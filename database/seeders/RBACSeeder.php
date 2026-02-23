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
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Can manage users and view reports',
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

        // Create a super admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1990-01-01',
                'status' => 'A',
            ]
        );

        $superAdmin->syncRoles(['super-admin']);

        $this->command->info('RBAC setup completed successfully!');
        $this->command->info('Super Admin credentials:');
        $this->command->info('Email: admin@example.com');
        $this->command->info('Password: password');
    }
}
