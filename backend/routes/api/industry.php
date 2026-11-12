<?php

declare(strict_types=1);

use App\Modules\Industry\Http\Controllers\CareerController;
use App\Modules\Industry\Http\Controllers\JobPostingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('careers', [CareerController::class, 'index']);
    Route::get('jobs', [JobPostingController::class, 'index']);
    Route::get('jobs/{job}', [JobPostingController::class, 'show']);
    Route::post('jobs', [JobPostingController::class, 'store']);
});
