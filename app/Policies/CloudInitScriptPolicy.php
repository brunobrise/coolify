<?php

namespace App\Policies;

use App\Models\CloudInitScript;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class CloudInitScriptPolicy
{
    use AuthorizesTeamAccess;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageTeam($user, $this->currentTeamId($user));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CloudInitScript $cloudInitScript): bool
    {
        return $this->canManageTeam($user, $cloudInitScript->team_id);
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
    public function update(User $user, CloudInitScript $cloudInitScript): bool
    {
        return $this->canManageTeam($user, $cloudInitScript->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CloudInitScript $cloudInitScript): bool
    {
        return $this->canManageTeam($user, $cloudInitScript->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CloudInitScript $cloudInitScript): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CloudInitScript $cloudInitScript): bool
    {
        return false;
    }
}
