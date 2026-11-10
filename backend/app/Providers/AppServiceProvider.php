<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Login/register attempts per minute, keyed by email + IP.
        RateLimiter::for('auth', function (Request $request): Limit {
            $perMinute = (int) config('skillbridge.rate_limits.auth_per_minute', 5);
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute($perMinute)
                ->by($email.'|'.$request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.',
                ], 429));
        });
    }
}
