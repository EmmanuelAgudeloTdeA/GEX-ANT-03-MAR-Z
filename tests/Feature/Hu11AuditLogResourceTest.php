<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU11: el auditor consulta el historial en modo lectura. Cubre que el
 * Resource tenga sus paginas y que muestre eventos sin solicitud (HU12).
 */
class Hu11AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->auditor = User::factory()->withRole('auditor')->create();
    }

    public function test_auditor_opens_the_audit_log_list_and_detail(): void
    {
        $request = SupportRequest::factory()->create();
        $log = AuditLog::query()->findOrFail($this->recordEvent(AuditEvent::Created, $request));

        $this->actingAs($this->auditor)
            ->get(AuditLogResource::getUrl('index'))
            ->assertOk();

        Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords([$log]);

        Livewire::test(ViewAuditLog::class, ['record' => $log->getKey()])->assertOk();
    }

    public function test_auditor_sees_export_events_without_a_support_request(): void
    {
        $coordinator = User::factory()->withRole('coordinador')->create();
        $log = AuditLog::query()->findOrFail($this->recordEvent(AuditEvent::Exported, actor: $coordinator));

        $this->actingAs($this->auditor);

        Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords([$log]);

        Livewire::test(ViewAuditLog::class, ['record' => $log->getKey()])->assertOk();
    }

    public function test_other_roles_cannot_open_the_global_audit_log(): void
    {
        foreach (['solicitante', 'agente', 'coordinador'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->get(AuditLogResource::getUrl('index'))
                ->assertForbidden();
        }
    }

    public function test_audit_log_resource_has_no_write_pages(): void
    {
        $this->assertSame(['index', 'view'], array_keys(AuditLogResource::getPages()));
    }

    private function recordEvent(AuditEvent $event, ?SupportRequest $request = null, ?User $actor = null): int
    {
        $batch = app(AuditLogger::class)->record(
            $request,
            $event,
            metadata: $event === AuditEvent::Exported ? ['rows' => 1, 'format' => 'csv'] : [],
            actor: $actor ?? $this->auditor,
        );

        return AuditLog::query()->where('batch_id', $batch)->value('id');
    }
}
