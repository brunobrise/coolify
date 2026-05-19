<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTeamAccess;
use Illuminate\Database\Eloquent\Model;

class NotificationPolicy
{
    use AuthorizesTeamAccess;

    /**
     * Determine whether the user can view the notification settings.
     */
    public function view(User $user, Model $notificationSettings): bool
    {
        return $this->canViewTeam($user, $this->notificationTeamId($notificationSettings));
    }

    /**
     * Determine whether the user can update the notification settings.
     */
    public function update(User $user, Model $notificationSettings): bool
    {
        return $this->canManageTeam($user, $this->notificationTeamId($notificationSettings));
    }

    /**
     * Determine whether the user can manage (create, update, delete) notification settings.
     */
    public function manage(User $user, Model $notificationSettings): bool
    {
        return $this->update($user, $notificationSettings);
    }

    /**
     * Determine whether the user can send test notifications.
     */
    public function sendTest(User $user, Model $notificationSettings): bool
    {
        return $this->update($user, $notificationSettings);
    }

    private function notificationTeamId(Model $notificationSettings): ?int
    {
        $teamId = $notificationSettings->getAttribute('team_id');
        if ($teamId !== null) {
            return (int) $teamId;
        }

        if ($notificationSettings->relationLoaded('team')) {
            return data_get($notificationSettings->getRelation('team'), 'id');
        }

        return null;
    }
}
