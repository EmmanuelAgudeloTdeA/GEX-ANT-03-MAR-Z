<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de evento que se registran en audit_logs (Tech Plan §23.2).
 */
enum AuditEvent: string implements HasLabel
{
    case Created = 'created';
    case PriorityChanged = 'priority_changed';
    case Assigned = 'assigned';
    case StatusChanged = 'status_changed';
    case CommentAdded = 'comment_added';
    case Confirmed = 'confirmed';
    case Reopened = 'reopened';
    case Exported = 'exported';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Creación',
            self::PriorityChanged => 'Cambio de prioridad',
            self::Assigned => 'Asignación',
            self::StatusChanged => 'Cambio de estado',
            self::CommentAdded => 'Comentario',
            self::Confirmed => 'Confirmación',
            self::Reopened => 'Reapertura',
            self::Exported => 'Exportación',
        };
    }
}
