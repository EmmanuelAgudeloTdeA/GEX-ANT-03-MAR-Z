<?php

namespace App\Policies;

use App\Enums\RequestStatus;
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

    public function view(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('View:SupportRequest') && $supportRequest->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:SupportRequest');
    }

    public function prioritize(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('Prioritize:SupportRequest')
            && $supportRequest->status !== RequestStatus::Closed;
    }

    /**
     * HU05: asignar o reasignar, solo en un estado desde el que la matriz
     * permite pasar a Asignada (Nuevo, Asignada, En progreso, Reabierta).
     */
    public function assign(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('Assign:SupportRequest')
            && $supportRequest->status->hasTransitionTo(RequestStatus::Assigned);
    }

    public function changeStatus(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('ChangeStatus:SupportRequest')
            && $supportRequest->assigned_agent_id === $user->getKey()
            && collect($supportRequest->status->allowedTargetsFor($user, $supportRequest))
                ->contains(fn (RequestStatus $status): bool => $status !== RequestStatus::Assigned);
    }

    /**
     * HU06: comenta el agente asignado o el coordinador (PD-06) y solo mientras
     * la solicitud siga abierta; el texto no vacio lo revisa el servicio.
     */
    public function comment(User $user, SupportRequest $supportRequest): bool
    {
        return $user->can('Comment:SupportRequest')
            && ($supportRequest->assigned_agent_id === $user->getKey()
                || $user->hasAnyRole(['coordinador', 'super_admin']))
            && $supportRequest->status !== RequestStatus::Closed;
    }

    // TODO HU08: permiso Confirm:SupportRequest + propietario + estado Resuelta.
    public function confirm(User $user, SupportRequest $supportRequest): bool
    {
        return false;
    }

    // TODO HU08: permiso Reopen:SupportRequest + propietario + estado Resuelta.
    public function reopen(User $user, SupportRequest $supportRequest): bool
    {
        return false;
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
