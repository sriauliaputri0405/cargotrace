<?php

namespace App\Http\Controllers;

use App\Http\Requests\EtaRequest;
use App\Http\Requests\TrackingEventRequest;
use App\Models\Shipment;
use App\Services\EtaService;
use App\Services\ShipmentService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function __construct(private ShipmentService $shipments, private EtaService $eta)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $shipments = Shipment::query()
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->when($user->isCustomer(), fn ($q) => $q->where('customer_id', $user->id))
            ->when($request->filled('q'), fn ($q) => $q->where(
                'shipment_number', 'like', '%' . trim($request->input('q')) . '%'
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Lokasi terkini: event terbaru yang punya lokasi
        $shipments->getCollection()->each(
            fn ($s) => $s->setRelation('currentEvent', $this->shipments->currentLocationEvent($s))
        );

        return view('tracking.index', compact('shipments'));
    }

    public function store(TrackingEventRequest $request, Shipment $shipment)
    {
        Gate::authorize('changeStatus', $shipment);

        try {
            $this->shipments->addEvent($shipment, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['tracking' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Event tracking ditambahkan.');
    }

    public function updateEta(EtaRequest $request, Shipment $shipment)
    {
        Gate::authorize('changeStatus', $shipment);

        $this->eta->updateEstimate(
            $shipment,
            $request->date('estimated_arrival_at'),
            $request->user(),
            $request->validated('note'),
        );

        return back()->with('status', 'ETA diperbarui.');
    }
}