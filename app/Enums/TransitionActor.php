<?php

namespace App\Enums;

use App\Models\SupportRequest;
use App\Models\User;

/**
 * Quien puede ejecutar una transicion de estado (Tech Plan §9.1). La
 * condicion depende del registro, no solo del rol.
 */
enum TransitionActor: string
{
    // Asigna o reasigna (HU05).
    case Coordinator = 'coordinator';

    // Atiende la solicitud que tiene asignada (HU07).
    case AssignedAgent = 'assigned_agent';

    // Confirma o reabre su propia solicitud (HU08).
    case Owner = 'owner';

    public function matches(User $user, SupportRequest $request): bool
    {
        return match ($this) {
            self::Coordinator => $user->hasAnyRole(['coordinador', 'super_admin']),
            self::AssignedAgent => $request->assigned_agent_id !== null
                && $request->assigned_agent_id === $user->getKey(),
            self::Owner => $request->requester_id === $user->getKey(),
        };
    }
}
