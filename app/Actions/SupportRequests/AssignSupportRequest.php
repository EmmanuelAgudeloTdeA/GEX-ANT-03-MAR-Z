<?php

namespace App\Actions\SupportRequests;

use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportRequestAssigned;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * HU05: asigna (T1) o reasigna (T7) una solicitud a un agente activo.
 * Registra quien y cuando, audita asignacion y estado con el mismo batch_id
 * y notifica al agente cuando el cambio ya esta guardado.
 */
class AssignSupportRequest
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, SupportRequest $request, User $agent): SupportRequest
    {
        Gate::forUser($user)->authorize('assign', $request);

        if (! User::query()->activeAgents()->whereKey($agent->getKey())->exists()) {
            throw new DomainException('Solo se puede asignar a un agente activo.');
        }

        $request = DB::transaction(function () use ($user, $request, $agent): SupportRequest {
            $request = SupportRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            // Se valida sobre el registro bloqueado: otro coordinador pudo cambiarlo.
            if (! $request->status->canTransitionTo(RequestStatus::Assigned, $user, $request)) {
                throw new DomainException('La solicitud no se puede asignar en su estado actual.');
            }

            // PD-23: se prioriza antes de asignar, para que la bandeja del agente se pueda ordenar.
            if ($request->priority === null) {
                throw new DomainException('Prioriza la solicitud antes de asignarla.');
            }

            if ($request->assigned_agent_id === $agent->getKey()) {
                throw new DomainException('La solicitud ya está asignada a ese agente.');
            }

            $previousAgent = $request->assigned_agent_id;
            $previousStatus = $request->status;

            $request->assigned_agent_id = $agent->getKey();
            $request->assigned_by_id = $user->getKey();
            $request->assigned_at = now();
            $request->status = RequestStatus::Assigned;
            $request->save();

            $batch = $this->audit->record(
                $request,
                AuditEvent::Assigned,
                ['assigned_agent_id' => [$previousAgent, $agent->getKey()]],
                actor: $user,
            );

            // Una reasignacion desde Asignada no cambia el estado y no se audita como tal.
            if ($previousStatus !== RequestStatus::Assigned) {
                $this->audit->record(
                    $request,
                    AuditEvent::StatusChanged,
                    ['status' => [$previousStatus, RequestStatus::Assigned]],
                    actor: $user,
                    batch: $batch,
                );
            }

            return $request;
        });

        // Fuera de la transaccion: no se notifica algo que luego se deshizo.
        $agent->notify(new SupportRequestAssigned($request));

        return $request;
    }
}
