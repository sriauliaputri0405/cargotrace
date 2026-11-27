<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Services\AnalyticsService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private AnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('reports.index', [
            'summary'       => $this->analytics->summary($filters),
            'kpiCards'      => $this->analytics->kpiCards($this->analytics->kpis($filters)),
            'primaryKpi'    => config('cargotrace.primary_kpi'),
            'carrierRows'   => $this->analytics->carrierDelay($filters),
            'statusCounts'  => $this->analytics->statusCounts($filters),
            'exceptionRows' => $this->analytics->exceptionsByType($filters),
            'carriers'      => Carrier::orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'cargotrace-shipments-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar

            fputcsv($out, [
                'shipment_number', 'customer', 'carrier', 'origin', 'destination', 'status',
                'expected_arrival_at', 'estimated_arrival_at', 'is_delayed', 'delay_hours',
                'delay_severity', 'freight_cost', 'currency', 'freight_cost_idr', 'created_at',
            ]);

            $this->analytics->shipmentQuery($filters)
                ->with(['customer', 'carrier', 'origin', 'destination'])
                ->chunkById(200, function ($chunk) use ($out) {
                    foreach ($chunk as $s) {
                        fputcsv($out, array_map([Csv::class, 'safe'], [
                            $s->shipment_number,
                            $s->customer->name,
                            $s->carrier->name,
                            $s->origin->city . ', ' . $s->origin->country,
                            $s->destination->city . ', ' . $s->destination->country,
                            $s->status,
                            $s->expected_arrival_at->format('Y-m-d H:i'),
                            $s->estimated_arrival_at?->format('Y-m-d H:i'),
                            (bool) $s->is_delayed,
                            (int) $s->delay_hours,
                            $s->delay_severity,
                            $s->freight_cost === null ? null : (float) $s->freight_cost,
                            $s->currency_code,
                            $s->freight_cost_idr === null ? null : (float) $s->freight_cost_idr,
                            $s->created_at->format('Y-m-d H:i'),
                        ]));
                    }
                }, 'shipments.id', 'id');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
        ]);

        return array_filter([
            'from'       => ! empty($data['from']) ? \Carbon\Carbon::parse($data['from'])->startOfDay() : null,
            'to'         => ! empty($data['to']) ? \Carbon\Carbon::parse($data['to'])->endOfDay() : null,
            'carrier_id' => $data['carrier_id'] ?? null,
        ]);
    }
}