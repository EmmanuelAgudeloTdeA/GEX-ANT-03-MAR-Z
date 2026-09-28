<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    /**
     * status, priority y requester_id quedan fuera a proposito: solo los fijan
     * los servicios de dominio (app/Actions/SupportRequests), nunca el formulario.
     */
    protected $fillable = [
        'title',
        'description',
        'category_id',
    ];

    protected $attributes = [
        'status' => 'nuevo',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'priority' => RequestPriority::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Unico filtro de visibilidad por rol (Tech Plan §1, BR-03 a BR-05). Lo
     * reutilizan la tabla, la Policy y, mas adelante, widgets y exportacion.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole('solicitante')) {
            $query->where('requester_id', $user->getKey());

            return;
        }

        if ($user->hasAnyRole(['coordinador', 'super_admin'])) {
            return;
        }

        // TODO HU05: el agente vera las solicitudes con assigned_agent_id = su id.
        $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }
}
