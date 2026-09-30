<?php

namespace App\Policies;

use App\Models\RequestComment;
use App\Models\SupportRequest;
use App\Models\User;

/**
 * Un comentario se ve y se crea unicamente por medio de la solicitud que
 * contiene, asi que esta Policy no inventa reglas propias: delega en
 * SupportRequestPolicy (Tech Plan §12.2).
 */
class RequestCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', SupportRequest::class);
    }

    public function view(User $user, RequestComment $comment): bool
    {
        return $user->can('view', $comment->supportRequest);
    }

    /**
     * Filament consulta este metodo sin un registro concreto (no hay comentario
     * todavia), asi que aqui solo se comprueba la capacidad del rol. La
     * condicion sobre la solicitud (agente asignado o coordinador, y estado
     * distinto de Cerrada) la aplica SupportRequestPolicy::comment, que es la
     * que consultan la Action y el servicio.
     */
    public function create(User $user): bool
    {
        return $user->can('Comment:SupportRequest');
    }

    // Un comentario no se edita ni se borra, ni siquiera por super_admin (R-03).
    public function update(User $user, RequestComment $comment): bool
    {
        return false;
    }

    public function delete(User $user, RequestComment $comment): bool
    {
        return false;
    }
}
