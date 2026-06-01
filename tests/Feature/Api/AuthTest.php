<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 'aaaaaaaa-0000-0000-0000-000000000001',
            'password'  => bcrypt('secret'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at'], 'request_id'])
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('request_id'));
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['email'], 'request_id']);
    }

    public function test_login_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email', 'password'], 'request_id']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'none@test.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'none@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_logout_with_valid_token_returns_204(): void
    {
        $user  = User::factory()->create(['tenant_id' => 'aaaaaaaa-0000-0000-0000-000000000001']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
        $response->assertHeader('X-Request-ID');
    }

    public function test_logout_with_invalid_token_returns_401(): void
    {
        $response = $this->withToken('invalid-token')->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['request_id']);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    public function test_logout_deletes_token_from_database(): void
    {
        $user      = User::factory()->create(['tenant_id' => 'aaaaaaaa-0000-0000-0000-000000000001']);
        $newToken  = $user->createToken('test');
        $tokenId   = $newToken->accessToken->id;

        $this->withToken($newToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}
