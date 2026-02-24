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
            ['email' => 'sa@example.com'],
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

        // Regular User
        $user = User::firstOrCreate(
            ['email' => 'jane@example.com'],
            [
                'first_name' => 'Jane',
                'middle_name' => null,
                'last_name' => 'Doe',
                'suffix' => null,
                'username' => 'user1',
                'password' => Hash::make('password'),
                'gender' => 'female',
                'birthdate' => '1995-03-10',
                'status' => 'A',
            ]
        );
        $user->syncRoles(['user']);

        // Inactive User (for testing)
        $inactiveUser = User::firstOrCreate(
            ['email' => 'mark@example.com'],
            [
                'first_name' => 'Mark',
                'middle_name' => null,
                'last_name' => 'Doe',
                'suffix' => null,
                'username' => 'user2',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1993-11-25',
                'status' => 'I',
            ]
        );
        $inactiveUser->syncRoles(['user']);

        // Multi-role User (SA + Admin)
        $multiRole = User::firstOrCreate(
            ['email' => 'multi@example.com'],
            [
                'first_name' => 'Kevin',
                'middle_name' => null,
                'last_name' => 'Doe',
                'suffix' => 'Jr.',
                'username' => 'multirole',
                'password' => Hash::make('password'),
                'gender' => 'male',
                'birthdate' => '1991-07-14',
                'status' => 'A',
            ]
        );
        $multiRole->syncRoles(['super-admin', 'admin']);
    }
}
