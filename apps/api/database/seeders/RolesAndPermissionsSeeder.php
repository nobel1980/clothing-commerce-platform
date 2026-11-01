<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create permissions
        $permissions = [
            // Identity
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'audit.view',

            // Catalog
            'catalog.view',
            'catalog.manage',

            // Inventory
            'inventory.view',
            'inventory.manage',

            // Orders
            'orders.view',
            'orders.manage',

            // Customers
            'customers.view',
            'customers.manage',

            // Payments
            'payments.view',
            'payments.manage',

            // Shipping
            'shipping.view',
            'shipping.manage',

            // Promotions
            'promotions.view',
            'promotions.manage',

            // Reviews
            'reviews.view',
            'reviews.manage',

            // Reports
            'reports.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 2. Define Roles and Assign Permissions
        $rolesWithPermissions = [
            'super_admin' => $permissions, // all permissions

            'product_manager' => [
                'catalog.view',
                'catalog.manage',
                'inventory.view',
                'reviews.view',
                'reviews.manage',
            ],

            'inventory_manager' => [
                'inventory.view',
                'inventory.manage',
                'catalog.view',
            ],

            'order_manager' => [
                'orders.view',
                'orders.manage',
                'shipping.view',
                'shipping.manage',
                'customers.view',
                'payments.view',
            ],

            'customer_support' => [
                'orders.view',
                'customers.view',
                'reviews.view',
            ],

            'marketing_manager' => [
                'promotions.view',
                'promotions.manage',
                'catalog.view',
                'reviews.view',
            ],

            'report_viewer' => [
                'reports.view',
                'orders.view',
                'inventory.view',
                'catalog.view',
            ],

            'customer' => [],
        ];

        foreach ($rolesWithPermissions as $roleName => $assignedPermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (!empty($assignedPermissions)) {
                $role->syncPermissions($assignedPermissions);
            }
        }

        // 3. Create Default Super Administrator
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@clothing-commerce.test'],
            [
                'name' => 'Super Administrator',
                'phone' => '+8801700000000',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        if (!$superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }
    }
}
