<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_login_via_admin_portal(): void
    {
        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@clothing-commerce.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@clothing-commerce.test')
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonStructure(['data', 'token', 'message']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.logged_in',
            'user_email' => 'admin@clothing-commerce.test',
        ]);
    }

    public function test_customer_cannot_login_to_admin_portal(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'regular@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'regular@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.access_denied_non_staff',
        ]);
    }

    public function test_staff_member_can_retrieve_own_profile_via_admin_me(): void
    {
        $staff = User::factory()->staff('product_manager')->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $staff->email)
            ->assertJsonPath('data.role', 'product_manager');
    }

    public function test_admin_can_list_users_with_filters(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();
        User::factory()->count(5)->customer()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?role=customer');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_admin_can_create_new_staff_member(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();

        $payload = [
            'name' => 'Fahim Inventory Lead',
            'email' => 'fahim@clothing-commerce.test',
            'phone' => '01912345678',
            'password' => 'staffSecret123',
            'role' => 'inventory_manager',
            'status' => 'active',
            'verified' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'fahim@clothing-commerce.test')
            ->assertJsonPath('data.role', 'inventory_manager');

        $this->assertDatabaseHas('users', [
            'email' => 'fahim@clothing-commerce.test',
            'role' => 'inventory_manager',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_email' => $admin->email,
        ]);
    }

    public function test_privilege_escalation_non_super_admin_cannot_create_or_assign_super_admin_role(): void
    {
        // Staff member with user management rights tries to escalate by creating a super_admin
        $staffManager = User::factory()->staff('product_manager')->create();
        $staffManager->givePermissionTo('users.manage');

        $payload = [
            'name' => 'Hacker Admin',
            'email' => 'hacker@example.com',
            'password' => 'secret1234',
            'role' => 'super_admin',
        ];

        $response = $this->actingAs($staffManager, 'sanctum')->postJson('/api/admin/users', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$admin->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_delete_super_admin_account(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();
        $secondAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($secondAdmin, 'sanctum')->deleteJson("/api/admin/users/{$admin->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();
        $targetUser = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$targetUser->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertEquals('suspended', $targetUser->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.status_suspended',
        ]);
    }

    public function test_role_and_permission_management(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();

        // 1. List system permissions
        $permResp = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/permissions');
        $permResp->assertStatus(200);

        // 2. Create custom role
        $createRoleResp = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', [
            'name' => 'warehouse_supervisor',
            'permissions' => ['inventory.view', 'inventory.manage'],
        ]);

        $createRoleResp->assertStatus(201)
            ->assertJsonPath('data.name', 'warehouse_supervisor');

        $roleId = $createRoleResp->json('data.id');

        // 3. Update custom role
        $updateRoleResp = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/roles/{$roleId}", [
            'permissions' => ['inventory.view', 'inventory.manage', 'orders.view'],
        ]);

        $updateRoleResp->assertStatus(200);

        // 4. Delete custom role
        $delResp = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/roles/{$roleId}");
        $delResp->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['name' => 'warehouse_supervisor']);
    }

    public function test_reserved_system_roles_cannot_be_deleted(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();
        $role = Role::where('name', 'product_manager')->first();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
        $this->assertDatabaseHas('roles', ['name' => 'product_manager']);
    }

    public function test_customer_is_forbidden_from_admin_management_routes(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/admin/users');
        $response->assertStatus(403);

        $roleResponse = $this->actingAs($customer, 'sanctum')->getJson('/api/admin/roles');
        $roleResponse->assertStatus(403);

        $auditResponse = $this->actingAs($customer, 'sanctum')->getJson('/api/admin/audit-logs');
        $auditResponse->assertStatus(403);
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::where('email', 'admin@clothing-commerce.test')->first();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
