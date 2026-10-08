<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Resources\SupportRequests\RelationManagers\HistoryRelationManager;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Audit\AuditChangeMapper;
use App\Support\Audit\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU11: el historial de auditoria muestra etiquetas legibles en vez de los
 * nombres tecnicos de las columnas, sin alterar los datos guardados ni los
 * eventos historicos.
 */
class Hu11AuditChangeMapperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // ---- Mapper: nombres de campo ----

    public function test_known_fields_get_human_readable_labels(): void
    {
        $this->assertSame('Estado', AuditChangeMapper::fieldLabel('status'));
        $this->assertSame('Prioridad', AuditChangeMapper::fieldLabel('priority'));
        $this->assertSame('Agente asignado', AuditChangeMapper::fieldLabel('assigned_agent_id'));
        $this->assertSame('Fecha de resolución', AuditChangeMapper::fieldLabel('resolved_at'));
        $this->assertSame('Fecha de cierre', AuditChangeMapper::fieldLabel('closed_at'));
    }

    public function test_unknown_field_falls_back_without_inventing_a_label(): void
    {
        $label = AuditChangeMapper::fieldLabel('unexpected_column');

        $this->assertNotNull($label);
        $this->assertStringNotContainsString('_', $label);
        $this->assertSame('Unexpected Column', $label);
    }

    public function test_null_field_and_null_value_stay_null(): void
    {
        $this->assertNull(AuditChangeMapper::fieldLabel(null));
        $this->assertNull(AuditChangeMapper::fieldLabel(''));
        $this->assertNull(AuditChangeMapper::value('status', null));
    }

    // ---- Mapper: valores ----

    public function test_enum_values_are_translated_to_labels(): void
    {
        $this->assertSame('Baja', AuditChangeMapper::value('priority', '1'));
        $this->assertSame('Media', AuditChangeMapper::value('priority', '2'));
        $this->assertSame('Alta', AuditChangeMapper::value('priority', '3'));

        $this->assertSame('Nuevo', AuditChangeMapper::value('status', 'new'));
        $this->assertSame('En progreso', AuditChangeMapper::value('status', 'in_progress'));
        $this->assertSame('Reabierta', AuditChangeMapper::value('status', 'reopened'));
    }

    public function test_assigned_agent_is_shown_as_the_agent_code(): void
    {
        $agent = User::factory()->withRole('agente')->create();

        $this->assertSame($agent->code, AuditChangeMapper::value('assigned_agent_id', (string) $agent->id));
    }

    public function test_values_outside_the_catalog_are_kept_untouched(): void
    {
        $this->assertSame('legacy', AuditChangeMapper::value('status', 'legacy'));
        $this->assertSame('99', AuditChangeMapper::value('priority', '99'));
        $this->assertSame('999999', AuditChangeMapper::value('assigned_agent_id', '999999'));
        $this->assertSame('valor', AuditChangeMapper::value('unexpected_column', 'valor'));
    }

    // ---- Integracion con las vistas ----

    public function test_history_relation_manager_shows_labels_and_keeps_actor_and_date(): void
    {
        $coordinator = User::factory()->withRole('coordinador')->create();
        $agent = User::factory()->withRole('agente')->create();
        $request = SupportRequest::factory()->create();

        $batch = app(AuditLogger::class)->record(
            $request,
            AuditEvent::StatusChanged,
            [
                'status' => [RequestStatus::New, RequestStatus::InProgress],
                'assigned_agent_id' => [null, $agent->id],
            ],
            actor: $coordinator,
        );

        $logs = AuditLog::query()->where('batch_id', $batch)->get();
        $this->assertCount(2, $logs);

        $this->actingAs($coordinator);

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => ViewSupportRequest::class,
        ])
            ->assertCanSeeTableRecords($logs)
            ->assertSee('Estado')
            ->assertSee('Agente asignado')
            ->assertSee('Nuevo')
            ->assertSee('En progreso')
            ->assertSee($agent->code)
            ->assertSee($coordinator->code)
            ->assertDontSeeText('assigned_agent_id');
    }

    public function test_audit_log_resource_shows_labels_instead_of_technical_names(): void
    {
        $auditor = User::factory()->withRole('auditor')->create();
        $request = SupportRequest::factory()->create();

        $batch = app(AuditLogger::class)->record(
            $request,
            AuditEvent::PriorityChanged,
            ['priority' => [RequestPriority::Low, RequestPriority::High]],
            actor: $auditor,
        );

        $log = AuditLog::query()->where('batch_id', $batch)->sole();

        $this->actingAs($auditor);

        Livewire::test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('Prioridad')
            ->assertSee('Baja')
            ->assertSee('Alta')
            // Se comprueba en el texto: los <option> del filtro de eventos
            // contienen el nombre tecnico del evento (priority_changed).
            ->assertDontSeeText('priority');
    }

    public function test_history_keeps_rendering_when_a_field_is_not_mapped(): void
    {
        $auditor = User::factory()->withRole('auditor')->create();
        $request = SupportRequest::factory()->create();

        $batch = app(AuditLogger::class)->record(
            $request,
            AuditEvent::StatusChanged,
            ['legacy_column' => ['anterior', 'nuevo']],
            actor: $auditor,
        );

        $log = AuditLog::query()->where('batch_id', $batch)->sole();

        $this->actingAs($auditor);

        Livewire::test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('Legacy Column')
            ->assertSee('anterior')
            ->assertSee('nuevo')
            ->assertDontSeeText('legacy_column');
    }
}
