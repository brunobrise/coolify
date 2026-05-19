<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanCreateResources
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Authentication required.');
        }

        if (! $user->currentTeam() || ! $user->isAdminFromSession()) {
            abort(403, 'You need admin or owner permissions to create resources.');
        }

        return $next($request);
    }
}
