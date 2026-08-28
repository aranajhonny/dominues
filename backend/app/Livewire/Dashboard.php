<?php

namespace App\Livewire;

use App\Models\MatchGame;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $pendingKyc = DB::table('kyc_documents')->where('status', 'pending')->count();
        $pendingDeposits = DB::table('transactions')->where('type', 'deposit')->where('status', 'pending')->count();
        $pendingWithdrawals = DB::table('transactions')->where('type', 'withdrawal')->where('status', 'pending')->count();

        $today = now()->startOfDay();
        $incomeToday = (float) DB::table('transactions')
            ->where('type', 'deposit')->where('status', 'approved')
            ->where('created_at', '>=', $today)
            ->sum('amount');
        $withdrawalsToday = (float) DB::table('transactions')
            ->where('type', 'withdrawal')->where('status', 'approved')
            ->where('created_at', '>=', $today)
            ->sum('amount');
        $rakeTotal = (float) MatchGame::where('status', 'finished')->sum('fee_amount');

        $ranking = User::query()
            ->select('users.id', 'users.name', DB::raw('SUM(t.amount) as winnings'))
            ->join('transactions as t', 't.user_id', '=', 'users.id')
            ->where('t.type', 'game_win')
            ->where('t.status', 'completed')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('winnings')
            ->limit(10)
            ->get();

        $playingMatches = MatchGame::where('status', 'playing')->count();

        return view('livewire.dashboard', [
            'stats' => [
                'users' => User::count(),
                'active_users' => User::where('active', true)->count(),
                'pending_kyc' => $pendingKyc,
                'pending_deposits' => $pendingDeposits,
                'pending_withdrawals' => $pendingWithdrawals,
                'income_today' => $incomeToday,
                'withdrawals_today' => $withdrawalsToday,
                'rake_total' => $rakeTotal,
                'playing_matches' => $playingMatches,
                'admin_mail' => Setting::get('admin_contact', '—'),
            ],
            'ranking' => $ranking,
            'bonusConfigured' => false, // no bonus module configured → “No configurado”, never fake zeros
        ]);
    }
}