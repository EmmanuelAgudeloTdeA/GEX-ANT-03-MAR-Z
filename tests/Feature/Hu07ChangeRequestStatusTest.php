<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\ChangeRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Hu07ChangeRequestStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->agent = User::factory()->withRole('agente')->create();
    }

    public function test_assigned_agent_changes_status_and_the_change_is_audited(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();

        $this->actingAs($this->agent);

        Livewire::test(ListSupportRequests::class)
            ->callTableAction('changeStatus', $request, data: ['status' => RequestStatus::InProgress->value])
            ->assertHasNoTableActionErrors();

        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);

        $log = AuditLog::sole();
        $this->assertSame(AuditEvent::StatusChanged, $log->event);
        $this->assertSame('status', $log->field);
        $this->assertSame(['assigned', 'in_progress'], [$log->old_value, $log->new_value]);
        $this->assertSame($this->agent->id, $log->actor_id);
    }

    public function test_invalid_status_is_rejected_without_changing_or_auditing_the_request(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();

        $this->actingAs($this->agent);

        Livewire::test(ListSupportRequests::class)
            ->callTableAction('changeStatus', $request, data: ['status' => 'not-a-status'])
            ->assertHasTableActionErrors(['status']);

        $this->assertSame(RequestStatus::Assigned, $request->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_without_the_permission_cannot_change_status(): void
    {
        $request = SupportRequest::factory()->assignedTo($this->agent)->create();
        $coordinator = User::factory()->withRole('coordinador')->create();

        $this->assertFalse($coordinator->can('changeStatus', $request));

        try {
            app(ChangeRequestStatus::class)->handle($coordinator, $request, RequestStatus::InProgress);
            $this->fail('Un coordinador pudo cambiar el estado sin el permiso correspondiente.');
        } catch (AuthorizationException) {
        }

        $this->assertSame(RequestStatus::Assigned, $request->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
