<?php

namespace App\Policies;

use App\Models\StandaloneDocker;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class StandaloneDockerPolicy
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
    public function view(User $user, StandaloneDocker $standaloneDocker): bool
    {
        return $this->canViewTeam($user, $standaloneDocker->server?->team_id);
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
    public function update(User $user, StandaloneDocker $standaloneDocker): bool
    {
        return $this->canManageTeam($user, $standaloneDocker->server?->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StandaloneDocker $standaloneDocker): bool
    {
        return $this->canManageTeam($user, $standaloneDocker->server?->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StandaloneDocker $standaloneDocker): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StandaloneDocker $standaloneDocker): bool
    {
        return false;
    }
}
