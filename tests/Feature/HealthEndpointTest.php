<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_uses_the_stable_api_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'code' => 'HEALTH_OK',
                'message' => 'EAV Ledger API is healthy',
                'data' => [
                    'service' => 'EAV Ledger API',
                    'environment' => 'testing',
                    'version' => 'test',
                    'status' => 'up',
                ],
                'page' => null,
                'sort' => null,
                'filters' => null,
                'error' => null,
            ]);
    }

    public function test_api_errors_use_the_stable_api_envelope(): void
    {
        $this->getJson('/api/v1/missing')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND')
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'page',
                'sort',
                'filters',
                'error',
            ]);
    }
}
