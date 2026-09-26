<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * Return a successful API response using the stable Ledger envelope.
     *
     * @param  array<string, mixed>|null  $page
     * @param  array<string, mixed>|null  $sort
     * @param  array<string, mixed>|null  $filters
     */
    public static function success(
        string $code,
        string $message,
        mixed $data = null,
        int $status = 200,
        ?array $page = null,
        ?array $sort = null,
        ?array $filters = null,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'page' => $page,
            'sort' => $sort,
            'filters' => $filters,
            'error' => null,
        ], $status);
    }

    /**
     * Return a failed API response using the stable Ledger envelope.
     */
    public static function error(
        string $code,
        string $message,
        mixed $error,
        int $status,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => null,
            'page' => null,
            'sort' => null,
            'filters' => null,
            'error' => $error,
        ], $status);
    }
}
