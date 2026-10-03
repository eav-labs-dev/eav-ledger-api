<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('owner@example.com|127.0.0.1');

        parent::tearDown();
    }

    public function test_authentication_attempts_are_limited_by_identity_and_ip(): void
    {
        config(['ledger.rate_limits.auth_per_minute' => 2]);
        User::factory()->create(['email' => 'owner@example.com']);

        $payload = [
            'email' => 'OWNER@EXAMPLE.COM',
            'password' => 'WrongPassword1',
            'device_name' => 'rate-limit-test',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'RATE_LIMIT_EXCEEDED')
            ->assertJsonPath('error', null);
    }

    public function test_authenticated_api_requests_are_limited_per_user(): void
    {
        config(['ledger.rate_limits.api_per_minute' => 2]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/customers')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/customers')->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/customers')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMIT_EXCEEDED');
    }

    public function test_rate_limit_buckets_are_isolated_between_users(): void
    {
        config(['ledger.rate_limits.api_per_minute' => 1]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->getJson('/api/v1/customers')->assertOk();
        $this->actingAs($firstUser)->getJson('/api/v1/customers')->assertTooManyRequests();
        $this->actingAs($secondUser)->getJson('/api/v1/customers')->assertOk();
    }

    public function test_health_check_is_not_rate_limited(): void
    {
        config([
            'ledger.rate_limits.auth_per_minute' => 1,
            'ledger.rate_limits.api_per_minute' => 1,
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->getJson('/api/v1/health')
                ->assertOk()
                ->assertJsonPath('code', 'HEALTH_OK');
        }
    }
}
