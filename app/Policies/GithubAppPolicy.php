<?php

namespace App\Policies;

use App\Models\GithubApp;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class GithubAppPolicy
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
    public function view(User $user, GithubApp $githubApp): bool
    {
        if ($githubApp->is_system_wide) {
            return true;
        }

        return $this->canViewTeam($user, $githubApp->team_id);
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
    public function update(User $user, GithubApp $githubApp): bool
    {
        if ($githubApp->is_system_wide) {
            return $user->canAccessSystemResources();
        }

        return $this->canManageTeam($user, $githubApp->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GithubApp $githubApp): bool
    {
        if ($githubApp->is_system_wide) {
            return $user->canAccessSystemResources();
        }

        return $this->canManageTeam($user, $githubApp->team_id);
    }

    public function useForDeployment(User $user, GithubApp $githubApp): bool
    {
        if ($githubApp->is_system_wide) {
            return $this->canManageTeam($user, $this->currentTeamId($user));
        }

        return $this->canManageTeam($user, $githubApp->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GithubApp $githubApp): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GithubApp $githubApp): bool
    {
        return false;
    }
}
