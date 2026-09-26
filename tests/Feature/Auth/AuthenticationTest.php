<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_an_access_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ADA@EXAMPLE.COM',
            'password' => 'Ledger123',
            'password_confirmation' => 'Ledger123',
            'device_name' => 'portfolio-demo',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'USER_REGISTERED')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_validation_uses_the_api_error_envelope(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['name', 'email', 'password', 'device_name']]);
    }

    public function test_user_can_log_in_and_retrieve_their_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'Ledger123',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'OWNER@EXAMPLE.COM',
            'password' => 'Ledger123',
            'device_name' => 'portfolio-demo',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('code', 'CURRENT_USER')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'owner@example.com');
    }

    public function test_invalid_credentials_do_not_reveal_account_existence(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'WrongPassword1',
            'device_name' => 'portfolio-demo',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('error', null);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($currentToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('code', 'LOGGED_OUT');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
    }
}
