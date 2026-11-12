<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Controllers;

use App\Models\Career;
use App\Modules\Industry\Http\Resources\CareerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CareerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $careers = Career::orderBy('name')->get();

        return CareerResource::collection($careers);
    }
}
