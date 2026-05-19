<?php

namespace App\Policies;

use App\Models\CloudProviderToken;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class CloudProviderTokenPolicy
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
    public function view(User $user, CloudProviderToken $cloudProviderToken): bool
    {
        return $this->canManageTeam($user, $cloudProviderToken->team_id);
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
    public function update(User $user, CloudProviderToken $cloudProviderToken): bool
    {
        return $this->canManageTeam($user, $cloudProviderToken->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CloudProviderToken $cloudProviderToken): bool
    {
        return $this->canManageTeam($user, $cloudProviderToken->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CloudProviderToken $cloudProviderToken): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CloudProviderToken $cloudProviderToken): bool
    {
        return false;
    }
}
