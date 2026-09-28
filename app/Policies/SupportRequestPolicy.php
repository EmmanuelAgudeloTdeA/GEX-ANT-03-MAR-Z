<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupportRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SupportRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SupportRequest');
    }

    public function view(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->isCoordinator()
            || (
                $supportRequest->user_id === $authUser->id
                && $authUser->can('View:SupportRequest')
            );
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SupportRequest');
    }

    public function update(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->isCoordinator();
    }

    public function delete(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->can('Delete:SupportRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SupportRequest');
    }

    public function restore(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->can('Restore:SupportRequest');
    }

    public function forceDelete(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->can('ForceDelete:SupportRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SupportRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SupportRequest');
    }

    public function replicate(
        AuthUser $authUser,
        SupportRequest $supportRequest
    ): bool {
        return $authUser->can('Replicate:SupportRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SupportRequest');
    }
}