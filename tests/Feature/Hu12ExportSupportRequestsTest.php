<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\RecordSupportRequestExport;
use App\Actions\SupportRequests\ReopenSupportRequest;
use App\Enums\AuditEvent;
use App\Enums\RequestPriority;
use App\Filament\Exports\SupportRequestExporter;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU12 + cambio controlado del Sprint 3: exporta CSV con filtros; excluye
 * credenciales y texto libre; registra la exportacion.
 */
class Hu12ExportSupportRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Storage::fake('local');

        $this->coordinator = User::factory()->withRole('coordinador')->create();
    }

    public function test_coordinator_exports_a_csv_with_the_fixed_columns(): void
    {
        $request = SupportRequest::factory()->prioritized(RequestPriority::High)->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $rows = $this->csvRows($this->lastExport());

        $this->assertSame(
            ['ID', 'Categoría', 'Estado', 'Prioridad', 'Creada', 'Asignada', 'Resuelta', 'Cerrada', 'Tiempo de ciclo (horas)', 'Reaperturas'],
            $rows[0],
        );
        $this->assertCount(2, $rows);
        $this->assertSame((string) $request->id, $rows[1][0]);
        $this->assertSame($request->category->name, $rows[1][1]);
        $this->assertSame('Nuevo', $rows[1][2]);
        $this->assertSame('Alta', $rows[1][3]);
    }

    public function test_csv_excludes_credentials_free_text_and_people(): void
    {
        $agent = User::factory()->withRole('agente')->create(['name' => 'Agente Visible']);
        $request = SupportRequest::factory()->inProgress($agent)->create([
            'title' => 'Titulo que no debe salir',
            'description' => 'Descripcion que no debe salir',
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)->callAction('export');

        $csv = $this->csvContent($this->lastExport());

        $this->assertStringNotContainsString('Titulo que no debe salir', $csv);
        $this->assertStringNotContainsString('Descripcion que no debe salir', $csv);
        $this->assertStringNotContainsString('Agente Visible', $csv);
        $this->assertStringNotContainsString($agent->email, $csv);
        $this->assertStringNotContainsString($request->requester->name, $csv);
        $this->assertStringNotContainsString($request->requester->email, $csv);
        $this->assertStringNotContainsString($agent->password, $csv);
    }

    public function test_export_respects_active_filters(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $expected = SupportRequest::factory()->prioritized(RequestPriority::High)->create(['category_id' => $categoryA->id]);
        SupportRequest::factory()->prioritized(RequestPriority::Low)->create(['category_id' => $categoryA->id]);
        SupportRequest::factory()->prioritized(RequestPriority::High)->create(['category_id' => $categoryB->id]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('priority', [(string) RequestPriority::High->value])
            ->filterTable('category_id', [$categoryA->id])
            ->callAction('export');

        $rows = $this->csvRows($this->lastExport());

        $this->assertSame([(string) $expected->id], array_column(array_slice($rows, 1), 0));
    }

    public function test_export_respects_the_text_search(): void
    {
        $expected = SupportRequest::factory()->create(['title' => 'Impresora sin toner']);
        SupportRequest::factory()->create(['title' => 'Correo caido', 'description' => 'No llegan correos']);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->searchTable('toner')
            ->callAction('export');

        $rows = $this->csvRows($this->lastExport());

        $this->assertSame([(string) $expected->id], array_column(array_slice($rows, 1), 0));
    }

    public function test_export_is_recorded_in_the_audit_log(): void
    {
        SupportRequest::factory()->count(2)->prioritized(RequestPriority::High)->create();
        SupportRequest::factory()->prioritized(RequestPriority::Low)->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('priority', [(string) RequestPriority::High->value])
            ->searchTable('')
            ->callAction('export');

        $export = $this->lastExport();
        $log = AuditLog::query()->where('event', AuditEvent::Exported)->sole();

        $this->assertNull($log->support_request_id);
        $this->assertSame($this->coordinator->id, $log->actor_id);
        $this->assertSame('coordinador', $log->actor_role);
        $this->assertSame($export->id, $log->metadata['export_id']);
        $this->assertSame(2, $log->metadata['rows']);
        $this->assertSame('csv', $log->metadata['format']);
        $this->assertSame(['values' => [(string) RequestPriority::High->value]], $log->metadata['filters']['priority']);
        $this->assertArrayNotHasKey('status', $log->metadata['filters']);
        $this->assertNull($log->metadata['search']);
    }

    public function test_cycle_time_and_reopenings_are_exported(): void
    {
        $this->travelTo(now()->subHours(30));
        $closed = SupportRequest::factory()->closed()->create();
        $this->travelBack();
        $closed->forceFill(['closed_at' => $closed->created_at->copy()->addHours(30)])->save();

        $requester = User::factory()->withRole('solicitante')->create();
        $reopened = SupportRequest::factory()->resolved()->create(['requester_id' => $requester->id]);
        app(ReopenSupportRequest::class)->handle($requester, $reopened, 'La impresora sigue fallando');

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)->callAction('export');

        $rows = collect(array_slice($this->csvRows($this->lastExport()), 1))->keyBy(0);

        $this->assertSame('30', $rows[(string) $closed->id][8]);
        $this->assertSame('0', $rows[(string) $closed->id][9]);
        $this->assertSame('', $rows[(string) $reopened->id][8]);
        $this->assertSame('1', $rows[(string) $reopened->id][9]);
        $this->assertStringNotContainsString('La impresora sigue fallando', $this->csvContent($this->lastExport()));
    }

    public function test_only_the_coordinator_sees_the_export_action(): void
    {
        foreach (['solicitante', 'agente'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());

            Livewire::test(ListSupportRequests::class)->assertActionHidden('export');
        }

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)->assertActionVisible('export');
    }

    public function test_service_rejects_users_without_the_export_permission(): void
    {
        $agent = User::factory()->withRole('agente')->create();
        $export = Export::forceCreate([
            'file_disk' => 'local',
            'exporter' => SupportRequestExporter::class,
            'total_rows' => 0,
            'user_id' => $agent->id,
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            app(RecordSupportRequestExport::class)->handle($agent, $export);
        } finally {
            $this->assertDatabaseMissing('audit_logs', ['event' => AuditEvent::Exported->value]);
        }
    }

    public function test_only_the_author_can_download_the_export(): void
    {
        SupportRequest::factory()->create();

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)->callAction('export');

        $url = route('filament.exports.download', ['export' => $this->lastExport(), 'format' => 'csv']);

        $this->get($url)->assertOk();

        $this->actingAs(User::factory()->withRole('coordinador')->create())
            ->get($url)
            ->assertForbidden();
    }

    public function test_requester_export_scope_never_exceeds_visible_requests(): void
    {
        // Defensa extra: aun si un rol con permiso exporta, la query base
        // limita las filas a lo que ese usuario puede ver.
        $requester = User::factory()->withRole('solicitante')->create();
        $requester->givePermissionTo('Export:SupportRequest');

        $own = SupportRequest::factory()->create(['requester_id' => $requester->id]);
        SupportRequest::factory()->create();

        $this->actingAs($requester);

        Livewire::test(ListSupportRequests::class)->callAction('export');

        $rows = $this->csvRows($this->lastExport());

        $this->assertSame([(string) $own->id], array_column(array_slice($rows, 1), 0));
    }

    private function lastExport(): Export
    {
        return Export::query()->latest('id')->firstOrFail();
    }

    /**
     * Filament guarda la cabecera y cada bloque de filas en archivos separados.
     */
    private function csvContent(Export $export): string
    {
        $disk = Storage::disk('local');
        $directory = $export->getFileDirectory();

        $chunks = collect($disk->files($directory))
            ->reject(fn (string $file): bool => str_ends_with($file, 'headers.csv'))
            ->sort()
            ->map(fn (string $file): string => $disk->get($file));

        return $disk->get($directory.'/headers.csv').$chunks->implode('');
    }

    /**
     * @return list<list<string>>
     */
    private function csvRows(Export $export): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $this->csvContent($export));

        return collect(preg_split('/\r?\n/', trim($content)))
            ->map(fn (string $line): array => str_getcsv($line, escape: ''))
            ->all();
    }
}
