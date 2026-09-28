<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'password' => $validated['password'],
        ]);

        return ApiResponse::success(
            code: 'USER_REGISTERED',
            message: 'Account created successfully',
            data: $this->tokenPayload($user, $validated['device_name']),
            status: 201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()
            ->where('email', Str::lower(trim($validated['email'])))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                code: 'INVALID_CREDENTIALS',
                message: 'The provided credentials are invalid',
                error: null,
                status: 401,
            );
        }

        return ApiResponse::success(
            code: 'AUTHENTICATED',
            message: 'Authenticated successfully',
            data: $this->tokenPayload($user, $validated['device_name']),
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            code: 'CURRENT_USER',
            message: 'Current user retrieved',
            data: (new UserResource($request->user()))->resolve(),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(
            code: 'LOGGED_OUT',
            message: 'Current access token revoked',
        );
    }

    /**
     * @return array{token: string, token_type: string, user: array<string, mixed>}
     */
    private function tokenPayload(User $user, string $deviceName): array
    {
        return [
            'token' => $user->createToken(trim($deviceName))->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ];
    }
}
