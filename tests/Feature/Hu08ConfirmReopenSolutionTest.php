<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\ConfirmSupportRequest;
use App\Actions\SupportRequests\ReopenSupportRequest;
use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class Hu08ConfirmReopenSolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_requester_can_confirm_a_resolved_solution(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->resolved($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $result = app(ConfirmSupportRequest::class)->handle(
            $requester,
            $request
        );

        $result->refresh();

        $this->assertSame(RequestStatus::Closed, $result->status);
        $this->assertNotNull($result->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'support_request_id' => $result->getKey(),
            'actor_id' => $requester->getKey(),
            'event' => AuditEvent::Confirmed->value,
            'field' => 'status',
            'old_value' => RequestStatus::Resolved->value,
            'new_value' => RequestStatus::Closed->value,
        ]);
    }

    public function test_only_the_requester_can_confirm_the_solution(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $otherUser = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->resolved($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(ConfirmSupportRequest::class)->handle(
            $otherUser,
            $request
        );
    }

    public function test_solution_can_only_be_confirmed_when_resolved(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->inProgress($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(ConfirmSupportRequest::class)->handle(
            $requester,
            $request
        );
    }

    public function test_requester_can_reopen_a_resolved_solution_with_a_reason(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->resolved($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $reason = 'La solución aplicada no resolvió completamente el problema.';

        $result = app(ReopenSupportRequest::class)->handle(
            $requester,
            $request,
            $reason
        );

        $result->refresh();

        $this->assertSame(RequestStatus::Reopened, $result->status);
        $this->assertNull($result->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'support_request_id' => $result->getKey(),
            'actor_id' => $requester->getKey(),
            'event' => AuditEvent::Reopened->value,
            'field' => 'status',
            'old_value' => RequestStatus::Resolved->value,
            'new_value' => RequestStatus::Reopened->value,
            'reason' => $reason,
        ]);
    }

    public function test_reopening_requires_a_reason(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->resolved($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Debes indicar el motivo de la reapertura.'
        );

        app(ReopenSupportRequest::class)->handle(
            $requester,
            $request,
            '   '
        );
    }

    public function test_only_the_requester_can_reopen_the_solution(): void
    {
        $requester = User::factory()
            ->withRole('solicitante')
            ->create();

        $otherUser = User::factory()
            ->withRole('solicitante')
            ->create();

        $agent = User::factory()
            ->withRole('agente')
            ->create();

        $request = SupportRequest::factory()
            ->resolved($agent)
            ->create([
                'requester_id' => $requester->getKey(),
            ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(ReopenSupportRequest::class)->handle(
            $otherUser,
            $request,
            'Necesito revisar nuevamente la solución.'
        );
    }
}