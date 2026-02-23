<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RBACSeeder extends Seeder
{
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            // User permissions
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users'],

            // Role permissions
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'roles'],

            // Permission permissions
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'permissions'],

            // Add more permissions for your future modules
            // Example: Posts
            // ['name' => 'posts.view', 'display_name' => 'View Posts', 'group' => 'posts'],
            // ['name' => 'posts.create', 'display_name' => 'Create Posts', 'group' => 'posts'],
            // ['name' => 'posts.update', 'display_name' => 'Update Posts', 'group' => 'posts'],
            // ['name' => 'posts.delete', 'display_name' => 'Delete Posts', 'group' => 'posts'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Create Roles
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Has full access to all features',
                'is_active' => true,
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Has access to most features',
                'is_active' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Can manage users and view reports',
                'is_active' => true,
            ]
        );

        $userRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Basic user with limited access',
                'is_active' => true,
            ]
        );

        // Assign all permissions to super-admin
        $allPermissions = Permission::pluck('name')->toArray();
        $superAdminRole->syncPermissions($allPermissions);

        // Assign specific permissions to admin
        $adminRole->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'permissions.view',
        ]);

        // Assign specific permissions to manager
        $managerRole->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
        ]);

        // User role has no permissions by default (or add basic ones)
        $userRole->syncPermissions([
            'users.view', // Can view other users
        ]);

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
                'status' => 'active',
            ]
        );

        $superAdmin->syncRoles(['super-admin']);

        $this->command->info('RBAC setup completed successfully!');
        $this->command->info('Super Admin credentials:');
        $this->command->info('Email: admin@example.com');
        $this->command->info('Password: password');
    }
}
