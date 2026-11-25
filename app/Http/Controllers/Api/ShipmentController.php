<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EtaRequest;
use App\Http\Requests\ShipmentRequest;
use App\Http\Requests\ShipmentStatusRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\EtaService;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    private const RELATIONS = ['customer', 'carrier', 'origin', 'destination', 'items'];

    public function __construct(private ShipmentService $service, private EtaService $eta)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Shipment::class);
        $user = $request->user();

        $request->validate([
            'status'    => ['nullable', Rule::in(ShipmentService::STATUSES)],
            'carrier_id'=> ['nullable', 'integer'],
            'sort'      => ['nullable', Rule::in(['created_at', 'expected_arrival_at', 'shipment_number', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $shipments = Shipment::query()
            ->with(self::RELATIONS)
            ->when($user->isCustomer(), fn ($q) => $q->where('customer_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('carrier_id'), fn ($q) => $q->where('carrier_id', $request->input('carrier_id')))
            ->when($request->boolean('delayed'), fn ($q) => $q->where('is_delayed', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('shipment_number', 'like', $term)
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        return ShipmentResource::collection($shipments);
    }

    public function store(ShipmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Shipment::class);
        $user = $request->user();

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        // Customer selalu membuat shipment atas namanya sendiri
        if ($user->isCustomer()) {
            $data['customer_id'] = $user->id;
        }

        $shipment = $this->service->create($data, $items, $user)->load(self::RELATIONS);

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        Gate::authorize('view', $shipment);

        return new ShipmentResource($shipment->load(self::RELATIONS));
    }

    public function update(ShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('update', $shipment);

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $shipment = $this->service->update($shipment, $data, $items, $request->user());

        return new ShipmentResource($shipment->load(self::RELATIONS));
    }

    public function destroy(Request $request, Shipment $shipment): Response
    {
        Gate::authorize('delete', $shipment);

        $this->service->delete($shipment, $request->user());

        return response()->noContent();
    }

    public function updateStatus(ShipmentStatusRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('changeStatus', $shipment);

        try {
            $shipment = $this->service->changeStatus(
                $shipment,
                $request->validated('status'),
                $request->user(),
                $request->validated('location_id'),
                $request->validated('note'),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['status' => [$e->getMessage()]]);
        }

        return new ShipmentResource($shipment->load(self::RELATIONS));
    }

    public function updateEta(EtaRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('changeStatus', $shipment);

        $shipment = $this->eta->updateEstimate(
            $shipment,
            $request->date('estimated_arrival_at'),
            $request->user(),
            $request->validated('note'),
        );

        return new ShipmentResource($shipment->load(self::RELATIONS));
    }
}