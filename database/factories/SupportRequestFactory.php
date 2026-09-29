<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los estados dejan la solicitud en cada punto del flujo sin pasar por los
 * servicios, para que cada HU se pruebe sin depender de las demas.
 *
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category_id' => Category::factory(),
            'requester_id' => User::factory(),
            'status' => RequestStatus::New,
        ];
    }

    public function prioritized(RequestPriority $priority = RequestPriority::Medium): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * Asignada a un agente (T1). Sin agente, se crea uno activo con rol agente.
     */
    public function assignedTo(?User $agent = null, ?User $by = null): static
    {
        return $this->prioritized()->state(fn (array $attributes) => [
            'status' => RequestStatus::Assigned,
            'assigned_agent_id' => $agent ?? User::factory()->withRole('agente'),
            'assigned_by_id' => $by ?? User::factory()->withRole('coordinador'),
            'assigned_at' => now(),
        ]);
    }

    public function inProgress(?User $agent = null): static
    {
        return $this->assignedTo($agent)->state(fn (array $attributes) => [
            'status' => RequestStatus::InProgress,
        ]);
    }

    public function resolved(?User $agent = null): static
    {
        return $this->assignedTo($agent)->state(fn (array $attributes) => [
            'status' => RequestStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function reopened(?User $agent = null): static
    {
        return $this->resolved($agent)->state(fn (array $attributes) => [
            'status' => RequestStatus::Reopened,
        ]);
    }

    public function closed(?User $agent = null): static
    {
        return $this->resolved($agent)->state(fn (array $attributes) => [
            'status' => RequestStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
