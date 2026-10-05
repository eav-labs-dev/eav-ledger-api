<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*')) {
                return $response;
            }

            $status = $response->getStatusCode();
            $content = json_decode((string) $response->getContent(), true);
            $message = is_array($content) && isset($content['message'])
                ? (string) $content['message']
                : (Response::$statusTexts[$status] ?? 'Request failed');

            if ($status >= 500 && ! config('app.debug')) {
                $message = 'An unexpected error occurred';
            }

            $apiResponse = ApiResponse::error(
                code: match ($status) {
                    401 => 'AUTHENTICATION_REQUIRED',
                    403 => 'FORBIDDEN',
                    404 => 'RESOURCE_NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    429 => 'RATE_LIMIT_EXCEEDED',
                    422 => 'VALIDATION_FAILED',
                    default => $status >= 500 ? 'INTERNAL_ERROR' : 'REQUEST_FAILED',
                },
                message: $message,
                error: is_array($content) ? ($content['errors'] ?? null) : null,
                status: $status,
            );

            if ($status === 429) {
                foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $header) {
                    if ($response->headers->has($header)) {
                        $apiResponse->headers->set($header, $response->headers->get($header));
                    }
                }
            }

            return $apiResponse;
        });
    })->create();
