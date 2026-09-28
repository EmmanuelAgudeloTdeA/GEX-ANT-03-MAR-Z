<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados de una solicitud de soporte (Tech Plan §8).
 *
 * Nuevo y Resuelta son requisito explicito; el resto es propuesta (PD-03).
 * La matriz de transiciones se agrega con HU07.
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
