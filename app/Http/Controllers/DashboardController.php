<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Customer hanya melihat shipment miliknya; admin dan operator melihat semua
        $base = Shipment::query()
            ->when($user->isCustomer(), fn ($q) => $q->where('customer_id', $user->id));

        $stats = [
            'total'      => (clone $base)->count(),
            'in_transit' => (clone $base)->where('status', 'IN_TRANSIT')->count(),
            'delivered'  => (clone $base)->where('status', 'DELIVERED')->count(),
            'delayed'    => (clone $base)->where('is_delayed', true)->count(),
        ];

        $openExceptions = ShipmentException::where('status', 'OPEN')
            ->when($user->isCustomer(), fn ($q) => $q->whereHas(
                'shipment', fn ($s) => $s->where('customer_id', $user->id)
            ))
            ->count();

        $recent = (clone $base)
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', compact('stats', 'openExceptions', 'recent'));
    }
}