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

class ConfirmSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, SupportRequest $request): SupportRequest
    {
        Gate::forUser($user)->authorize('confirm', $request);

        return DB::transaction(function () use ($user, $request): SupportRequest {
            $request = SupportRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status !== RequestStatus::Resolved) {
                throw new DomainException(
                    'Solo se puede confirmar una solicitud que esté resuelta.'
                );
            }

            $previous = $request->status;
            $previousClosedAt = $request->closed_at;

            $request->status = RequestStatus::Closed;
            $request->closed_at = now();
            $request->save();

            $this->audit->record(
                $request,
                AuditEvent::Confirmed,
                [
                    'status' => [$previous, RequestStatus::Closed],
                    'closed_at' => [$previousClosedAt, $request->closed_at],
                ],
                actor: $user,
            );

            return $request;
        });
    }
}
