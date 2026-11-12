<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Rps\Http\Controllers\RpsUploadController;
use Illuminate\Support\Facades\Route;

/*
| Owner: A. Other modules add routes in routes/api/<module>.php and require them here.
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('rps', RpsUploadController::class)->middleware('throttle:upload');
});

require __DIR__.'/api/rps.php';
require __DIR__.'/api/industry.php';
require __DIR__.'/api/analyses.php';
require __DIR__.'/api/recommendations.php';
