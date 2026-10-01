<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Filament\Widgets\CoordinatorIndicators;
use App\Filament\Widgets\TriageStats;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Hu13CoordinatorIndicatorsTest extends TestCase
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

    public function test_coordinator_can_see_aggregated_request_indicators(): void
    {
        SupportRequest::factory()->create([
            'status' => RequestStatus::New,
            'priority' => RequestPriority::High,
        ]);
        SupportRequest::factory()->create([
            'status' => RequestStatus::InProgress,
            'priority' => RequestPriority::Low,
        ]);
        SupportRequest::factory()->create([
            'status' => RequestStatus::Resolved,
            'priority' => null,
        ]);

        $this->actingAs($this->userWithRole('coordinador'));

        $this->assertTrue(CoordinatorIndicators::canView());
        $section = (new CoordinatorIndicators)->getSectionContentComponent();
        $this->assertTrue($section->isCollapsible());
        $this->assertTrue($section->isCollapsed());
        $this->assertLessThan(TriageStats::getSort(), CoordinatorIndicators::getSort());

        $stats = collect((fn () => $this->getStats())->call(new CoordinatorIndicators))
            ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()]);

        $this->assertSame(3, $stats['Total de solicitudes']);
        $this->assertSame(1, $stats['Nuevo']);
        $this->assertSame(1, $stats['En progreso']);
        $this->assertSame(1, $stats['Resuelta']);
        $this->assertSame(1, $stats['Alta']);
        $this->assertSame(1, $stats['Baja']);
        $this->assertSame(1, $stats['Sin prioridad']);
    }

    public function test_user_without_coordinator_role_cannot_see_the_indicators(): void
    {
        $this->actingAs($this->userWithRole('solicitante'));

        $this->assertFalse(CoordinatorIndicators::canView());
    }
}
