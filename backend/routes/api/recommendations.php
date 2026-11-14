<?php

declare(strict_types=1);

use App\Modules\Recommendation\Http\Controllers\RecommendationController;
use App\Modules\Recommendation\Http\Controllers\UserSkillController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('analyses/{analysis}/recommendations', [RecommendationController::class, 'index']);
    Route::post('analyses/{analysis}/recommendations/refresh', [RecommendationController::class, 'refresh']);

    Route::get('me/skills', [UserSkillController::class, 'index']);
    Route::post('me/skills', [UserSkillController::class, 'store']);
    Route::delete('me/skills/{userSkill}', [UserSkillController::class, 'destroy'])
        ->whereNumber('userSkill');
});