<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'status',
        'priority',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::updated(function (SupportRequest $supportRequest): void {
            if (
                $supportRequest->wasChanged('priority')
                && auth()->check()
            ) {
                SupportRequestAudit::create([
                    'support_request_id' => $supportRequest->id,
                    'user_id' => auth()->id(),
                    'field' => 'priority',
                    'old_value' => $supportRequest->getOriginal('priority'),
                    'new_value' => $supportRequest->priority,
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priorityAudits()
    {
        return $this->hasMany(SupportRequestAudit::class);
    }
}