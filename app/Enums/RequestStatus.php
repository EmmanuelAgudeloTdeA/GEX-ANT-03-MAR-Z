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
    case Nuevo = 'nuevo';
    case Asignada = 'asignada';
    case EnProgreso = 'en_progreso';
    case Resuelta = 'resuelta';
    case Reabierta = 'reabierta';
    case Cerrada = 'cerrada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Asignada => 'Asignada',
            self::EnProgreso => 'En progreso',
            self::Resuelta => 'Resuelta',
            self::Reabierta => 'Reabierta',
            self::Cerrada => 'Cerrada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Nuevo => 'gray',
            self::Asignada => 'info',
            self::EnProgreso => 'warning',
            self::Resuelta => 'success',
            self::Reabierta => 'danger',
            self::Cerrada => 'success',
        };
    }
}
