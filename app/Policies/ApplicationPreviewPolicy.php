<?php

namespace App\Policies;

use App\Models\ApplicationPreview;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ApplicationPreviewPolicy
{
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
    public function view(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('view', $applicationPreview->application);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ApplicationPreview $applicationPreview)
    {
        return Gate::forUser($user)->allows('update', $applicationPreview->application);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('delete', $applicationPreview->application);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('update', $applicationPreview->application);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('delete', $applicationPreview->application);
    }

    /**
     * Determine whether the user can deploy the preview.
     */
    public function deploy(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('deploy', $applicationPreview->application);
    }

    /**
     * Determine whether the user can manage preview deployments.
     */
    public function manageDeployments(User $user, ApplicationPreview $applicationPreview): bool
    {
        return Gate::forUser($user)->allows('manageDeployments', $applicationPreview->application);
    }
}
