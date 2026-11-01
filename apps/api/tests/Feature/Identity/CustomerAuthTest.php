<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_customer_can_register_with_valid_bangladesh_phone_and_email(): void
    {
        $payload = [
            'name' => 'Tanvir Hasan',
            'email' => 'tanvir@example.com',
            'phone' => '01712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'tanvir@example.com')
            ->assertJsonPath('data.phone', '01712345678')
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'phone', 'role', 'status'],
                'token',
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'tanvir@example.com',
            'phone' => '01712345678',
            'role' => 'customer',
        ]);
    }

    public function test_customer_registration_fails_with_invalid_bangladesh_phone(): void
    {
        $payload = [
            'name' => 'Tanvir Hasan',
            'email' => 'tanvir2@example.com',
            'phone' => '123456', // Invalid BD phone
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_customer_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $payload = [
            'name' => 'New User',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secretpass123'),
            'status' => 'active',
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'secretpass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'customer@example.com')
            ->assertJsonStructure(['data', 'token', 'message']);
    }

    public function test_customer_can_login_with_bangladesh_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '01812345678',
            'password' => Hash::make('secretpass123'),
            'status' => 'active',
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/auth/login', [
            'email' => '01812345678',
            'password' => 'secretpass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '01812345678');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('correct_pass'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'wrong_pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_suspended_customer_cannot_login(): void
    {
        $user = User::factory()->suspended()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_view_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_customer_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'phone' => '01711111111']);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/account/profile', [
            'name' => 'Updated Name',
            'phone' => '01722222222',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '01722222222');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '01722222222',
        ]);
    }

    public function test_customer_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password123'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/change-password', [
            'current_password' => 'old_password123',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password has been updated successfully.');

        $this->assertTrue(Hash::check('new_password123', $user->fresh()->password));
    }

    public function test_customer_can_create_and_manage_addresses(): void
    {
        $user = User::factory()->create();

        // 1. Create first address (should automatically be default)
        $addressPayload = [
            'name' => 'Rahim Ahmed',
            'phone' => '01712345678',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila_thana' => 'Gulshan',
            'street_address' => 'House 12, Road 4, Gulshan-1',
            'postal_code' => '1212',
            'type' => 'shipping',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/addresses', $addressPayload);

        $response->assertStatus(201)
            ->assertJsonPath('data.division', 'Dhaka')
            ->assertJsonPath('data.upazila_thana', 'Gulshan')
            ->assertJsonPath('data.is_default', true);

        $addressId = $response->json('data.id');

        // 2. Create second address with is_default = true
        $secondPayload = [
            'name' => 'Rahim Office',
            'phone' => '01712345678',
            'division' => 'Chattogram',
            'district' => 'Chattogram',
            'upazila_thana' => 'Pahartali',
            'street_address' => 'CDA Avenue',
            'postal_code' => '4000',
            'type' => 'billing',
            'is_default' => true,
        ];

        $secondResp = $this->actingAs($user, 'sanctum')->postJson('/api/account/addresses', $secondPayload);
        $secondResp->assertStatus(201)->assertJsonPath('data.is_default', true);

        // Previous address is no longer default
        $this->assertFalse(Address::find($addressId)->is_default);

        // 3. List addresses
        $listResp = $this->actingAs($user, 'sanctum')->getJson('/api/account/addresses');
        $listResp->assertStatus(200)->assertJsonCount(2, 'data');

        // 4. Delete address
        $delResp = $this->actingAs($user, 'sanctum')->deleteJson("/api/account/addresses/{$addressId}");
        $delResp->assertStatus(200);

        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }

    public function test_customer_cannot_view_or_modify_other_customer_address(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $address1 = Address::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'sanctum')->getJson("/api/account/addresses/{$address1->id}");
        $response->assertStatus(403);

        $delResponse = $this->actingAs($user2, 'sanctum')->deleteJson("/api/account/addresses/{$address1->id}");
        $delResponse->assertStatus(403);
    }

    public function test_password_reset_flow(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com']);

        // 1. Request reset token
        $forgotResp = $this->postJson('/api/auth/forgot-password', [
            'email' => 'resetme@example.com',
        ]);

        $forgotResp->assertStatus(200);
        $token = $forgotResp->json('reset_token');
        $this->assertNotNull($token);

        // 2. Reset password with token
        $resetResp = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'resetme@example.com',
            'password' => 'brand_new_pass123',
            'password_confirmation' => 'brand_new_pass123',
        ]);

        $resetResp->assertStatus(200)
            ->assertJsonPath('message', 'Password has been reset successfully. Please log in with your new password.');

        $this->assertTrue(Hash::check('brand_new_pass123', $user->fresh()->password));
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Successfully logged out.');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_unauthenticated_request_to_protected_routes_returns_401(): void
    {
        $response = $this->getJson('/api/account/profile');
        $response->assertStatus(401);

        $addressResp = $this->getJson('/api/account/addresses');
        $addressResp->assertStatus(401);
    }
}
