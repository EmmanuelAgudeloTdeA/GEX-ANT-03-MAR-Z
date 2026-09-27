<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    /**
     * status y requester_id quedan fuera a proposito: solo los fijan los
     * servicios de dominio (app/Actions/SupportRequests), nunca el formulario.
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
}
