<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * HU04: solo el coordinador prioriza, con un valor valido, y el cambio queda
 * trazado en la misma transaccion.
 */
class PrioritizeSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, SupportRequest $request, RequestPriority $priority): SupportRequest
    {
        Gate::forUser($user)->authorize('prioritize', $request);

        return DB::transaction(function () use ($user, $request, $priority): SupportRequest {
            $request = SupportRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            $previous = $request->priority;

            // Asignar la misma prioridad no es un cambio y no se audita.
            if ($previous === $priority) {
                return $request;
            }

            $request->priority = $priority;
            $request->save();

            $this->audit->record(
                $request,
                AuditEvent::PriorityChanged,
                ['priority' => [$previous, $priority]],
                actor: $user,
            );

            return $request;
        });
    }
}
