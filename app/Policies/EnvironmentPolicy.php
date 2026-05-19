<?php

namespace App\Policies;

use App\Models\Environment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class EnvironmentPolicy
{
    use AuthorizesTeamAccess;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Environment $environment): bool
    {
        return $this->canViewTeam($user, $this->teamId($environment));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageTeam($user, $this->currentTeamId($user));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Environment $environment): bool
    {
        return $this->canManageTeam($user, $this->teamId($environment));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Environment $environment): bool
    {
        return $this->canManageTeam($user, $this->teamId($environment));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Environment $environment): bool
    {
        return $this->canManageTeam($user, $this->teamId($environment));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Environment $environment): bool
    {
        return $this->canManageTeam($user, $this->teamId($environment));
    }

    private function teamId(Environment $environment): ?int
    {
        return data_get($environment, 'project.team_id');
    }
}
