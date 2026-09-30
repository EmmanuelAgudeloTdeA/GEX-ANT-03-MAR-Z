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

class ChangeRequestStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, SupportRequest $request, RequestStatus $status): SupportRequest
    {
        Gate::forUser($user)->authorize('changeStatus', $request);

        return DB::transaction(function () use ($user, $request, $status): SupportRequest {
            $request = SupportRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if (! $request->status->canTransitionTo($status, $user, $request)) {
                throw new DomainException(
                    'La solicitud no puede cambiar a ese estado desde su estado actual.'
                );
            }

            $previous = $request->status;
            $previousResolvedAt = $request->resolved_at;

            $request->status = $status;

            if ($status === RequestStatus::Resolved) {
                $request->resolved_at = now();
            }

            $request->save();

            $changes = [
                'status' => [$previous, $status],
            ];

            if ($status === RequestStatus::Resolved) {
                $changes['resolved_at'] = [
                    $previousResolvedAt,
                    $request->resolved_at,
                ];
            }

            $this->audit->record(
                $request,
                AuditEvent::StatusChanged,
                $changes,
                actor: $user,
            );

            return $request;
        });
    }
}