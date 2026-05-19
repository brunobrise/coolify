<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiSensitiveData
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()->currentAccessToken();
        $tokenTeamId = $token instanceof PersonalAccessToken
            ? $token->team_id
            : data_get($request->user()->currentTeam(), 'id');
        $tokenTeamId = apiNormalizeTeamId($tokenTeamId);
        $canUsePrivilegedAbility = apiUserCanUsePrivilegedAbilitiesForTeam($request->user(), $tokenTeamId);

        $request->attributes->add([
            'can_read_sensitive' => $canUsePrivilegedAbility && ($token?->can('root') || $token?->can('read:sensitive')),
        ]);

        return $next($request);
    }
}
