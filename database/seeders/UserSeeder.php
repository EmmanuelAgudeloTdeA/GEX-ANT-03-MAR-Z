<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Un usuario de prueba por rol, sin datos personales reales.
     * Contraseña de todos: "password".
     */
    public function run(): void
    {
        $users = [
            'super_admin' => ['name' => 'Administrador', 'email' => 'test@example.com'],
            'solicitante' => ['name' => 'Solicitante Demo', 'email' => 'solicitante@example.com'],
            'agente' => ['name' => 'Agente Demo', 'email' => 'agente@example.com'],
            'coordinador' => ['name' => 'Coordinador Demo', 'email' => 'coordinador@example.com'],
            'auditor' => ['name' => 'Auditor Demo', 'email' => 'auditor@example.com'],
        ];

        foreach ($users as $role => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password', 'is_active' => true],
            );

            $user->syncRoles([$role]);
        }
    }
}
