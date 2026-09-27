<?php

namespace App\Policies;

use App\Models\SupportRequest;
use App\Models\User;

class SupportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Solicitante') || $user->can('ViewAny:SupportRequest');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Solicitante') || $user->can('Create:SupportRequest');
    }

    public function view(User $user, SupportRequest $supportRequest): bool
    {
        return $user->id === $supportRequest->user_id || $user->can('View:SupportRequest');
    }

    public function update(User $user, SupportRequest $supportRequest): bool
    {
        return $user->id === $supportRequest->user_id || $user->can('Update:SupportRequest');
    }
}
