<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackingEventRequest;
use App\Http\Resources\LocationResource;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    public function __construct(private ShipmentService $service)
    {
    }

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $events = $shipment->trackingEvents()
            ->with(['location', 'creator'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate($perPage);

        $current = $this->service->currentLocationEvent($shipment);

        return TrackingEventResource::collection($events)->additional([
            'current_location' => $current ? new LocationResource($current->location) : null,
        ]);
    }

    public function store(TrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('changeStatus', $shipment);

        try {
            $event = $this->service->addEvent($shipment, $request->validated(), $request->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['occurred_at' => [$e->getMessage()]]);
        }

        return (new TrackingEventResource($event->load(['location', 'creator'])))
            ->response()->setStatusCode(201);
    }
}