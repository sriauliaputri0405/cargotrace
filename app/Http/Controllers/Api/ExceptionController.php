<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExceptionRequest;
use App\Http\Requests\ExceptionStatusRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return ExceptionResource::collection(
            $shipment->exceptions()->latest('detected_at')->paginate($perPage)
        );
    }

    public function store(ExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        $exception = $this->exceptions->report($shipment, $request->validated(), $request->user());

        return (new ExceptionResource($exception))->response()->setStatusCode(201);
    }

    public function updateStatus(ExceptionStatusRequest $request, ShipmentException $exception): ExceptionResource
    {
        try {
            $exception = $this->exceptions->transition($exception, $request->validated('status'), $request->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['status' => [$e->getMessage()]]);
        }

        return new ExceptionResource($exception);
    }
}