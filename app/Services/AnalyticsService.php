<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Filter yang didukung: customer_id, carrier_id, from, to (Carbon, berdasarkan tanggal dibuat).
     */
    public function shipmentQuery(array $filters = []): Builder
    {
        return Shipment::query()
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('shipments.customer_id', $v))
            ->when($filters['carrier_id'] ?? null, fn ($q, $v) => $q->where('shipments.carrier_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('shipments.created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('shipments.created_at', '<=', $v));
    }

    private function exceptionQuery(array $filters): Builder
    {
        return ShipmentException::query()
            ->whereIn('shipment_id', $this->shipmentQuery($filters)->select('shipments.id'));
    }

    public function summary(array $filters = []): array
    {
        $total = $this->shipmentQuery($filters)->count();
        $delayed = $this->shipmentQuery($filters)->where('is_delayed', true)->count();
        $withException = $this->shipmentQuery($filters)->whereHas('exceptions')->count();

        return [
            'total'           => $total,
            'in_transit'      => $this->shipmentQuery($filters)->where('status', 'IN_TRANSIT')->count(),
            'delivered'       => $this->shipmentQuery($filters)->where('status', 'DELIVERED')->count(),
            'delayed'         => $delayed,
            'open_exceptions' => $this->exceptionQuery($filters)->where('status', 'OPEN')->count(),
            'delay_rate'      => $total ? round($delayed / $total * 100, 1) : null,
            'exception_rate'  => $total ? round($withException / $total * 100, 1) : null,
        ];
    }

    /** Jumlah shipment per status, selalu berisi semua status (0 kalau kosong). */
    public function statusCounts(array $filters = []): array
    {
        $counts = $this->shipmentQuery($filters)
            ->selectRaw('shipments.status as status, count(*) as total')
            ->groupBy('shipments.status')
            ->pluck('total', 'status');

        return collect(ShipmentService::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /** Jumlah total, jumlah terlambat, dan delay rate per carrier. */
    public function carrierDelay(array $filters = []): Collection
    {
    return $this->shipmentQuery($filters)
        ->join('carriers', 'carriers.id', '=', 'shipments.carrier_id')
        ->selectRaw('carriers.id as carrier_id, carriers.name as name, count(*) as total, sum(case when shipments.is_delayed = 1 then 1 else 0 end) as delayed_total')
        ->groupBy('carriers.id', 'carriers.name')
        ->orderBy('carriers.name')
        ->get()
        ->map(fn ($row) => [
            'name'    => $row->name,
            'total'   => (int) $row->total,
            'delayed' => (int) $row->delayed_total,
            'rate'    => $row->total ? round($row->delayed_total / $row->total * 100, 1) : 0.0,
        ]);
    }

    public function exceptionsByType(array $filters = []): Collection
    {
        return $this->exceptionQuery($filters)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');
    }

    /** Nilai null berarti belum ada data untuk dihitung. */
    public function kpis(array $filters = []): array
    {
        $deliveredTotal = $this->shipmentQuery($filters)->where('status', 'DELIVERED')->count();
        $deliveredOnTime = $this->shipmentQuery($filters)->where('status', 'DELIVERED')->where('is_delayed', false)->count();

        $avgDelay = $this->shipmentQuery($filters)->where('is_delayed', true)->avg('delay_hours');

        $resolved = $this->exceptionQuery($filters)
            ->whereNotNull('resolved_at')
            ->get(['detected_at', 'resolved_at']);

        $avgResolution = $resolved->isEmpty()
            ? null
            : round($resolved->avg(fn ($e) => abs($e->detected_at->diffInMinutes($e->resolved_at))) / 60, 1);

        return [
            'on_time_rate'         => $deliveredTotal ? round($deliveredOnTime / $deliveredTotal * 100, 1) : null,
            'avg_delay'            => $avgDelay === null ? null : round((float) $avgDelay, 1),
            'exception_resolution' => $avgResolution,
        ];
    }

    /** Siap tampil: label, nilai berformat, dan definisi tiap KPI. */
    public function kpiCards(array $kpis): array
    {
        $percent = fn ($v) => $v === null ? '-' : number_format($v, 1) . '%';
        $hours = fn ($v) => $v === null ? '-' : number_format($v, 1) . ' jam';

        return [
            [
                'key'        => 'on_time_rate',
                'label'      => 'On-time delivery rate',
                'value'      => $percent($kpis['on_time_rate']),
                'definition' => 'Shipment DELIVERED yang tidak terlambat, dibagi total shipment DELIVERED.',
            ],
            [
                'key'        => 'avg_delay',
                'label'      => 'Average delay duration',
                'value'      => $hours($kpis['avg_delay']),
                'definition' => 'Rata-rata jam keterlambatan dari shipment yang ditandai terlambat.',
            ],
            [
                'key'        => 'exception_resolution',
                'label'      => 'Exception resolution time',
                'value'      => $hours($kpis['exception_resolution']),
                'definition' => 'Rata-rata jam dari exception terdeteksi sampai RESOLVED.',
            ],
        ];
    }
}