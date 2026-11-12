<?php

declare(strict_types=1);

use App\Modules\Recommendation\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('analyses/{analysis}/recommendations', [RecommendationController::class, 'index']);
});
