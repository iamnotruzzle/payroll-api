<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RBACSeeder extends Seeder
{
    public function run(): void
    {
        // Create Roles
        Role::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Has full access to all features',
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Has access to most features',
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Basic user with limited access',
                'is_active' => true,
            ]
        );
    }
}
