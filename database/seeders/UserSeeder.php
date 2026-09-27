<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::find(1);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ];

        if ($user) {
            $user->update($userData);
        } else {
            $user = User::factory()->create($userData);
        }

        $user->syncRoles(['super_admin']);
    }
}
