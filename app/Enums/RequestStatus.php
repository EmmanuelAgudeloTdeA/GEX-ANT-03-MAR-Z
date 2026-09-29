<?php

namespace App\Enums;

use App\Models\SupportRequest;
use App\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados de una solicitud de soporte (Tech Plan §8).
 *
 * Nuevo y Resuelta son requisito explicito; el resto es propuesta (PD-03).
 */
enum RequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Reopened = 'reopened';
    case Closed = 'closed';

    /**
     * Matriz de transiciones validas (Tech Plan §9.1): unica fuente de verdad.
     *
     * Las transiciones hacia Asignada solo ocurren como efecto de asignar
     * (T1) o reasignar (T7), nunca desde "Cambiar estado"; Asignada ->
     * Asignada es la reasignacion.
     *
     * @return array<string, array<string, TransitionActor>> desde => [hacia => actor]
     */
    public static function transitions(): array
    {
        return [
            self::New->value => [
                self::Assigned->value => TransitionActor::Coordinator,
            ],
            self::Assigned->value => [
                self::InProgress->value => TransitionActor::AssignedAgent,
                self::Assigned->value => TransitionActor::Coordinator,
            ],
            self::InProgress->value => [
                self::Resolved->value => TransitionActor::AssignedAgent,
                self::Assigned->value => TransitionActor::Coordinator,
            ],
            self::Resolved->value => [
                self::Closed->value => TransitionActor::Owner,
                self::Reopened->value => TransitionActor::Owner,
            ],
            self::Reopened->value => [
                self::InProgress->value => TransitionActor::AssignedAgent,
                self::Assigned->value => TransitionActor::Coordinator,
            ],
            self::Closed->value => [],
        ];
    }

    /**
     * Solo la matriz: existe la transicion, sin mirar quien la ejecuta.
     */
    public function hasTransitionTo(self $to): bool
    {
        return isset(self::transitions()[$this->value][$to->value]);
    }

    /**
     * Matriz + actor: la transicion existe y este usuario puede ejecutarla
     * sobre este registro. Los servicios la vuelven a validar en servidor.
     */
    public function canTransitionTo(self $to, User $user, SupportRequest $request): bool
    {
        $actor = self::transitions()[$this->value][$to->value] ?? null;

        return $actor !== null && $actor->matches($user, $request);
    }

    /**
     * Estados destino que este usuario puede elegir desde el estado actual.
     *
     * @return array<self>
     */
    public function allowedTargetsFor(User $user, SupportRequest $request): array
    {
        return collect(self::transitions()[$this->value])
            ->filter(fn (TransitionActor $actor): bool => $actor->matches($user, $request))
            ->keys()
            ->map(fn (string $to): self => self::from($to))
            ->all();
    }

    /**
     * Estados en los que la solicitud sigue en atencion (ni resuelta ni cerrada).
     *
     * @return array<self>
     */
    public static function open(): array
    {
        return [self::New, self::Assigned, self::InProgress, self::Reopened];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Assigned => 'Asignada',
            self::InProgress => 'En progreso',
            self::Resolved => 'Resuelta',
            self::Reopened => 'Reabierta',
            self::Closed => 'Cerrada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Assigned => 'info',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Reopened => 'danger',
            self::Closed => 'success',
        };
    }
}
