<?php

namespace App\Support\Audit;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * HU11: traduce los nombres tecnicos de los campos auditados y sus valores
 * crudos a etiquetas legibles para la interfaz (Tech Plan §23.4).
 *
 * Es la unica fuente de verdad del mapeo: AuditLogResource y
 * HistoryRelationManager la comparten y no repiten estas reglas. Solo afecta
 * a la representacion; nunca modifica los datos guardados ni los eventos.
 */
final class AuditChangeMapper
{
    /**
     * Etiquetas legibles de los campos que escribe AuditLogger. Al registrar
     * un campo auditado nuevo, agregarlo aqui para no mostrar su nombre
     * tecnico al usuario.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'status' => 'Estado',
        'priority' => 'Prioridad',
        'assigned_agent_id' => 'Agente asignado',
        'resolved_at' => 'Fecha de resolución',
        'closed_at' => 'Fecha de cierre',
    ];

    /**
     * Nombre legible del campo. Un campo no contemplado no se inventa: cae al
     * respaldo neutral de Str::headline y el cambio sigue visible.
     */
    public static function fieldLabel(?string $field): ?string
    {
        if ($field === null || $field === '') {
            return null;
        }

        return self::FIELD_LABELS[$field] ?? Str::headline($field);
    }

    /**
     * Valor legible de un cambio, segun el campo al que pertenece. Un valor
     * fuera de catalogo se conserva tal cual: no se pierde informacion.
     */
    public static function value(?string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'priority' => self::priorityLabel($value),
            'status' => self::statusLabel($value),
            'assigned_agent_id' => self::agentCode($value),
            default => $value,
        };
    }

    private static function priorityLabel(string $value): string
    {
        return RequestPriority::tryFrom((int) $value)?->getLabel() ?? $value;
    }

    private static function statusLabel(string $value): string
    {
        return RequestStatus::tryFrom($value)?->getLabel() ?? $value;
    }

    private static function agentCode(string $value): string
    {
        return User::query()->find($value)?->code ?? $value;
    }
}
