<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShieldSeeder;
use Filament\Actions\DeleteAction;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU01: iniciar sesion y acceder solo a las funciones del propio rol.
 */
class Hu01AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ShieldSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::factory()->state(['is_active' => $active])->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_active_user_with_role_can_enter_the_panel(): void
    {
        $this->actingAs($this->userWithRole('solicitante'))
            ->get('/admin')
            ->assertOk();
    }

    public function test_inactive_user_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->userWithRole('solicitante', active: false))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_without_role_cannot_enter_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_solicitante_cannot_reach_administration_functions(): void
    {
        $this->actingAs($this->userWithRole('solicitante'));

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();
        $this->get('/admin/categories')->assertForbidden();
    }

    public function test_agent_cannot_create_support_requests(): void
    {
        $this->actingAs($this->userWithRole('agente'))
            ->get('/admin/support-requests/create')
            ->assertForbidden();
    }

    public function test_invalid_credentials_do_not_reveal_whether_the_user_exists(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);
        $user->assignRole('solicitante');

        $unknownEmail = Livewire::test(Login::class)
            ->fillForm(['email' => 'nobody@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->errors()
            ->get('data.email');

        $wrongPassword = Livewire::test(Login::class)
            ->fillForm(['email' => 'real@example.com', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->errors()
            ->get('data.email');

        $this->assertNotEmpty($unknownEmail);
        $this->assertSame($unknownEmail, $wrongPassword);
        $this->assertGuest();
    }

    public function test_valid_credentials_log_in_and_session_can_be_closed(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);
        $user->assignRole('solicitante');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'real@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect();

        $this->assertGuest();
    }

    public function test_user_code_is_generated_automatically(): void
    {
        $user = User::factory()->create();

        $this->assertSame(sprintf('USR-%04d', $user->id), $user->fresh()->code);
    }

    public function test_users_cannot_be_deleted_even_by_super_admin(): void
    {
        $admin = $this->userWithRole('super_admin');
        $target = $this->userWithRole('agente');

        $this->actingAs($admin);

        $this->assertFalse($admin->can('delete', $target));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionDoesNotExist(DeleteAction::class);

        Livewire::test(ListUsers::class)
            ->assertOk();
    }

    public function test_super_admin_can_manage_users_and_categories(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Soporte', 'Solicitudes de soporte',
                'Administración', 'Categorías',
                'Configuración general', 'Usuarios', 'Roles',
            ]);

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/users/create')->assertOk();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/support-requests')->assertOk();
        $this->get('/admin/support-requests/create')->assertOk();
    }
}
