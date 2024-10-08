<?php

use Tests\TestCase;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user creation.
     */
    public function test_create_user_success()
    {
        $payload = [
            'full_name' => 'John Doe',
            'role' => 'developer',
            'efficiency' => 85,
        ];

        $response = $this->postJson('/create', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'result' => ['id']
            ]);
    }

    /**
     * Test user creation failure due to missing fields.
     */
    public function test_create_user_validation_error()
    {
        $payload = [
            'full_name' => '',
            'role' => 'developer',
            'efficiency' => 85,
        ];

        $response = $this->postJson('/create', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'result' => [
                    'error' => [
                        'full_name' => ['The full name field is required.']
                    ]
                ]
            ]);
    }

    /**
     * Test retrieving user by ID.
     */
    public function test_get_user_by_id_success()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/get/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'users' => [
                        [
                            'id' => $user->id,
                            'full_name' => $user->full_name,
                            'role' => $user->role,
                            'efficiency' => $user->efficiency,
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test retrieving user by non-existing ID.
     */
    public function test_get_user_not_found()
    {
        $response = $this->getJson('/get/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'result' => ['error' => 'User not found']
            ]);
    }

    /**
     * Test updating user.
     */
    public function test_update_user_success()
    {
        $user = User::factory()->create();

        $payload = [
            'full_name' => 'Jane Doe',
            'role' => 'manager',
        ];

        $response = $this->patchJson("/update/{$user->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'id' => $user->id,
                    'full_name' => 'Jane Doe',
                    'role' => 'manager',
                    'efficiency' => $user->efficiency,
                ]
            ]);
    }

    /**
     * Test updating non-existing user.
     */
    public function test_update_user_not_found()
    {
        $payload = [
            'full_name' => 'Non-existing user',
            'role' => 'manager',
        ];

        $response = $this->patchJson('/update/99999', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'result' => ['error' => 'User not found']
            ]);
    }

    /**
     * Test deleting user by ID.
     */
    public function test_delete_user_success()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/delete/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'efficiency' => $user->efficiency,
                ]
            ]);
    }

    /**
     * Test deleting non-existing user.
     */
    public function test_delete_user_not_found()
    {
        $response = $this->deleteJson('/delete/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'result' => ['error' => 'User not found']
            ]);
    }

    /**
     * Test deleting all users.
     */
    public function test_delete_all_users_success()
    {
        User::factory()->count(3)->create();

        $response = $this->deleteJson('/delete');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('users', 0);
    }
}
