<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\AssignSupportRequest;
use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportRequestAssigned;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU05: asigna a agente activo; registra quien/cuando; notifica en la
 * aplicacion; evita asignacion invalida.
 */
class Hu05AssignSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->coordinator = User::factory()->withRole('coordinador')->create();
        $this->agent = User::factory()->withRole('agente')->create();
    }

    private function assign(SupportRequest $request, User $agent, ?User $by = null): SupportRequest
    {
        return app(AssignSupportRequest::class)->handle($by ?? $this->coordinator, $request, $agent);
    }

    public function test_coordinator_assigns_from_the_detail_and_who_and_when_are_recorded(): void
    {
        $this->freezeSecond();
        $request = SupportRequest::factory()->prioritized()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('assign', data: ['assigned_agent_id' => $this->agent->id])
            ->assertHasNoActionErrors();

        $request->refresh();
        $this->assertSame(RequestStatus::Assigned, $request->status);
        $this->assertSame($this->agent->id, $request->assigned_agent_id);
        $this->assertSame($this->coordinator->id, $request->assigned_by_id);
        $this->assertTrue($request->assigned_at->equalTo(now()));
    }

    public function test_assignment_and_status_change_are_audited_in_the_same_batch(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        $this->assign($request, $this->agent);

        $logs = AuditLog::orderBy('id')->get();
        $this->assertCount(2, $logs);

        [$assigned, $status] = $logs;
        $this->assertSame(AuditEvent::Assigned, $assigned->event);
        $this->assertSame('assigned_agent_id', $assigned->field);
        $this->assertNull($assigned->old_value);
        $this->assertSame((string) $this->agent->id, $assigned->new_value);
        $this->assertSame($this->coordinator->id, $assigned->actor_id);
        $this->assertSame('coordinador', $assigned->actor_role);

        $this->assertSame(AuditEvent::StatusChanged, $status->event);
        $this->assertSame(['new', 'assigned'], [$status->old_value, $status->new_value]);
        $this->assertSame($assigned->batch_id, $status->batch_id);
    }

    public function test_coordinator_assigns_from_the_table_row(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->callTableAction('assign', $request, data: ['assigned_agent_id' => $this->agent->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($this->agent->id, $request->fresh()->assigned_agent_id);
    }

    public function test_the_agent_is_notified_in_the_application(): void
    {
        $request = SupportRequest::factory()->prioritized(RequestPriority::High)->create();

        $this->assign($request, $this->agent);

        $notification = $this->agent->notifications()->sole();
        $this->assertSame(SupportRequestAssigned::class, $notification->type);
        $this->assertSame("Se te asignó la solicitud #{$request->id}", $notification->data['title']);
        $this->assertStringContainsString('Prioridad Alta', $notification->data['body']);
        $this->assertStringNotContainsString($request->description, json_encode($notification->data));
        $this->assertSame(0, $this->coordinator->notifications()->count());
    }

    public function test_reassignment_from_in_progress_returns_to_assigned_and_notifies_the_new_agent(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();
        $newAgent = User::factory()->withRole('agente')->create();

        $this->assign($request, $newAgent);

        $request->refresh();
        $this->assertSame(RequestStatus::Assigned, $request->status);
        $this->assertSame($newAgent->id, $request->assigned_agent_id);

        $assigned = AuditLog::where('event', AuditEvent::Assigned)->sole();
        $this->assertSame([(string) $this->agent->id, (string) $newAgent->id], [$assigned->old_value, $assigned->new_value]);
        $this->assertSame(['in_progress', 'assigned'], [
            AuditLog::where('event', AuditEvent::StatusChanged)->sole()->old_value,
            AuditLog::where('event', AuditEvent::StatusChanged)->sole()->new_value,
        ]);

        $this->assertSame(1, $newAgent->notifications()->count());
    }

    public function test_reassignment_from_assigned_does_not_audit_a_status_change(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();

        $this->assign($request, User::factory()->withRole('agente')->create());

        $this->assertSame([AuditEvent::Assigned], AuditLog::pluck('event')->all());
    }

    public function test_invalid_agents_are_rejected_without_changes(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        $invalid = [
            'inactivo' => User::factory()->inactive()->withRole('agente')->create(),
            'coordinador' => User::factory()->withRole('coordinador')->create(),
            'solicitante' => User::factory()->withRole('solicitante')->create(),
            'sin rol' => User::factory()->create(),
        ];

        Notification::fake();

        foreach ($invalid as $case => $user) {
            try {
                $this->assign($request, $user);
                $this->fail("Se asignó a un usuario {$case}.");
            } catch (DomainException) {
            }
        }

        $this->assertNull($request->fresh()->assigned_agent_id);
        $this->assertSame(RequestStatus::New, $request->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        Notification::assertNothingSent();
    }

    public function test_the_form_only_offers_active_agents_other_than_the_current_one(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();
        $other = User::factory()->withRole('agente')->create();
        $inactive = User::factory()->inactive()->withRole('agente')->create();

        $this->actingAs($this->coordinator);

        foreach ([$this->agent, $inactive, $this->coordinator] as $notOffered) {
            Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
                ->callAction('assign', data: ['assigned_agent_id' => $notOffered->id])
                ->assertHasActionErrors(['assigned_agent_id']);
        }

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('assign', data: ['assigned_agent_id' => $other->id])
            ->assertHasNoActionErrors();

        $this->assertSame($other->id, $request->fresh()->assigned_agent_id);
    }

    public function test_priority_is_required_before_assigning(): void
    {
        $request = SupportRequest::factory()->create();

        $this->expectExceptionObject(new DomainException('Prioriza la solicitud antes de asignarla.'));

        $this->assign($request, $this->agent);
    }

    public function test_the_action_shows_the_business_error_and_keeps_the_request_unchanged(): void
    {
        $request = SupportRequest::factory()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('assign', data: ['assigned_agent_id' => $this->agent->id])
            ->assertNotified('No se pudo asignar la solicitud');

        $this->assertNull($request->fresh()->assigned_agent_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assigning_to_the_same_agent_is_rejected(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();

        $this->expectException(DomainException::class);

        $this->assign($request, $this->agent);
    }

    public function test_only_the_coordinator_can_assign(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        foreach (['solicitante', 'agente', 'auditor'] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->assertFalse($user->can('assign', $request), $role);

            try {
                $this->assign($request, $this->agent, by: $user);
                $this->fail("El rol {$role} pudo asignar.");
            } catch (AuthorizationException) {
            }
        }

        $this->assertNull($request->fresh()->assigned_agent_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_requester_does_not_see_the_assign_action(): void
    {
        $requester = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->prioritized()->create(['requester_id' => $requester->id]);

        $this->actingAs($requester);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->assertActionHidden('assign');
    }

    public function test_resolved_and_closed_requests_cannot_be_assigned(): void
    {
        foreach ([SupportRequest::factory()->resolved()->create(), SupportRequest::factory()->closed()->create()] as $request) {
            $this->assertFalse($this->coordinator->can('assign', $request), $request->status->value);
        }
    }

    public function test_the_agent_only_sees_the_requests_assigned_to_them(): void
    {
        $mine = SupportRequest::factory()->assignedTo($this->agent)->create();
        $unassigned = SupportRequest::factory()->create();
        $others = SupportRequest::factory()->assignedTo()->create();

        $this->actingAs($this->agent);

        Livewire::test(ListSupportRequests::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$unassigned, $others]);

        $this->get(ViewSupportRequest::getUrl(['record' => $mine]))->assertOk();
        // Igual que en HU03: la query restringida ni siquiera encuentra la ajena.
        $this->get(ViewSupportRequest::getUrl(['record' => $others]))->assertNotFound();
    }

    public function test_the_agent_gains_access_once_assigned(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        $this->assertFalse($this->agent->can('view', $request));

        $this->assign($request, $this->agent);

        $this->assertTrue($this->agent->can('view', $request->fresh()));
    }

    public function test_assigned_agent_column_is_only_shown_to_coordination(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();

        $this->actingAs($this->coordinator);
        Livewire::test(ListSupportRequests::class)
            ->assertTableColumnVisible('assignedAgent.name')
            ->assertTableColumnStateSet('assignedAgent.name', $this->agent->name, $request);

        $this->actingAs($this->agent);
        Livewire::test(ListSupportRequests::class)
            ->assertTableColumnHidden('assignedAgent.name');
    }
}
