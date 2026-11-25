<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    public function index(Request $request)
    {
        $carriers = Carrier::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . trim($request->input('q')) . '%'))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100))
            ->withQueryString();

        return CarrierResource::collection($carriers);
    }

    public function store(CarrierRequest $request): JsonResponse
    {
        $carrier = Carrier::create($request->validated());

        return (new CarrierResource($carrier))->response()->setStatusCode(201);
    }
}