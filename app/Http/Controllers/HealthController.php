<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            code: 'HEALTH_OK',
            message: 'EAV Ledger API is healthy',
            data: [
                'service' => config('app.name'),
                'environment' => app()->environment(),
                'version' => config('app.version'),
                'status' => 'up',
            ],
        );
    }
}
