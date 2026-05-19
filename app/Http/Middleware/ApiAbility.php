<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAbility extends CheckForAnyAbility
{
    public function handle($request, $next, ...$abilities)
    {
        try {
            $token = $request->user()->currentAccessToken();
            $tokenId = data_get($token, 'id');
            $tokenTeamId = $token instanceof PersonalAccessToken
                ? $token->team_id
                : data_get($request->user()->currentTeam(), 'id');
            $tokenTeamId = apiNormalizeTeamId($tokenTeamId);

            if (! apiUserBelongsToTeam($request->user(), $tokenTeamId)) {
                auditLog('api.auth.team_denied', [
                    'required_abilities' => $abilities,
                    'token_id' => $tokenId,
                    'team_id' => $tokenTeamId,
                    'reason' => 'Token user is not a current member of the token team.',
                ], 'warning');

                return response()->json([
                    'message' => 'Token team access is no longer valid.',
                ], 403);
            }

            $canUsePrivilegedAbility = apiUserCanUsePrivilegedAbilitiesForTeam($request->user(), $tokenTeamId);

            if ($request->user()->tokenCan('root')) {
                if (! $canUsePrivilegedAbility) {
                    auditLog('api.auth.ability_denied', [
                        'required_abilities' => $abilities,
                        'token_id' => $tokenId,
                        'reason' => 'Root token used by non-admin team member.',
                    ], 'warning');

                    return response()->json([
                        'message' => 'Missing required permissions: '.implode(', ', $abilities),
                    ], 403);
                }

                return $next($request);
            }

            if (in_array('write', $abilities, true) && $request->user()->tokenCan('write') && ! $canUsePrivilegedAbility) {
                auditLog('api.auth.ability_denied', [
                    'required_abilities' => $abilities,
                    'token_id' => $tokenId,
                    'reason' => 'Write token used by non-admin team member.',
                ], 'warning');

                return response()->json([
                    'message' => 'Missing required permissions: '.implode(', ', $abilities),
                ], 403);
            }

            return parent::handle($request, $next, ...$abilities);
        } catch (AuthenticationException $e) {
            auditLog('api.auth.unauthenticated', [
                'reason' => $e->getMessage(),
                'required_abilities' => $abilities,
            ], 'warning');

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        } catch (\Exception $e) {
            auditLog('api.auth.ability_denied', [
                'required_abilities' => $abilities,
                'token_id' => data_get($request->user()?->currentAccessToken(), 'id'),
                'reason' => $e->getMessage(),
            ], 'warning');

            return response()->json([
                'message' => 'Missing required permissions: '.implode(', ', $abilities),
            ], 403);
        }
    }
}
