<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Prioridad de una solicitud (Tech Plan §5.6, PD-01).
 *
 * Es entera para que ordenar por prioridad sea un ORDER BY natural
 * (Baja < Media < Alta). Una solicitud sin priorizar tiene null.
 */
enum RequestPriority: int implements HasColor, HasLabel
{
    case Low = 1;
    case Medium = 2;
    case High = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }
}
