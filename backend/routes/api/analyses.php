<?php

declare(strict_types=1);

use App\Modules\Matching\Http\Controllers\AnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('analyses', [AnalysisController::class, 'store']);
    Route::get('analyses', [AnalysisController::class, 'index']);
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show']);
    Route::get('analyses/{analysis}/matches', [AnalysisController::class, 'matches']);
    Route::delete('analyses/{analysis}', [AnalysisController::class, 'destroy']);
});
