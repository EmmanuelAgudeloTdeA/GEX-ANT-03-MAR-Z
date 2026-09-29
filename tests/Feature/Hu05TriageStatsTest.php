<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Filament\Widgets\TriageStats;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU05 (mejora UX): el escritorio del coordinador resume lo que espera una
 * decision de priorizacion o asignacion.
 */
class Hu05TriageStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ShieldSeeder::class, RolePermissionSeeder::class]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return collect((fn () => $this->getStats())->call(new TriageStats))
            ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()])
            ->all();
    }

    public function test_counts_what_waits_for_a_coordination_decision(): void
    {
        SupportRequest::factory()->count(2)->create();
        SupportRequest::factory()->prioritized(RequestPriority::High)->create();
        SupportRequest::factory()->assignedTo()->create();
        SupportRequest::factory()->reopened()->create();
        SupportRequest::factory()->closed()->create();

        $this->actingAs(User::factory()->withRole('coordinador')->create());

        $this->assertSame(['Por priorizar' => 2, 'Por asignar' => 3, 'Reabiertas' => 1], $this->stats());
    }

    public function test_widget_renders_on_the_coordinator_dashboard(): void
    {
        $this->actingAs(User::factory()->withRole('coordinador')->create());

        Livewire::test(TriageStats::class)
            ->assertOk()
            ->assertSee('Triaje')
            ->assertSee('Por asignar');

        $this->get('/admin')->assertOk()->assertSeeLivewire(TriageStats::class);
    }

    public function test_stats_link_to_the_filtered_list(): void
    {
        $this->actingAs(User::factory()->withRole('coordinador')->create());

        $urls = collect((fn () => $this->getStats())->call(new TriageStats))
            ->map(fn ($stat): string => urldecode($stat->getUrl()));

        $this->assertStringContainsString('filters[priority][values][0]=none', $urls[0]);
        $this->assertStringContainsString('filters[status][values][0]=new', $urls[1]);
        $this->assertStringContainsString('filters[status][values][0]=reopened', $urls[2]);
    }

    public function test_only_coordinator_and_super_admin_see_the_widget(): void
    {
        foreach (['coordinador' => true, 'super_admin' => true, 'solicitante' => false, 'agente' => false, 'auditor' => false] as $role => $visible) {
            $this->actingAs(User::factory()->withRole($role)->create());

            $this->assertSame($visible, TriageStats::canView(), $role);
        }
    }
}
