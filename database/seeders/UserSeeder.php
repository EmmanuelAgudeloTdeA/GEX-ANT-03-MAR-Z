<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createSuperAdmin();
    }

     private function createSuperAdmin(): void
    {
        $superAdminUser = User::find(1);

        $superAdminUserData = [
            'name' => 'Administrador',
            'email' => 'admin@admin.com',
            'password' => Hash::make(config('app.super_admin_password')),
        ];

        if ($superAdminUser) {
            $superAdminUser->update($superAdminUserData);
        } else {
            $superAdminUser = User::factory()
                ->create($superAdminUserData);
        }

        $superAdminUser->assignRole('super_admin');
    }
}
