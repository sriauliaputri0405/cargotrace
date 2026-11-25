<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $locations = Location::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->orderBy('country')
            ->orderBy('city')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100))
            ->withQueryString();

        return LocationResource::collection($locations);
    }

    public function store(LocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        return (new LocationResource($location))->response()->setStatusCode(201);
    }
}