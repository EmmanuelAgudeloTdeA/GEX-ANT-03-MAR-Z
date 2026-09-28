<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles funcionales del caso MAR-Z y sus permisos Shield (Tech Plan §11.3).
     *
     * Solo incluye los permisos de las HU ya implementadas; cada HU nueva
     * agrega aqui los suyos (Prioritize, Assign, ...).
     */
    private const ROLE_PERMISSIONS = [
        'solicitante' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
            'Create:SupportRequest',
            'View:MyRequestsStats',
        ],
        'agente' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
        ],
        'coordinador' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
            'Prioritize:SupportRequest',
        ],
        'auditor' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                collect($permissions)->map(fn (string $name) => Permission::findOrCreate($name, 'web'))
            );
        }
    }
}
