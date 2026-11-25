<?php

declare(strict_types=1);

use App\Modules\Ai\Http\Controllers\AiQuotaController;
use App\Modules\Matching\Http\Controllers\AnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:ai');
    Route::get('analyses', [AnalysisController::class, 'index']);
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show']);
    Route::get('analyses/{analysis}/matches', [AnalysisController::class, 'matches']);
    Route::delete('analyses/{analysis}', [AnalysisController::class, 'destroy']);

    // AI budget status. Lives here because this file is already loaded by routes/api.php (owner A):
    // move it to routes/api/ai.php when A adds the require line.
    Route::get('ai/quota', [AiQuotaController::class, 'show'])->middleware('throttle:60,1');
});
