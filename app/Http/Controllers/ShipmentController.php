<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentRequest;
use App\Http\Requests\ShipmentStatusRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EtaService;
use App\Services\MapService;
use App\Services\ShipmentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Shipment::class);
        $user = $request->user();

        // Kolom sorting dibatasi daftar putih supaya input pengguna tidak masuk ke query
        $sort = in_array($request->input('sort'), ['created_at', 'shipment_number', 'expected_arrival_at', 'status'], true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));
        $status = $request->input('status');

        $shipments = Shipment::query()
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->when($user->isCustomer(), fn ($q) => $q->where('customer_id', $user->id))
            ->when(in_array($status, ShipmentService::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('carrier_id'), fn ($q) => $q->where('carrier_id', (int) $request->input('carrier_id')))
            ->when($request->filled('origin_id'), fn ($q) => $q->where('origin_id', (int) $request->input('origin_id')))
            ->when($request->filled('destination_id'), fn ($q) => $q->where('destination_id', (int) $request->input('destination_id')))
            ->when($request->input('delay') === 'delayed', fn ($q) => $q->where('is_delayed', true))
            ->when($request->input('delay') === 'ontime', fn ($q) => $q->where('is_delayed', false))
            ->when($from, fn ($q) => $q->where('expected_arrival_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('expected_arrival_at', '<=', $to->copy()->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('shipment_number', 'like', $term)
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('shipments.index', [
            'shipments' => $shipments,
            'statuses'  => ShipmentService::STATUSES,
            'carriers'  => Carrier::orderBy('name')->get(),
            'locations' => Location::orderBy('country')->orderBy('city')->get(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Shipment::class);

        return view('shipments.create', $this->formData($request->user()));
    }

    public function store(ShipmentRequest $request)
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

        $shipment = $this->service->create($data, $items, $user);

        return redirect()->route('shipments.show', $shipment)
            ->with('status', 'Shipment berhasil dibuat: ' . $shipment->shipment_number);
    }

    public function show(Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        $shipment->load([
            'customer', 'carrier', 'origin', 'destination', 'items',
            'exceptions'     => fn ($q) => $q->orderByDesc('detected_at'),
            'trackingEvents' => fn ($q) => $q->with(['location', 'creator'])->orderBy('occurred_at')->orderBy('id'),
        ]);

        return view('shipments.show', [
            'shipment'       => $shipment,
            'nextStatuses'   => $this->service->allowedNext($shipment->status),
            'locations'      => Location::orderBy('country')->orderBy('city')->get(),
            'currentEvent'   => $this->service->currentLocationEvent($shipment),
            'delayInfo'      => app(EtaService::class)->explain($shipment),
            'mapData'        => app(MapService::class)->forShipment($shipment),
            'eventTypes'     => config('cargotrace.event_types'),
            'exceptionTypes' => config('cargotrace.exception_types'),
        ]);
    }

    public function edit(Request $request, Shipment $shipment)
    {
        Gate::authorize('update', $shipment);
        $shipment->load('items');

        return view('shipments.edit', $this->formData($request->user(), $shipment) + ['shipment' => $shipment]);
    }

    public function update(ShipmentRequest $request, Shipment $shipment)
    {
        Gate::authorize('update', $shipment);

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->update($shipment, $data, $items, $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Shipment berhasil diperbarui.');
    }

    public function updateStatus(ShipmentStatusRequest $request, Shipment $shipment)
    {
        Gate::authorize('changeStatus', $shipment);

        try {
            $this->service->changeStatus(
                $shipment,
                $request->validated('status'),
                $request->user(),
                $request->validated('location_id'),
                $request->validated('note'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Status shipment berhasil diperbarui.');
    }

    public function destroy(Request $request, Shipment $shipment)
    {
        Gate::authorize('delete', $shipment);

        $this->service->delete($shipment, $request->user());

        return redirect()->route('shipments.index')->with('status', 'Shipment berhasil dihapus.');
    }

    private function formData(User $user, ?Shipment $shipment = null): array
    {
        return [
            'carriers' => Carrier::where('is_active', true)
                ->when($shipment, fn ($q) => $q->orWhere('id', $shipment->carrier_id))
                ->orderBy('name')->get(),
            'locations' => Location::orderBy('country')->orderBy('city')->get(),
            'customers' => $user->isStaff()
                ? User::where('role', 'customer')->orderBy('name')->get()
                : collect(),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}