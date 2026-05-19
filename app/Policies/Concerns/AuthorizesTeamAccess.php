<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesTeamAccess
{
    protected function canViewTeam(User $user, ?int $teamId): bool
    {
        if ($teamId === null) {
            return false;
        }

        if ($teamId === 0) {
            return $user->canAccessSystemResources();
        }

        return $user->teams->contains('id', $teamId);
    }

    protected function canManageTeam(User $user, ?int $teamId): bool
    {
        if ($teamId === null) {
            return false;
        }

        if ($teamId === 0) {
            return $user->canAccessSystemResources();
        }

        return $user->isAdminOfTeam($teamId);
    }

    protected function currentTeamId(User $user): ?int
    {
        return data_get($user->currentTeam(), 'id');
    }
}
