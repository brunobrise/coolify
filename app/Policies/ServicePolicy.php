<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;

class ServicePolicy
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
    public function view(User $user, Service $service): bool
    {
        return $this->canViewTeam($user, $this->teamId($service));
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
    public function update(User $user, Service $service): bool
    {
        $team = $service->team();
        if (! $team) {
            return false;
        }

        return $this->canManageTeam($user, $team->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Service $service): bool
    {
        return $this->canManageTeam($user, $this->teamId($service));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Service $service): bool
    {
        return $this->canManageTeam($user, $this->teamId($service));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return $this->canManageTeam($user, $this->teamId($service));
    }

    public function stop(User $user, Service $service): bool
    {
        $team = $service->team();
        if (! $team) {
            return false;
        }

        return $this->canManageTeam($user, $team->id);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, Service $service): bool
    {
        $team = $service->team();
        if (! $team) {
            return false;
        }

        return $this->canManageTeam($user, $team->id);
    }

    /**
     * Determine whether the user can deploy the service.
     */
    public function deploy(User $user, Service $service): bool
    {
        $team = $service->team();
        if (! $team) {
            return false;
        }

        return $this->canManageTeam($user, $team->id);
    }

    public function accessTerminal(User $user, Service $service): bool
    {
        return $this->canManageTeam($user, $this->teamId($service));
    }

    private function teamId(Service $service): ?int
    {
        return data_get($service->team(), 'id');
    }
}
