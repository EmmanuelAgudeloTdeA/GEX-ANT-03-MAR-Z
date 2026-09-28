<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Filament\Widgets\MyRequestsStats;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU03 (mejora UX): el escritorio del solicitante resume el estado de sus
 * solicitudes sin contar las ajenas.
 */
class Hu03MyRequestsStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ShieldSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_counts_only_own_requests_by_state(): void
    {
        $requester = $this->userWithRole('solicitante');
        $own = fn (RequestStatus $status, int $count = 1) => SupportRequest::factory()
            ->count($count)
            ->create(['requester_id' => $requester->id, 'status' => $status]);

        $own(RequestStatus::New, 2);
        $own(RequestStatus::InProgress);
        $own(RequestStatus::Resolved);
        $own(RequestStatus::Closed, 3);
        SupportRequest::factory()->count(5)->create(['status' => RequestStatus::New]);

        $this->actingAs($requester);

        $stats = collect((fn () => $this->getStats())->call(new MyRequestsStats))
            ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()]);

        $this->assertSame(['Abiertas' => 3, 'Por confirmar' => 1, 'Cerradas' => 3], $stats->all());
    }

    public function test_widget_renders_on_the_requester_dashboard(): void
    {
        $this->actingAs($this->userWithRole('solicitante'));

        Livewire::test(MyRequestsStats::class)
            ->assertOk()
            ->assertSee('Mis solicitudes')
            ->assertSee('Por confirmar');

        // Los widgets se cargan en diferido: el escritorio solo monta el componente.
        $this->get('/admin')->assertOk()->assertSeeLivewire(MyRequestsStats::class);
    }

    public function test_stats_link_to_the_filtered_list(): void
    {
        $this->actingAs($this->userWithRole('solicitante'));

        $urls = collect((fn () => $this->getStats())->call(new MyRequestsStats))
            ->map(fn ($stat): string => urldecode($stat->getUrl()));

        $this->assertStringContainsString('filters[status][values][0]=resolved', $urls[1]);
        $this->assertStringContainsString('filters[status][values][0]=closed', $urls[2]);
    }

    public function test_other_roles_do_not_see_the_widget(): void
    {
        foreach (['agente', 'coordinador', 'auditor', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(MyRequestsStats::canView(), $role);
        }
    }
}
