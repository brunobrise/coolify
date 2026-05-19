<?php

namespace App\Policies;

use App\Models\EnvironmentVariable;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;
use Illuminate\Support\Facades\Gate;

class EnvironmentVariablePolicy
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
    public function view(User $user, EnvironmentVariable $environmentVariable): bool
    {
        $resource = $environmentVariable->resourceable;
        if (! $resource) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $resource);
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
    public function update(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->canManageEnvironment($user, $environmentVariable);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->canManageEnvironment($user, $environmentVariable);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->canManageEnvironment($user, $environmentVariable);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->canManageEnvironment($user, $environmentVariable);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->canManageEnvironment($user, $environmentVariable);
    }

    private function canManageEnvironment(User $user, EnvironmentVariable $environmentVariable): bool
    {
        $resource = $environmentVariable->resourceable;
        if (! $resource) {
            return false;
        }

        return Gate::forUser($user)->allows('manageEnvironment', $resource);
    }
}
