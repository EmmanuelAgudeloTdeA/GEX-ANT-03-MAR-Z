<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\PrioritizeSupportRequest;
use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU04: prioridad valida, cambio trazable, lista ordenable por prioridad,
 * estado y fecha; solo el coordinador modifica.
 */
class Hu04PrioritizeSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->coordinator = $this->userWithRole('coordinador');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_new_requests_start_without_priority(): void
    {
        $this->assertNull(SupportRequest::factory()->create()->fresh()->priority);
    }

    public function test_coordinator_prioritizes_from_the_detail_and_the_change_is_audited(): void
    {
        $request = SupportRequest::factory()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('prioritize', data: ['priority' => RequestPriority::High->value])
            ->assertHasNoActionErrors();

        $this->assertSame(RequestPriority::High, $request->fresh()->priority);

        $log = AuditLog::sole();
        $this->assertSame(AuditEvent::PriorityChanged, $log->event);
        $this->assertSame($request->id, $log->support_request_id);
        $this->assertSame($this->coordinator->id, $log->actor_id);
        $this->assertSame('coordinador', $log->actor_role);
        $this->assertSame('priority', $log->field);
        $this->assertNull($log->old_value);
        $this->assertSame('3', $log->new_value);
    }

    public function test_coordinator_prioritizes_from_the_table_row(): void
    {
        $request = SupportRequest::factory()->create(['priority' => RequestPriority::Low]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->callTableAction('prioritize', $request, data: ['priority' => RequestPriority::Medium->value])
            ->assertHasNoTableActionErrors();

        $this->assertSame(RequestPriority::Medium, $request->fresh()->priority);
        $this->assertSame(['1', '2'], [AuditLog::sole()->old_value, AuditLog::sole()->new_value]);
    }

    public function test_priority_is_required_and_must_be_valid(): void
    {
        $request = SupportRequest::factory()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('prioritize', data: ['priority' => null])
            ->assertHasActionErrors(['priority' => 'required']);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('prioritize', data: ['priority' => 9])
            ->assertHasActionErrors(['priority']);

        $this->assertNull($request->fresh()->priority);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_same_priority_is_not_audited(): void
    {
        $request = SupportRequest::factory()->create(['priority' => RequestPriority::Medium]);

        app(PrioritizeSupportRequest::class)->handle($this->coordinator, $request, RequestPriority::Medium);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_the_coordinator_can_prioritize(): void
    {
        $request = SupportRequest::factory()->create();

        foreach (['solicitante', 'agente', 'auditor'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertFalse($user->can('prioritize', $request), $role);

            try {
                app(PrioritizeSupportRequest::class)->handle($user, $request, RequestPriority::High);
                $this->fail("El rol {$role} pudo priorizar.");
            } catch (AuthorizationException) {
            }
        }

        $this->assertNull($request->fresh()->priority);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_requester_does_not_see_the_prioritize_action(): void
    {
        $requester = $this->userWithRole('solicitante');
        $request = SupportRequest::factory()->create(['requester_id' => $requester->id]);

        $this->actingAs($requester);

        Livewire::test(ViewSupportRequest::class, ['record' => $request->getRouteKey()])
            ->assertActionHidden('prioritize');
    }

    public function test_closed_requests_cannot_be_prioritized(): void
    {
        $request = SupportRequest::factory()->create(['status' => RequestStatus::Closed]);

        $this->assertFalse($this->coordinator->can('prioritize', $request));

        $this->expectException(AuthorizationException::class);
        app(PrioritizeSupportRequest::class)->handle($this->coordinator, $request, RequestPriority::High);
    }

    public function test_list_sorts_by_priority_status_and_date(): void
    {
        $low = SupportRequest::factory()->create([
            'priority' => RequestPriority::Low,
            'status' => RequestStatus::Closed,
            'created_at' => now()->subDays(2),
        ]);
        $high = SupportRequest::factory()->create([
            'priority' => RequestPriority::High,
            'status' => RequestStatus::New,
            'created_at' => now()->subDay(),
        ]);
        $medium = SupportRequest::factory()->create([
            'priority' => RequestPriority::Medium,
            'status' => RequestStatus::InProgress,
            'created_at' => now(),
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->sortTable('priority', 'desc')
            ->assertCanSeeTableRecords([$high, $medium, $low], inOrder: true)
            ->sortTable('status')
            ->assertCanSeeTableRecords([$high, $medium, $low], inOrder: true)
            ->sortTable('created_at', 'desc')
            ->assertCanSeeTableRecords([$medium, $high, $low], inOrder: true);
    }
}
