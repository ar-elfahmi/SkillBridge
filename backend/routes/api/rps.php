<?php

declare(strict_types=1);

use App\Modules\Competency\Http\Controllers\CompetencyController;
use App\Modules\Rps\Http\Controllers\RpsDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('rps', [RpsDocumentController::class, 'index']);
    Route::get('rps/{document}', [RpsDocumentController::class, 'show']);
    Route::patch('rps/{document}', [RpsDocumentController::class, 'update']);
    Route::delete('rps/{document}', [RpsDocumentController::class, 'destroy']);

    Route::post('rps/{document}/extract', [CompetencyController::class, 'extract']);
    Route::get('rps/{document}/competencies', [CompetencyController::class, 'index']);
});
