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
    case Baja = 1;
    case Media = 2;
    case Alta = 3;

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Baja => 'gray',
            self::Media => 'warning',
            self::Alta => 'danger',
        };
    }
}
