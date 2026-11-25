<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExceptionRequest;
use App\Http\Requests\ExceptionStatusRequest;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use DomainException;
use Illuminate\Http\Request;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    public function index(Request $request)
    {
        $exceptions = ShipmentException::query()
            ->with(['shipment.customer', 'creator'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->latest('detected_at')
            ->paginate(10)
            ->withQueryString();

        return view('exceptions.index', [
            'exceptions' => $exceptions,
            'statuses'   => ['OPEN', 'INVESTIGATING', 'RESOLVED'],
            'severities' => ['LOW', 'MEDIUM', 'HIGH'],
        ]);
    }

    public function store(ExceptionRequest $request, Shipment $shipment)
    {
        $this->exceptions->report($shipment, $request->validated(), $request->user());

        return back()->with('status', 'Exception dilaporkan.');
    }

    public function updateStatus(ExceptionStatusRequest $request, ShipmentException $exception)
    {
        try {
            $this->exceptions->transition($exception, $request->validated('status'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Status exception diperbarui.');
    }
}