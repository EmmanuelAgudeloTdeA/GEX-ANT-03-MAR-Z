<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\RequestComment;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Base del Sprint 2: matriz de transiciones (Tech Plan §9) y comentarios
 * inmutables. Las HU05-HU08 se apoyan en estas piezas.
 */
class RequestStatusTransitionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Todas las transiciones validas; cualquier otra combinacion se rechaza.
     *
     * @var array<array{0: RequestStatus, 1: RequestStatus}>
     */
    private const VALID = [
        [RequestStatus::New, RequestStatus::Assigned],
        [RequestStatus::Assigned, RequestStatus::InProgress],
        [RequestStatus::Assigned, RequestStatus::Assigned],
        [RequestStatus::InProgress, RequestStatus::Resolved],
        [RequestStatus::InProgress, RequestStatus::Assigned],
        [RequestStatus::Resolved, RequestStatus::Closed],
        [RequestStatus::Resolved, RequestStatus::Reopened],
        [RequestStatus::Reopened, RequestStatus::InProgress],
        [RequestStatus::Reopened, RequestStatus::Assigned],
    ];

    public function test_the_matrix_only_contains_the_valid_transitions(): void
    {
        foreach (RequestStatus::cases() as $from) {
            foreach (RequestStatus::cases() as $to) {
                $this->assertSame(
                    in_array([$from, $to], self::VALID, true),
                    $from->hasTransitionTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public function test_nothing_goes_back_to_new_and_closed_is_final(): void
    {
        foreach (RequestStatus::cases() as $status) {
            $this->assertFalse($status->hasTransitionTo(RequestStatus::New), $status->value);
            $this->assertFalse(RequestStatus::Closed->hasTransitionTo($status), $status->value);
        }
    }

    public function test_each_transition_is_only_available_to_its_actor(): void
    {
        $agent = User::factory()->withRole('agente')->create();
        $otherAgent = User::factory()->withRole('agente')->create();
        $coordinator = User::factory()->withRole('coordinador')->create();
        $owner = User::factory()->withRole('solicitante')->create();

        $inProgress = SupportRequest::factory()->inProgress($agent)->create(['requester_id' => $owner->id]);

        $this->assertSame([RequestStatus::Resolved], RequestStatus::InProgress->allowedTargetsFor($agent, $inProgress));
        $this->assertSame([], RequestStatus::InProgress->allowedTargetsFor($otherAgent, $inProgress));
        $this->assertSame([], RequestStatus::InProgress->allowedTargetsFor($owner, $inProgress));
        $this->assertSame([RequestStatus::Assigned], RequestStatus::InProgress->allowedTargetsFor($coordinator, $inProgress));

        $resolved = SupportRequest::factory()->resolved($agent)->create(['requester_id' => $owner->id]);

        $this->assertTrue(RequestStatus::Resolved->canTransitionTo(RequestStatus::Closed, $owner, $resolved));
        $this->assertTrue(RequestStatus::Resolved->canTransitionTo(RequestStatus::Reopened, $owner, $resolved));
        $this->assertFalse(RequestStatus::Resolved->canTransitionTo(RequestStatus::Closed, $agent, $resolved));
        $this->assertFalse(RequestStatus::Resolved->canTransitionTo(RequestStatus::InProgress, $agent, $resolved));
    }

    public function test_factory_states_leave_requests_at_each_point_of_the_flow(): void
    {
        $agent = User::factory()->withRole('agente')->create();

        $this->assertSame(RequestStatus::Assigned, SupportRequest::factory()->assignedTo($agent)->create()->status);
        $this->assertSame(RequestStatus::InProgress, SupportRequest::factory()->inProgress($agent)->create()->status);
        $this->assertNotNull(SupportRequest::factory()->resolved($agent)->create()->resolved_at);
        $this->assertSame(RequestStatus::Reopened, SupportRequest::factory()->reopened($agent)->create()->status);

        $closed = SupportRequest::factory()->closed($agent)->create();
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($agent->id, $closed->assigned_agent_id);
        $this->assertTrue($closed->assignedBy->hasRole('coordinador'));
    }

    public function test_comments_cannot_be_edited_or_deleted(): void
    {
        $request = SupportRequest::factory()->assignedTo()->create();

        $comment = new RequestComment(['body' => 'Avance']);
        $comment->supportRequest()->associate($request);
        $comment->author()->associate($request->assignedAgent);
        $comment->save();

        try {
            $comment->update(['body' => 'Cambiado']);
            $this->fail('Se editó un comentario.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $comment->delete();
    }
}
