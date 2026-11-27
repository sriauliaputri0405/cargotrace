<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\MapService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics, MapService $maps)
    {
        $user = $request->user();

        // Customer hanya melihat datanya sendiri; admin dan operator melihat semua
        $filters = $user->isCustomer() ? ['customer_id' => $user->id] : [];

        $summary = $analytics->summary($filters);
        $statusCounts = $analytics->statusCounts($filters);
        $carriers = $analytics->carrierDelay($filters);
        $kpiCards = $analytics->kpiCards($analytics->kpis($filters));

        $primaryKpi = collect($kpiCards)->firstWhere('key', config('cargotrace.primary_kpi')) ?? $kpiCards[0];

        $statusChart = [
            'labels' => array_map(fn ($s) => str_replace('_', ' ', $s), array_keys($statusCounts)),
            'values' => array_values($statusCounts),
        ];

        $carrierChart = [
            'labels' => $carriers->pluck('name')->all(),
            'values' => $carriers->pluck('delayed')->all(),
            'rates'  => $carriers->pluck('rate')->all(),
            'totals' => $carriers->pluck('total')->all(),
        ];

        $recent = $analytics->shipmentQuery($filters)
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->latest('shipments.created_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'summary'      => $summary,
            'primaryKpi'   => $primaryKpi,
            'statusChart'  => $statusChart,
            'carrierChart' => $carrierChart,
            'carrierRows'  => $carriers,
            'recent'       => $recent,
            'mapData'      => $maps->forDashboard($user),
        ]);
    }
}