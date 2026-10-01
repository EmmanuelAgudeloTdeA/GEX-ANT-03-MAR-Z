<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReopenSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(
        User $user,
        SupportRequest $request,
        string $reason
    ): SupportRequest {
        Gate::forUser($user)->authorize('reopen', $request);

        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException(
                'Debes indicar el motivo de la reapertura.'
            );
        }

        return DB::transaction(function () use ($user, $request, $reason): SupportRequest {
            $request = SupportRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status !== RequestStatus::Resolved) {
                throw new DomainException(
                    'Solo se puede reabrir una solicitud que esté resuelta.'
                );
            }

            $previous = $request->status;
            $previousClosedAt = $request->closed_at;

            $request->status = RequestStatus::Reopened;
            $request->closed_at = null;
            $request->save();

            $this->audit->record(
                $request,
                AuditEvent::Reopened,
                [
                    'status' => [$previous, RequestStatus::Reopened],
                    'closed_at' => [$previousClosedAt, null],
                ],
                reason: $reason,
                actor: $user,
            );

            return $request;
        });
    }
}
