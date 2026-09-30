<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\AddCommentToSupportRequest;
use App\Enums\AuditEvent;
use App\Filament\Actions\AddCommentAction;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Resources\SupportRequests\RelationManagers\CommentsRelationManager;
use App\Models\AuditLog;
use App\Models\RequestComment;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShieldSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HU06: comentarios no vacios, con autor y fecha automaticos e inmutables, que
 * solo ven y escriben los roles correspondientes y que ya no se editan.
 */
class Hu06AddCommentToSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ShieldSeeder::class, RolePermissionSeeder::class]);

        $this->agent = User::factory()->withRole('agente')->create();
        $this->coordinator = User::factory()->withRole('coordinador')->create();
    }

    private function comment(SupportRequest $request, string $body, ?User $by = null): RequestComment
    {
        return app(AddCommentToSupportRequest::class)->handle($by ?? $this->agent, $request, $body);
    }

    private function relationManager(SupportRequest $request): Testable
    {
        return Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => ViewSupportRequest::class,
        ]);
    }

    public function test_the_assigned_agent_adds_a_comment_with_author_and_date_automatically(): void
    {
        $this->freezeSecond();

        $request = SupportRequest::factory()->inProgress($this->agent)->create();

        $comment = $this->comment($request, '  Diagnóstico: es el cable de red.  ');

        $this->assertSame($request->id, $comment->support_request_id);
        $this->assertSame($this->agent->id, $comment->user_id);
        $this->assertSame('Diagnóstico: es el cable de red.', $comment->body);
        $this->assertTrue($comment->created_at->equalTo(now()));
        $this->assertSame($this->agent->id, $comment->author->id);
    }

    public function test_the_comment_is_audited_without_copying_its_text(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();

        $comment = $this->comment($request, 'Cambio el cable del rack 7');

        $log = AuditLog::sole();
        $this->assertSame(AuditEvent::CommentAdded, $log->event);
        $this->assertSame($request->id, $log->support_request_id);
        $this->assertSame($this->agent->id, $log->actor_id);
        $this->assertSame('agente', $log->actor_role);
        $this->assertSame(['comment_id' => $comment->id], $log->metadata);
        $this->assertStringNotContainsString('rack 7', json_encode($log->metadata));
    }

    public function test_the_comment_updates_the_last_update_of_the_request(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create([
            'updated_at' => now()->subDay(),
        ]);

        $before = $request->fresh()->updated_at;

        $this->comment($request, 'Avance de la revisión');

        $this->assertTrue($request->fresh()->updated_at->greaterThan($before));
    }

    public function test_an_empty_comment_is_rejected(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();

        foreach (['', '   ', "\n\t "] as $body) {
            try {
                $this->comment($request, $body);
                $this->fail('Se registró un comentario vacío.');
            } catch (DomainException $exception) {
                $this->assertSame('El comentario no puede estar vacío.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('request_comments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_coordinator_can_comment_too(): void
    {
        $request = SupportRequest::factory()->prioritized()->create();

        $this->assertTrue($this->coordinator->can('comment', $request));

        $comment = $this->comment($request, 'Coordinación: se reasigna el lunes.', by: $this->coordinator);

        $this->assertSame($this->coordinator->id, $comment->user_id);
        $this->assertSame($this->coordinator->id, $comment->author->id);
    }

    public function test_the_requester_a_stranger_agent_and_the_auditor_cannot_comment(): void
    {
        $owner = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->inProgress($this->agent)->create(['requester_id' => $owner->id]);

        $notAllowed = [
            'solicitante' => $owner,
            'agente' => User::factory()->withRole('agente')->create(),
            'auditor' => User::factory()->withRole('auditor')->create(),
        ];

        foreach ($notAllowed as $role => $user) {
            $this->assertFalse($user->can('comment', $request), $role);

            try {
                $this->comment($request, 'No me corresponde', by: $user);
                $this->fail("El rol {$role} pudo comentar.");
            } catch (AuthorizationException) {
            }
        }

        $this->assertDatabaseCount('request_comments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_closed_request_cannot_be_commented(): void
    {
        $closed = SupportRequest::factory()->closed($this->agent)->create();

        $this->assertFalse($this->agent->can('comment', $closed));
        $this->assertFalse($this->coordinator->can('comment', $closed));

        try {
            $this->comment($closed, 'Tarde');
            $this->fail('Se comentó una solicitud cerrada.');
        } catch (AuthorizationException) {
        }

        $this->assertDatabaseCount('request_comments', 0);
    }

    public function test_the_text_the_author_and_the_date_cannot_be_changed(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();
        $comment = $this->comment($request, 'Avance inicial');

        $attempts = [
            'texto' => ['body' => 'Otro avance'],
            'autor' => ['user_id' => $this->coordinator->id],
            'fecha' => ['created_at' => now()->addYear()],
        ];

        foreach ($attempts as $field => $changes) {
            try {
                $comment->update($changes);
                $this->fail("Se cambió el {$field} del comentario.");
            } catch (LogicException) {
            }
        }

        $fresh = $comment->fresh();
        $this->assertSame('Avance inicial', $fresh->body);
        $this->assertSame($this->agent->id, $fresh->user_id);
        $this->assertTrue($fresh->created_at->equalTo($comment->created_at));
    }

    public function test_the_policy_denies_updating_and_deleting_even_with_every_permission(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();
        $comment = $this->comment($request, 'Avance inicial');

        $admin = User::factory()->withRole('super_admin')->create();
        $admin->givePermissionTo(Permission::all());

        $this->assertFalse($admin->can('update', $comment));
        $this->assertFalse($admin->can('delete', $comment));

        $this->expectException(LogicException::class);
        $comment->delete();
    }

    public function test_a_comment_is_visible_only_to_those_who_can_see_its_request(): void
    {
        $owner = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->inProgress($this->agent)->create(['requester_id' => $owner->id]);
        $comment = $this->comment($request, 'Avance inicial');

        // El solicitante ve los comentarios de su solicitud (PD-06).
        $this->assertTrue($owner->can('view', $comment));
        $this->assertTrue($this->agent->can('view', $comment));
        $this->assertTrue($this->coordinator->can('view', $comment));

        $this->assertFalse(User::factory()->withRole('agente')->create()->can('view', $comment));
        $this->assertFalse(User::factory()->withRole('auditor')->create()->can('view', $comment));
    }

    public function test_the_comments_tab_is_only_offered_to_those_who_can_view_the_request(): void
    {
        $owner = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->inProgress($this->agent)->create(['requester_id' => $owner->id]);

        foreach ([$owner, $this->agent, $this->coordinator] as $user) {
            $this->actingAs($user);
            $this->assertTrue(CommentsRelationManager::canViewForRecord($request, ViewSupportRequest::class));
        }

        foreach ([
            User::factory()->withRole('agente')->create(),
            User::factory()->withRole('auditor')->create(),
        ] as $user) {
            $this->actingAs($user);
            $this->assertFalse(CommentsRelationManager::canViewForRecord($request, ViewSupportRequest::class));
        }
    }

    public function test_the_button_to_add_a_comment_is_only_offered_to_those_who_can_comment(): void
    {
        $owner = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->inProgress($this->agent)->create(['requester_id' => $owner->id]);
        $closed = SupportRequest::factory()->closed($this->agent)->create();

        $whoSeesTheButton = [
            'agente asignado' => [$this->agent, $request, true],
            'coordinador' => [$this->coordinator, $request, true],
            'solicitante' => [$owner, $request, false],
            'agente no asignado' => [User::factory()->withRole('agente')->create(), $request, false],
            'auditor' => [User::factory()->withRole('auditor')->create(), $request, false],
            'solicitud cerrada' => [$this->agent, $closed, false],
        ];

        foreach ($whoSeesTheButton as $case => [$user, $target, $expected]) {
            $this->actingAs($user);

            $this->assertSame(
                $expected,
                AddCommentAction::make($target)->isVisible(),
                $case,
            );
        }
    }

    public function test_the_agent_adds_a_comment_from_the_detail(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();
        $earlier = $this->comment($request, 'Primero revisé el cable');

        $this->actingAs($this->agent);

        $this->relationManager($request)
            ->assertCanSeeTableRecords([$earlier])
            ->callTableAction('addComment', data: ['body' => 'Después el switch'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('request_comments', [
            'support_request_id' => $request->id,
            'user_id' => $this->agent->id,
            'body' => 'Después el switch',
        ]);
    }

    public function test_the_form_rejects_an_empty_comment_and_the_table_offers_no_editing(): void
    {
        $request = SupportRequest::factory()->inProgress($this->agent)->create();

        $this->actingAs($this->agent);

        $this->relationManager($request)
            ->callTableAction('addComment', data: ['body' => '   '])
            ->assertHasActionErrors(['body'])
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete');

        $this->assertDatabaseCount('request_comments', 0);
    }

    public function test_the_requester_sees_the_comments_but_not_the_button_to_add_one(): void
    {
        $owner = User::factory()->withRole('solicitante')->create();
        $request = SupportRequest::factory()->inProgress($this->agent)->create(['requester_id' => $owner->id]);
        $comment = $this->comment($request, 'Replacing the switch');

        $this->actingAs($owner);

        $this->relationManager($request)
            ->assertCanSeeTableRecords([$comment])
            ->assertTableActionHidden('addComment');

        $this->actingAs($this->agent);

        $this->relationManager($request)
            ->assertTableActionVisible('addComment');
    }
}
