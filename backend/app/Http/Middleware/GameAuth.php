<?php

namespace App\Http\Middleware;

use App\Models\GameToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the game service calls using a short-lived game token
 * (created by POST /api/game/session) instead of a Sanctum personal token.
 */
class GameAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Se requiere token de juego.'], 401);
        }

        $gameToken = GameToken::query()
            ->with(['user', 'game'])
            ->where('token', $token)
            ->where('expires_at', '>', \now())
            ->first();

        if (! $gameToken || ! $gameToken->user->active) {
            return response()->json(['message' => 'Token de juego inválido o expirado.'], 401);
        }

        $request->merge([
            'game_user' => $gameToken->user,
            'game_token' => $gameToken,
        ]);

        return $next($request);
    }
}