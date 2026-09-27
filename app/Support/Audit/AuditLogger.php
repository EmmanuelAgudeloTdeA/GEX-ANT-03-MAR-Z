<?php

namespace App\Support\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Unico punto de escritura de audit_logs (Tech Plan §23.3).
 *
 * Debe llamarse dentro de la transaccion del servicio de dominio, para que
 * el cambio y su auditoria se guarden juntos o no se guarde ninguno.
 */
class AuditLogger
{
    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  campo => [anterior, nuevo]
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?SupportRequest $request,
        AuditEvent $event,
        array $changes = [],
        ?string $reason = null,
        array $metadata = [],
        ?User $actor = null,
    ): void {
        // Sin actor explicito se usa el usuario autenticado; sin ninguno, es el sistema.
        $actor ??= auth()->user();

        $base = [
            'support_request_id' => $request?->getKey(),
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->getRoleNames()->first() ?? 'system',
            'event' => $event,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'batch_id' => (string) Str::uuid(),
        ];

        if ($changes === []) {
            AuditLog::create($base);

            return;
        }

        foreach ($changes as $field => [$old, $new]) {
            AuditLog::create($base + [
                'field' => $field,
                'old_value' => $this->normalize($old),
                'new_value' => $this->normalize($new),
            ]);
        }
    }

    /**
     * Guarda el valor crudo (valor del enum, id, fecha ISO); la etiqueta
     * legible se resuelve al mostrar.
     */
    private function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => (string) $value,
        };
    }
}
