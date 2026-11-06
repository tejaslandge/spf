<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'superadmin@finance.test'],
            [
                'name' => 'Super Admin',
                'mobile' => '9000000001',
                'role' => UserRole::SuperAdmin,
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@finance.test'],
            [
                'name' => 'Operations Admin',
                'mobile' => '9000000002',
                'role' => UserRole::Admin,
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );
    }
}
