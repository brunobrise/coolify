<?php

namespace App\Mcp\Concerns;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait ResolvesTeam
{
    protected function ensureAbility(Request $request, string $ability = 'read'): ?Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $token = $user->currentAccessToken();
        if (! $token) {
            return Response::error('Invalid token.');
        }

        $teamId = apiNormalizeTeamId(data_get($token, 'team_id'));
        if (! apiUserBelongsToTeam($user, $teamId)) {
            auditLog('mcp.auth.team_denied', [
                'required_ability' => $ability,
                'token_id' => data_get($token, 'id'),
                'team_id' => $teamId,
                'reason' => 'Token user is not a current member of the token team.',
            ], 'warning');

            return Response::error('Token team access is no longer valid.');
        }

        if ($token->can('root')) {
            if (! apiUserCanUsePrivilegedAbilitiesForTeam($user, $teamId)) {
                auditLog('mcp.auth.ability_denied', [
                    'required_ability' => $ability,
                    'token_id' => data_get($token, 'id'),
                    'reason' => 'Root token used by non-admin team member.',
                ], 'warning');

                return Response::error("Missing required permissions: {$ability}");
            }

            return null;
        }

        if ($token->can($ability)) {
            return null;
        }

        return Response::error("Missing required permissions: {$ability}");
    }

    protected function resolveTeamId(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();

        $teamId = apiNormalizeTeamId(data_get($token, 'team_id'));

        if (! apiUserBelongsToTeam($request->user(), $teamId)) {
            return null;
        }

        return $teamId;
    }
}
