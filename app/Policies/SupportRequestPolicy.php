<?php

namespace App\Policies;

use App\Models\SupportRequest;
use App\Models\User;

/**
 * El permiso Shield dice si el rol tiene la capacidad; la condicion sobre el
 * registro se agrega aqui (Tech Plan §12.1).
 */
class SupportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:SupportRequest');
    }

    // TODO HU03: agregar la condicion de registro (propietario / agente asignado).
    public function view(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('View:SupportRequest');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:SupportRequest');
    }

    // Las solicitudes no se editan ni se borran (PD-07): los cambios se hacen
    // con acciones trazables. Aplica tambien a super_admin.
    public function update(User $user, SupportRequest $supportRequest): bool
    {
        return false;
    }

    public function delete(User $user, SupportRequest $supportRequest): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, SupportRequest $supportRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupportRequest $supportRequest): bool
    {
        return false;
    }
}
