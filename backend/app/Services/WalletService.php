<?php

namespace App\Services;

use App\Models\MatchGame;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry point for every money movement.
 * All operations run inside a DB transaction with row-level locks,
 * so concurrent game/panel requests cannot double-credit or double-debit.
 */
class WalletService
{
    /**
     * Register a deposit request (pending). No balance change yet.
     */
    public function requestDeposit(User $user, float $amount, string $method, ?string $reference, ?string $proofBase64): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $method, $reference, $proofBase64) {
            return Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'status' => 'pending',
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'meta' => $proofBase64 ? ['proof' => $proofBase64] : null,
            ]);
        });
    }

    /**
     * Approve a deposit: credit the balance EXACTLY ONCE (idempotent by status).
     */
    public function approveDeposit(Transaction $tx): Transaction
    {
        return DB::transaction(function () use ($tx) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();

            if ($tx->status === 'approved') {
                return $tx; // idempotent
            }
            if ($tx->type !== 'deposit' || $tx->status !== 'pending') {
                throw new RuntimeException('Depósito no aprobable en su estado actual.');
            }

            User::whereKey($tx->user_id)->lockForUpdate()->increment('balance', $tx->amount);
            $tx->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

            return $tx;
        });
    }

    public function rejectDeposit(Transaction $tx, string $note): Transaction
    {
        return DB::transaction(function () use ($tx, $note) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->status === 'rejected') {
                return $tx;
            }
            $tx->update([
                'status' => 'rejected',
                'reference' => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $tx;
        });
    }

    /**
     * Withdrawal request: RESERVE the amount — debit from available balance
     * and move it to reserved_balance. The money is taken once, at request time.
     */
    public function requestWithdrawal(User $user, float $amount): Transaction
    {
        return DB::transaction(function () use ($user, $amount) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $available = (float) $locked->balance - (float) $locked->reserved_balance;

            if ($available < $amount) {
                throw new RuntimeException('Saldo disponible insuficiente para el retiro.');
            }

            $locked->decrement('balance', $amount);
            $locked->increment('reserved_balance', $amount);

            return Transaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'status' => 'pending',
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Approve a withdrawal: money was already debited at request time,
     * so we only release the reservation and mark the movement.
     * NEVER re-debits here.
     */
    public function approveWithdrawal(Transaction $tx): Transaction
    {
        return DB::transaction(function () use ($tx) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();

            if ($tx->status === 'approved') {
                return $tx;
            }
            if ($tx->type !== 'withdrawal' || $tx->status !== 'pending') {
                throw new RuntimeException('Retiro no aprobable en su estado actual.');
            }

            $user = User::whereKey($tx->user_id)->lockForUpdate()->firstOrFail();
            $user->decrement('reserved_balance', $tx->amount);
            $user->increment('balance', -0); // no-op keeper for clarity: balance already debited

            $tx->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

            return $tx;
        });
    }

    /**
     * Reject a withdrawal: release the reservation and refund the money back
     * into the available balance — only when it was reserved.
     */
    public function rejectWithdrawal(Transaction $tx, string $note): Transaction
    {
        return DB::transaction(function () use ($tx, $note) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();

            if ($tx->status === 'rejected') {
                return $tx;
            }

            $user = User::whereKey($tx->user_id)->lockForUpdate()->firstOrFail();

            if ($tx->status === 'pending') {
                $user->decrement('reserved_balance', $tx->amount);
                $user->increment('balance', $tx->amount);
            }

            $tx->update([
                'status' => 'rejected',
                'reference' => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $tx;
        });
    }

    /**
     * Game join: charge the entry stake (completed). Idempotent per match+user.
     */
    public function gameStake(User $user, MatchGame $match): Transaction
    {
        return DB::transaction(function () use ($user, $match) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $stake = (float) $match->game->min_bet;
            $available = (float) $user->balance - (float) $user->reserved_balance;

            $existing = Transaction::query()
                ->where('user_id', $user->id)
                ->where('match_id', $match->id)
                ->where('type', 'game_stake')
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                return $existing; // idempotent
            }

            if ($available < $stake) {
                throw new RuntimeException('Saldo insuficiente para entrar a la mesa.');
            }

            $user->decrement('balance', $stake);

            return Transaction::create([
                'user_id' => $user->id,
                'type' => 'game_stake',
                'status' => 'completed',
                'amount' => -$stake,
                'match_id' => $match->id,
            ]);
        });
    }

    /**
     * Finish a match: compute pot from real stakes, apply platform fee,
     * credit the winner exactly once. Idempotent by match.status.
     */
    public function settleMatch(MatchGame $match, int $winnerId): MatchGame
    {
        return DB::transaction(function () use ($match, $winnerId) {
            $match = MatchGame::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status === 'finished') {
                return $match; // already settled
            }

            $pot = (float) $match->transactions()
                ->where('type', 'game_stake')
                ->where('status', 'completed')
                ->sum('amount') * -1;

            $feePercent = (float) $match->game->fee_percent;
            $fee = round($pot * $feePercent / 100, 2);
            $prize = round($pot - $fee, 2);

            $winner = User::whereKey($winnerId)->lockForUpdate()->firstOrFail();
            $winner->increment('balance', $prize);

            Transaction::create([
                'user_id' => $winner->id,
                'type' => 'game_win',
                'status' => 'completed',
                'amount' => $prize,
                'match_id' => $match->id,
                'meta' => ['pot' => $pot, 'fee' => $fee],
            ]);

            $match->update([
                'status' => 'finished',
                'winner_id' => $winner->id,
                'pot' => $pot,
                'fee_amount' => $fee,
                'prize' => $prize,
                'finished_at' => now(),
            ]);

            return $match;
        });
    }

    /**
     * Refund a single player's stake for a never-started match (they left the table).
     * Idempotent: already-refunded stakes are skipped.
     */
    public function refundPlayerStake(User $user, MatchGame $match): void
    {
        DB::transaction(function () use ($user, $match) {
            $match = MatchGame::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($match->status === 'finished' || $match->status === 'refunded') {
                return;
            }

            $stake = $match->transactions()
                ->where('user_id', $user->id)
                ->where('type', 'game_stake')
                ->where('status', 'completed')
                ->first();

            if ($stake) {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $locked->increment('balance', abs((float) $stake->amount));
                $stake->update(['status' => 'refunded']);
            }
        });
    }

    /**
     * Cancel a never-started match: refund every player's stake.
     */
    public function refundMatch(MatchGame $match): void
    {
        DB::transaction(function () use ($match) {
            $match = MatchGame::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status === 'refunded' || $match->status === 'finished') {
                return;
            }

            $stakes = $match->transactions()
                ->where('type', 'game_stake')
                ->where('status', 'completed')
                ->get();

            foreach ($stakes as $stake) {
                $user = User::whereKey($stake->user_id)->lockForUpdate()->firstOrFail();
                $user->increment('balance', abs((float) $stake->amount));
                $stake->update(['status' => 'refunded']);
            }

            $match->update(['status' => 'refunded', 'finished_at' => now()]);
        });
    }
}