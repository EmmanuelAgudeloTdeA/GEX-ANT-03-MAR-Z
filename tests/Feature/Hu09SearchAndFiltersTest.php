<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU09: buscar y filtrar solicitudes para localizar informacion autorizada.
 *
 * Los filtros de estado, prioridad y categoria ya venian de HU03, y la busqueda
 * en titulo y descripcion la agrego el grupo en HU08 (plan tecnico PD-14). Aqui se
 * cubren las partes que faltaban: el rango de fechas de creacion, la combinacion
 * de los cuatro filtros entre si, y que filtros y busqueda se puedan reabrir.
 */
class Hu09SearchAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    private User $requester;

    private User $otherRequester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->coordinator = $this->userWithRole('coordinador');
        $this->requester = $this->userWithRole('solicitante');
        $this->otherRequester = $this->userWithRole('solicitante');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_search_finds_a_request_by_its_description(): void
    {
        $match = SupportRequest::factory()->create([
            'title' => 'Solicitud sin etiqueta',
            'description' => 'Falta el inventario de repuestos del turno nocturno.',
        ]);
        $other = SupportRequest::factory()->create([
            'title' => 'Puerta sin llave',
            'description' => 'La puerta del deposito no abre con la tarjeta.',
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->searchTable('inventario')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_by_description_does_not_escape_the_visibility_scope(): void
    {
        $own = SupportRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'title' => 'Solicitud sin etiqueta',
            'description' => 'Falta el inventario de repuestos.',
        ]);
        $foreign = SupportRequest::factory()->create([
            'requester_id' => $this->otherRequester->id,
            'title' => 'Solicitud sin etiqueta',
            'description' => 'Falta el inventario de uniformes.',
        ]);

        $this->actingAs($this->requester);

        Livewire::test(ListSupportRequests::class)
            ->searchTable('inventario')
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_search_matches_the_title_or_the_description(): void
    {
        $byTitle = SupportRequest::factory()->create([
            'title' => 'Impresora atascada',
            'description' => 'Sin detalles.',
        ]);
        $byDescription = SupportRequest::factory()->create([
            'title' => 'Fallo de red',
            'description' => 'La impresora no responde al imprimir.',
        ]);
        $unrelated = SupportRequest::factory()->create([
            'title' => 'Puerta sin llave',
            'description' => 'La puerta del deposito no abre.',
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->searchTable('impresora')
            ->assertCanSeeTableRecords([$byTitle, $byDescription])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_created_at_filter_returns_only_the_selected_range(): void
    {
        $recent = SupportRequest::factory()->create([
            'title' => 'Dentro del rango',
            'created_at' => now()->subDays(2),
        ]);
        $old = SupportRequest::factory()->create([
            'title' => 'Fuera del rango',
            'created_at' => now()->subDays(40),
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('created_at', [
                'from' => now()->subDays(10)->toDateString(),
                'until' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_created_at_filter_until_includes_the_whole_day(): void
    {
        $lastNight = SupportRequest::factory()->create([
            'title' => 'Creada de noche',
            'created_at' => now()->setTime(23, 50)->subDays(3),
        ]);
        $nextDay = SupportRequest::factory()->create([
            'title' => 'Creada al dia siguiente',
            'created_at' => now()->setTime(0, 10)->subDays(2),
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('created_at', ['until' => now()->subDays(3)->toDateString()])
            ->assertCanSeeTableRecords([$lastNight])
            ->assertCanNotSeeTableRecords([$nextDay]);
    }

    public function test_created_at_filter_does_not_escape_the_visibility_scope(): void
    {
        $own = SupportRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'title' => 'Mi solicitud',
            'created_at' => now(),
        ]);
        $foreign = SupportRequest::factory()->create([
            'requester_id' => $this->otherRequester->id,
            'title' => 'Su solicitud',
            'created_at' => now(),
        ]);

        $this->actingAs($this->requester);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('created_at', [
                'from' => now()->subDay()->toDateString(),
                'until' => now()->addDay()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_status_priority_category_and_dates_combine_as_an_intersection(): void
    {
        $category = Category::factory()->create();

        $match = SupportRequest::factory()->create([
            'category_id' => $category->id,
            'status' => RequestStatus::New,
            'priority' => RequestPriority::High,
            'created_at' => now()->subDays(2),
        ]);
        $otherStatus = SupportRequest::factory()->create([
            'category_id' => $category->id,
            'status' => RequestStatus::Resolved,
            'priority' => RequestPriority::High,
            'created_at' => now()->subDays(2),
        ]);
        $otherDate = SupportRequest::factory()->create([
            'category_id' => $category->id,
            'status' => RequestStatus::New,
            'priority' => RequestPriority::High,
            'created_at' => now()->subDays(40),
        ]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('status', [RequestStatus::New->value])
            ->filterTable('priority', [RequestPriority::High->value])
            ->filterTable('category_id', [$category->id])
            ->filterTable('created_at', [
                'from' => now()->subDays(10)->toDateString(),
                'until' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$otherStatus, $otherDate]);
    }

    public function test_multiple_values_in_one_filter_combine_with_or(): void
    {
        $new = SupportRequest::factory()->create(['title' => 'Recien abierta', 'status' => RequestStatus::New]);
        $inProgress = SupportRequest::factory()->create(['title' => 'En proceso', 'status' => RequestStatus::InProgress]);
        $resolved = SupportRequest::factory()->create(['title' => 'Ya resuelta', 'status' => RequestStatus::Resolved]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('status', [RequestStatus::New->value, RequestStatus::InProgress->value])
            ->assertCanSeeTableRecords([$new, $inProgress])
            ->assertCanNotSeeTableRecords([$resolved]);
    }

    public function test_a_filtered_view_can_be_reopened_from_the_url(): void
    {
        $match = SupportRequest::factory()->create([
            'title' => 'Impresora atascada',
            'description' => 'Sin detalles.',
            'status' => RequestStatus::New,
        ]);
        $other = SupportRequest::factory()->create([
            'title' => 'Impresora del archivo',
            'description' => 'Sin detalles.',
            'status' => RequestStatus::Resolved,
        ]);

        $this->actingAs($this->coordinator)
            ->get('/admin/support-requests?tableSearch=impresora&filters[status][values][0]=new')
            ->assertOk()
            ->assertSee('Impresora atascada')
            ->assertDontSee('Impresora del archivo');
    }

    public function test_filters_are_remembered_in_the_session(): void
    {
        $new = SupportRequest::factory()->create(['title' => 'Recien abierta', 'status' => RequestStatus::New]);
        $resolved = SupportRequest::factory()->create(['title' => 'Ya resuelta', 'status' => RequestStatus::Resolved]);

        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('status', [RequestStatus::New->value]);

        // Segunda visita sin filtros en la URL: los sobreviven desde la sesion.
        Livewire::test(ListSupportRequests::class)
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$resolved]);
    }

    public function test_filters_are_shown_in_a_collapsible_panel_above_the_content(): void
    {
        $this->actingAs($this->coordinator);

        Livewire::test(ListSupportRequests::class)
            ->assertTableFilterExists('created_at')
            ->assertTableFilterVisible('created_at');

        // Filament solo imprime ese boton cuando el panel de filtros es plegable.
        $this->get('/admin/support-requests')
            ->assertOk()
            ->assertSee('fi-ta-filters-trigger-action-ctn', escape: false);
    }
}
