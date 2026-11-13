<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-based SPA authentication via Sanctum (CSRF + session for stateful domains).
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Silakan masuk terlebih dahulu.'], 401);
            }

            return null;
        });

        // Tangani database down / query exception agar tidak bocor raw SQLSTATE ke response JSON
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->is('sanctum/*') || $request->expectsJson()) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return null; // Biarkan Laravel merender 422 standard
                }
                if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                    return null; // Biarkan respons kustom (seperti response rate limit 429) langsung dikembalikan
                }
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    if ($e->getStatusCode() < 500) {
                        return null; // 401, 403, 404, 409, 429 tetap sesuai exception aslinya
                    }
                }

                // Error sistem / 500 / Database exception: sembunyikan pesan internal
                return response()->json([
                    'message' => 'Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.',
                ], 500);
            }

            return null;
        });
    })->create();
