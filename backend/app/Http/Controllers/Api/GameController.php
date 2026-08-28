<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameToken;
use App\Models\MatchGame;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Active games exposed to the portal lobby.
     */
    public function index(): JsonResponse
    {
        $games = Game::active()->get()->map(function (Game $game) {
            $openMatches = MatchGame::where('game_id', $game->id)
                ->whereIn('status', ['open', 'playing'])
                ->count();

            return [
                'id' => $game->id,
                'name' => $game->name,
                'mode' => $game->mode,
                'min_bet' => $game->min_bet,
                'max_players' => $game->max_players,
                'players_count' => $openMatches,
            ];
        });

        return response()->json(['games' => $games]);
    }

    /**
     * Portal asks for a short-lived game token to open the game client.
     */
    public function session(Request $request): JsonResponse
    {
        $data = $request->validate(['game_id' => ['required', 'exists:games,id']]);

        $game = Game::whereKey($data['game_id'])->where('active', true)->firstOrFail();

        $token = GameToken::create([
            'user_id' => $request->user()->id,
            'game_id' => $game->id,
            'token' => Str::random(40),
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json([
            'token' => $token->token,
            'expires_at' => $token->expires_at->toISOString(),
            'game_id' => $game->id,
        ]);
    }

    /**
     * Game service validates a game token on socket connect.
     */
    public function validateToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        $gameToken = GameToken::query()
            ->with('user')
            ->where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $gameToken || ! $gameToken->user->active) {
            return response()->json(['valid' => false], 401);
        }

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $gameToken->user->id,
                'name' => $gameToken->user->name,
                'email' => $gameToken->user->email,
            ],
            'balance' => $gameToken->user->balance,
        ]);
    }

    /**
     * Player joins a match: registers stake (money handled by WalletService).
     * Middleware: GameAuth (Bearer game token).
     */
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['game_id' => ['required', 'exists:games,id']]);
        /** @var User $user */
        $user = $request->input('game_user');
        /** @var Game $game */
        $game = Game::whereKey($data['game_id'])->where('active', true)->firstOrFail();

        $match = MatchGame::where('game_id', $game->id)
            ->whereIn('status', ['open', 'playing'])
            ->first();

        if (! $match) {
            $match = MatchGame::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'status' => 'open',
                'players' => [],
            ]);
        } elseif ($match->status === 'playing' && $match->started_at && $match->started_at->lt(now()->subMinutes(5))) {
            // Orphan table: the game service crashed mid-match without a result.
            // Refund everyone and open a fresh match so players aren't locked out.
            $this->wallet->refundMatch($match);
            $match = MatchGame::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'status' => 'open',
                'players' => [],
            ]);
        }

        $players = collect($match->players ?? []);
        $alreadyIn = $players->contains(fn ($p) => (int) $p['user_id'] === (int) $user->id);

        if (! $alreadyIn) {
            if ($players->count() >= (int) $game->max_players) {
                return response()->json(['ok' => false, 'error' => 'Mesa llena.'], 422);
            }
            $players->push([
                'id' => $user->id,
                'name' => $user->name,
                'user_id' => $user->id,
            ]);
            $match->update(['players' => $players->values()->all()]);
        }

        try {
            $this->wallet->gameStake($user, $match);
        } catch (\RuntimeException $e) {
            // Roll back the roster if we added them but payment failed.
            if (! $alreadyIn) {
                $match->update(['players' => $players->slice(0, -1)->values()->all()]);
            }
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if ($players->count() >= (int) $game->max_players && $match->status === 'open') {
            $match->update(['status' => 'playing', 'started_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'match_id' => $match->id,
            'players' => $match->players,
            'stake' => $game->min_bet,
        ]);
    }

    /**
     * Player leaves a table before the match started → individual refund.
     */
    public function refund(Request $request): JsonResponse
    {
        $data = $request->validate(['game_id' => ['required', 'exists:games,id']]);
        /** @var User $user */
        $user = $request->input('game_user');

        $match = MatchGame::where('game_id', $data['game_id'])
            ->whereIn('status', ['open', 'playing'])
            ->get()
            ->first(fn (MatchGame $m) => collect($m->players ?? [])
                ->contains(fn ($p) => (int) $p['user_id'] === (int) $user->id));

        if (! $match) {
            return response()->json(['ok' => false, 'error' => 'No estás en una mesa de ese juego.'], 422);
        }

        if ($match->status !== 'open') {
            return response()->json(['ok' => false, 'error' => 'La partida ya inició; no puedes retirarte.'], 422);
        }

        $this->wallet->refundPlayerStake($user, $match);

        $players = collect($match->players ?? [])
            ->reject(fn ($p) => (int) $p['user_id'] === (int) $user->id)
            ->values()
            ->all();

        $match->update(['players' => $players]);

        return response()->json(['ok' => true, 'players' => $players]);
    }

    /**
     * Game service reports a finished match → settle pot & pay winner once.
     */
    public function result(Request $request): JsonResponse
    {
        $data = $request->validate([
            'match_id' => ['required', 'string'],
            'winner_id' => ['required', 'integer'],
        ]);
        /** @var User $user */
        $user = $request->input('game_user');

        $match = MatchGame::whereKey($data['match_id'])->first();

        if (! $match) {
            return response()->json(['ok' => false, 'error' => 'Partida no encontrada.'], 404);
        }

        $isPlayer = collect($match->players ?? [])->contains(fn ($p) => (int) $p['user_id'] === (int) $user->id);
        if (! $isPlayer && $match->winner_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'No eres jugador de esta partida.'], 403);
        }

        $isWinnerPlayer = collect($match->players ?? [])
            ->contains(fn ($p) => (int) $p['user_id'] === (int) $data['winner_id']);
        if (! $isWinnerPlayer) {
            return response()->json(['ok' => false, 'error' => 'El ganador no es jugador de la partida.'], 422);
        }

        $settled = $this->wallet->settleMatch($match, (int) $data['winner_id']);

        return response()->json([
            'ok' => true,
            'pot' => $settled->pot,
            'fee' => $settled->fee_amount,
            'prize' => $settled->prize,
            'status' => $settled->status,
        ]);
    }
}