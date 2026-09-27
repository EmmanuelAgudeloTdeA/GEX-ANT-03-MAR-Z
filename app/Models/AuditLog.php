<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evento historico inmutable. Solo lo escribe App\Support\Audit\AuditLogger.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'support_request_id',
        'actor_id',
        'actor_role',
        'event',
        'field',
        'old_value',
        'new_value',
        'reason',
        'metadata',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los registros de auditoría no se pueden modificar.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los registros de auditoría no se pueden eliminar.');
        });
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
