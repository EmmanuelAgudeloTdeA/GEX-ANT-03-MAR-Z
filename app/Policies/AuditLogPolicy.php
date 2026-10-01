<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * HU11: consulta global del historial.
     * Solo los usuarios con el permiso Shield correspondiente pueden verla.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewHistory:SupportRequest');
    }

    /**
     * HU11: consultar un registro individual del historial.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can('ViewHistory:SupportRequest');
    }

    /**
     * La auditoría nunca se crea desde Filament.
     * La escritura la realiza exclusivamente AuditLogger.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Los registros de auditoría son inmutables.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Los registros de auditoría no se pueden eliminar.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
