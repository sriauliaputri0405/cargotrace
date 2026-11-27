<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    @php
        $cards = [
            ['Total Shipments', $summary['total'],           'text-gray-900'],
            ['In Transit',      $summary['in_transit'],      'text-blue-600'],
            ['Delivered',       $summary['delivered'],       'text-green-600'],
            ['Delayed',         $summary['delayed'],         'text-amber-600'],
            ['Open Exceptions', $summary['open_exceptions'], 'text-red-600'],
        ];
        $pct = fn ($v) => $v === null ? '-' : number_format($v, 1) . '%';
    @endphp

    <div class="space-y-6">

        {{-- Kartu ringkasan (angka dari database) --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($cards as [$label, $value, $color])
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-3xl font-semibold {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        {{-- KPI --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-indigo-500">
                <div class="text-sm text-gray-500">{{ $primaryKpi['label'] }} <span class="text-xs">(KPI utama)</span></div>
                <div class="mt-1 text-3xl font-semibold text-indigo-700">{{ $primaryKpi['value'] }}</div>
                <p class="mt-1 text-xs text-gray-400">{{ $primaryKpi['definition'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="text-sm text-gray-500">Delay rate</div>
                <div class="mt-1 text-3xl font-semibold text-amber-600">{{ $pct($summary['delay_rate']) }}</div>
                <p class="mt-1 text-xs text-gray-400">Shipment terlambat dibagi total shipment.</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="text-sm text-gray-500">Exception rate</div>
                <div class="mt-1 text-3xl font-semibold text-red-600">{{ $pct($summary['exception_rate']) }}</div>
                <p class="mt-1 text-xs text-gray-400">Shipment yang pernah punya exception dibagi total shipment.</p>
            </div>
        </div>

        {{-- Grafik dan peta --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Shipments by Status</h3>
                <div class="mt-3 relative h-56">
                    <canvas id="chart-status" data-chart="{{ json_encode($statusChart) }}"></canvas>
                    <p data-chart-empty class="hidden absolute inset-0 flex items-center justify-center text-sm text-gray-500">
                        Belum ada data shipment.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Delay Rate by Carrier</h3>
                <p class="text-xs text-gray-500">
                    Sebaran shipment terlambat per carrier. Rata-rata delay rate: {{ $pct($summary['delay_rate']) }}
                </p>
                <div class="mt-3 relative h-44">
                    <canvas id="chart-carrier" data-chart="{{ json_encode($carrierChart) }}"></canvas>
                    <p data-chart-empty class="hidden absolute inset-0 flex items-center justify-center text-sm text-gray-500">
                        Belum ada shipment terlambat.
                    </p>
                </div>
                <ul class="mt-2 text-xs text-gray-600 space-y-0.5">
                    @foreach ($carrierRows as $row)
                        <li>{{ $row['name'] }}: {{ $pct($row['rate']) }} ({{ $row['delayed'] }} dari {{ $row['total'] }})</li>
                    @endforeach
                </ul>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Shipment Map</h3>
                <div class="mt-3">
                    <x-shipment-map :data="$mapData" height="h-56" />
                </div>
            </div>
        </div>

        {{-- Shipment terbaru --}}
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Recent Shipments</h3>
                <a href="{{ route('shipments.index') }}" class="text-sm text-indigo-600">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="p-3">Shipment No.</th>
                            <th class="p-3">Customer</th>
                            <th class="p-3">Carrier</th>
                            <th class="p-3">Origin</th>
                            <th class="p-3">Destination</th>
                            <th class="p-3">ETA</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $s)
                            <tr class="border-t">
                                <td class="p-3"><a href="{{ route('shipments.show', $s) }}" class="text-indigo-600">{{ $s->shipment_number }}</a></td>
                                <td class="p-3">{{ $s->customer->name }}</td>
                                <td class="p-3">{{ $s->carrier->name }}</td>
                                <td class="p-3">{{ $s->origin->city }}</td>
                                <td class="p-3">{{ $s->destination->city }}</td>
                                <td class="p-3">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('Y-m-d') }}</td>
                                <td class="p-3"><x-status-badge :shipment="$s" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-4 text-gray-500">Belum ada shipment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>