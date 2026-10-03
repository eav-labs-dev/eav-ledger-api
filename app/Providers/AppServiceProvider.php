<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            $identity = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute((int) config('ledger.rate_limits.auth_per_minute'))
                ->by($identity.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute((int) config('ledger.rate_limits.api_per_minute'))
                ->by((string) $identity);
        });
    }
}
