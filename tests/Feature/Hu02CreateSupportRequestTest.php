<?php

namespace Tests\Feature;

use App\Actions\SupportRequests\CreateSupportRequest as CreateSupportRequestAction;
use App\Enums\AuditEvent;
use App\Enums\RequestStatus;
use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * HU02: titulo, descripcion y categoria obligatorios; el sistema genera ID,
 * fecha, estado Nuevo y propietario.
 */
class Hu02CreateSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->requester = User::factory()->create();
        $this->requester->assignRole('solicitante');
    }

    public function test_requester_creates_a_request_with_automatic_fields_and_audit(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->requester);

        Livewire::test(CreateSupportRequest::class)
            ->fillForm([
                'title' => 'No enciende el equipo',
                'description' => 'El equipo de la bodega 3 no enciende desde ayer.',
                'category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $request = SupportRequest::sole();

        $this->assertSame('No enciende el equipo', $request->title);
        $this->assertSame($category->id, $request->category_id);
        $this->assertSame($this->requester->id, $request->requester_id);
        $this->assertSame(RequestStatus::Nuevo, $request->status);
        $this->assertNotNull($request->id);
        $this->assertNotNull($request->created_at);

        $log = AuditLog::sole();
        $this->assertSame($request->id, $log->support_request_id);
        $this->assertSame(AuditEvent::Created, $log->event);
        $this->assertSame($this->requester->id, $log->actor_id);
        $this->assertSame('solicitante', $log->actor_role);
        $this->assertSame('status', $log->field);
        $this->assertSame('nuevo', $log->new_value);
    }

    public function test_title_description_and_category_are_required(): void
    {
        $this->actingAs($this->requester);

        Livewire::test(CreateSupportRequest::class)
            ->fillForm(['title' => '', 'description' => '', 'category_id' => null])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'description' => 'required',
                'category_id' => 'required',
            ]);

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_inactive_category_is_rejected(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->expectException(\DomainException::class);

        app(CreateSupportRequestAction::class)->handle($this->requester, [
            'title' => 'Titulo',
            'description' => 'Descripcion',
            'category_id' => $category->id,
        ]);
    }

    public function test_user_cannot_set_status_or_owner(): void
    {
        $category = Category::factory()->create();
        $otherUser = User::factory()->create();

        $request = app(CreateSupportRequestAction::class)->handle($this->requester, [
            'title' => 'Titulo',
            'description' => 'Descripcion',
            'category_id' => $category->id,
            'status' => 'cerrada',
            'requester_id' => $otherUser->id,
        ]);

        $this->assertSame(RequestStatus::Nuevo, $request->status);
        $this->assertSame($this->requester->id, $request->requester_id);
    }

    public function test_other_roles_cannot_open_the_create_page(): void
    {
        foreach (['agente', 'coordinador', 'auditor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)
                ->get('/admin/support-requests/create')
                ->assertForbidden();
        }
    }

    public function test_service_rejects_users_without_create_permission(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agente');

        $this->expectException(AuthorizationException::class);

        app(CreateSupportRequestAction::class)->handle($agent, [
            'title' => 'Titulo',
            'description' => 'Descripcion',
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    public function test_list_and_detail_pages_render_for_the_requester(): void
    {
        $request = SupportRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'title' => 'Impresora sin tóner',
        ]);

        $this->actingAs($this->requester);

        $this->get('/admin/support-requests')
            ->assertOk()
            ->assertSee('Impresora sin tóner');

        $this->get("/admin/support-requests/{$request->id}")
            ->assertOk()
            ->assertSee('Impresora sin tóner')
            ->assertSee('Nuevo');
    }

    public function test_support_requests_cannot_be_edited(): void
    {
        $request = SupportRequest::factory()->create(['requester_id' => $this->requester->id]);

        $this->assertFalse($this->requester->can('update', $request));

        $this->actingAs($this->requester)
            ->get("/admin/support-requests/{$request->id}/edit")
            ->assertNotFound();
    }

    public function test_audit_logs_are_immutable(): void
    {
        $request = app(CreateSupportRequestAction::class)->handle($this->requester, [
            'title' => 'Titulo',
            'description' => 'Descripcion',
            'category_id' => Category::factory()->create()->id,
        ]);

        $log = $request->auditLogs()->sole();

        try {
            $log->update(['new_value' => 'cerrada']);
            $this->fail('Se permitio modificar un registro de auditoria.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
