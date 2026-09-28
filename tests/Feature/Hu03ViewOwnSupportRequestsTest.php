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
 * HU03: el solicitante lista solo sus solicitudes, puede abrir el detalle y
 * ve estado y ultima actualizacion.
 */
class Hu03ViewOwnSupportRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $otherRequester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->requester = $this->userWithRole('solicitante');
        $this->otherRequester = $this->userWithRole('solicitante');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_requester_lists_only_own_requests(): void
    {
        $own = SupportRequest::factory()->create(['requester_id' => $this->requester->id]);
        $foreign = SupportRequest::factory()->create(['requester_id' => $this->otherRequester->id]);

        $this->actingAs($this->requester);

        Livewire::test(ListSupportRequests::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_requester_cannot_open_a_foreign_request_by_url(): void
    {
        $foreign = SupportRequest::factory()->create(['requester_id' => $this->otherRequester->id]);

        $this->actingAs($this->requester)
            ->get("/admin/support-requests/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_detail_shows_status_and_last_update(): void
    {
        $own = SupportRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'title' => 'Sin acceso a la VPN',
        ]);

        $this->actingAs($this->requester)
            ->get("/admin/support-requests/{$own->id}")
            ->assertOk()
            ->assertSee('Sin acceso a la VPN')
            ->assertSee('Estado')
            ->assertSee('Nuevo')
            ->assertSee('Última actualización');
    }

    public function test_search_does_not_escape_the_visibility_scope(): void
    {
        $own = SupportRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'title' => 'Impresora atascada',
        ]);
        $foreign = SupportRequest::factory()->create([
            'requester_id' => $this->otherRequester->id,
            'title' => 'Impresora sin tóner',
        ]);

        $this->actingAs($this->requester);

        Livewire::test(ListSupportRequests::class)
            ->searchTable('Impresora')
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_filters_do_not_escape_the_visibility_scope(): void
    {
        $category = Category::factory()->create();
        $own = SupportRequest::factory()->create(['requester_id' => $this->requester->id, 'category_id' => $category->id]);
        $foreign = SupportRequest::factory()->create(['requester_id' => $this->otherRequester->id, 'category_id' => $category->id]);

        $this->actingAs($this->requester);

        Livewire::test(ListSupportRequests::class)
            ->filterTable('priority', ['none'])
            ->filterTable('category_id', [$category->id])
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_coordinator_sees_all_requests(): void
    {
        $requests = SupportRequest::factory()->count(3)->create();

        $this->actingAs($this->userWithRole('coordinador'));

        Livewire::test(ListSupportRequests::class)
            ->assertCanSeeTableRecords($requests);
    }

    public function test_agent_and_auditor_see_no_requests_until_assignment_exists(): void
    {
        $requests = SupportRequest::factory()->count(2)->create();

        foreach (['agente', 'auditor'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertSame(0, SupportRequest::query()->visibleTo($user)->count(), $role);
            $this->assertFalse($user->can('view', $requests->first()), $role);
        }
    }

    public function test_list_can_be_filtered_by_status_priority_and_category(): void
    {
        $category = Category::factory()->create();
        $match = SupportRequest::factory()->create([
            'category_id' => $category->id,
            'status' => RequestStatus::Nuevo,
            'priority' => RequestPriority::Alta,
        ]);
        $otherPriority = SupportRequest::factory()->create([
            'category_id' => $category->id,
            'priority' => RequestPriority::Baja,
        ]);
        $otherCategory = SupportRequest::factory()->create(['priority' => RequestPriority::Alta]);

        $this->actingAs($this->userWithRole('coordinador'));

        Livewire::test(ListSupportRequests::class)
            ->filterTable('status', [RequestStatus::Nuevo->value])
            ->filterTable('priority', [RequestPriority::Alta->value])
            ->filterTable('category_id', [$category->id])
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$otherPriority, $otherCategory]);
    }
}
