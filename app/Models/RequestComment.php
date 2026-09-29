<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * HU06: comentario de trabajo. Autor y fecha inmutables; no se edita ni se
 * borra una vez creado.
 */
class RequestComment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'body',
    ];

    // El comentario actualiza la "ultima actualizacion" de la solicitud (HU03).
    protected $touches = ['supportRequest'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los comentarios no se pueden modificar.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los comentarios no se pueden eliminar.');
        });
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
