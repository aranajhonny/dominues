<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backoffice gate: only admin / host / business may enter the panel.
 * Section-level restrictions (host on sensitive routes) are enforced inside
 * the Livewire components — hiding a sidebar link is never enough.
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canAccessBackoffice()) {
            abort(403, 'Acceso restringido al personal autorizado de Dominues.');
        }

        return $next($request);
    }
}