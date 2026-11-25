<?php

declare(strict_types=1);

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Services\AiQuotaGuard;
use Illuminate\Http\JsonResponse;

class AiQuotaController extends Controller
{
    /**
     * Remaining daily AI budget and the moment it resets.
     */
    public function show(AiQuotaGuard $guard): JsonResponse
    {
        return response()->json(['data' => $guard->status()]);
    }
}
