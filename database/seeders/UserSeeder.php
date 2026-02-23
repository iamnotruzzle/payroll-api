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
        // Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Super',
                'middle_name' => null,
                'last_name' => 'Admin',
                'suffix' => null,
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1990-01-01',
                'status' => 'A',
            ]
        );
        $superAdmin->syncRoles(['super-admin']);

        // Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin.user@example.com'],
            [
                'first_name' => 'Admin',
                'middle_name' => null,
                'last_name' => 'User',
                'suffix' => null,
                'username' => 'admin',
                'password' => Hash::make('password'),
                'gender' => 'female',
                'birthdate' => '1992-05-15',
                'status' => 'A',
            ]
        );
        $admin->syncRoles(['admin']);

        // Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'first_name' => 'John',
                'middle_name' => 'Michael',
                'last_name' => 'Manager',
                'suffix' => null,
                'username' => 'manager',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1988-08-20',
                'status' => 'A',
            ]
        );
        $manager->syncRoles(['manager']);

        // Regular User
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'first_name' => 'Jane',
                'middle_name' => null,
                'last_name' => 'Doe',
                'suffix' => null,
                'username' => 'user',
                'password' => Hash::make('password'),
                'gender' => 'female',
                'birthdate' => '1995-03-10',
                'status' => 'A',
            ]
        );
        $user->syncRoles(['user']);

        // Inactive User (for testing)
        $inactiveUser = User::firstOrCreate(
            ['email' => 'inactive@example.com'],
            [
                'first_name' => 'Inactive',
                'middle_name' => null,
                'last_name' => 'User',
                'suffix' => null,
                'username' => 'inactive',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1993-11-25',
                'status' => 'A',
            ]
        );
        $inactiveUser->syncRoles(['user']);

        // Multi-role User (Admin + Manager)
        $multiRole = User::firstOrCreate(
            ['email' => 'multi@example.com'],
            [
                'first_name' => 'Multi',
                'middle_name' => 'Role',
                'last_name' => 'User',
                'suffix' => 'Jr.',
                'username' => 'multirole',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1991-07-14',
                'status' => 'A',
            ]
        );
        $multiRole->syncRoles(['admin', 'manager']);

        $this->command->info('Users seeded successfully!');
        $this->command->newLine();
        $this->command->info('Test Credentials:');
        $this->command->table(
            ['Role', 'Username', 'Email', 'Password', 'Status'],
            [
                ['Super Admin', 'superadmin', 'admin@example.com', 'password', 'A'],
                ['Admin', 'admin', 'admin.user@example.com', 'password', 'A'],
                ['Manager', 'manager', 'manager@example.com', 'password', 'A'],
                ['User', 'user', 'user@example.com', 'password', 'A'],
                ['User', 'inactive', 'inactive@example.com', 'password', 'inactive'],
                ['Admin+Manager', 'multirole', 'multi@example.com', 'password', 'A'],
            ]
        );
    }
}
