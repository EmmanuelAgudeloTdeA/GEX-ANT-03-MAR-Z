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
     * Incluye los permisos de las HU implementadas hasta el momento.
     */
    private const ROLE_PERMISSIONS = [
        'solicitante' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
            'Create:SupportRequest',
            'Confirm:SupportRequest',
            'Reopen:SupportRequest',
            'View:MyRequestsStats',
        ],
        'agente' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
            'ChangeStatus:SupportRequest',
            'Comment:SupportRequest',
        ],
        'coordinador' => [
            'ViewAny:SupportRequest',
            'View:SupportRequest',
            'Prioritize:SupportRequest',
            'Assign:SupportRequest',
            'Comment:SupportRequest',
            'View:TriageStats',
            'ViewHistory:SupportRequest',
        ],
        'auditor' => [
            'ViewHistory:SupportRequest',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                collect($permissions)->map(
                    fn (string $name) => Permission::findOrCreate($name, 'web')
                )
            );
        }

        $superAdmin = Role::findOrCreate('super_admin', 'web');

        $superAdmin->givePermissionTo(
            Permission::findOrCreate('ViewHistory:SupportRequest', 'web')
        );
    }
}
