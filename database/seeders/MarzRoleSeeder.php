<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class MarzRoleSeeder extends Seeder
{
    /**
     * Crea los roles funcionales definidos por el caso de estudio MAR-Z.
     *
     * Solo se crean los nombres. Los permisos de cada rol se asignan mas
     * adelante desde el panel, en Filament Shield.
     */
    public function run(): void
    {
        $roles = [
            'solicitante',
            'agente',
            'coordinador',
            'auditor',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
