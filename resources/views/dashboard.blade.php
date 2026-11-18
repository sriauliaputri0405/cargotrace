<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    @php
        $cards = [
            ['Total Shipments',  $stats['total'],      'text-gray-900'],
            ['In Transit',       $stats['in_transit'], 'text-blue-600'],
            ['Delivered',        $stats['delivered'],  'text-green-600'],
            ['Delayed',          $stats['delayed'],    'text-amber-600'],
            ['Open Exceptions',  $openExceptions,      'text-red-600'],
        ];

        $badge = [
            'CREATED'    => 'bg-gray-100 text-gray-700',
            'BOOKED'     => 'bg-sky-100 text-sky-700',
            'PICKED_UP'  => 'bg-indigo-100 text-indigo-700',
            'IN_TRANSIT' => 'bg-blue-100 text-blue-700',
            'DELIVERED'  => 'bg-green-100 text-green-700',
        ];
    @endphp

    <div class="space-y-6">

        {{-- Kartu ringkasan: angka dari database --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($cards as [$label, $value, $color])
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-3xl font-semibold {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        {{-- Area grafik dan peta (diisi di tahap berikutnya) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Shipments by Status</h3>
                <div class="mt-3 h-56 flex items-center justify-center border-2 border-dashed rounded text-sm text-gray-400">
                    Grafik batang (Chart.js), Tahap H
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Delay Rate by Carrier</h3>
                <div class="mt-3 h-56 flex items-center justify-center border-2 border-dashed rounded text-sm text-gray-400">
                    Grafik donut (Chart.js), Tahap H
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800">Shipment Map</h3>
                <div class="mt-3 h-56 flex items-center justify-center border-2 border-dashed rounded text-sm text-gray-400">
                    Peta Leaflet, Tahap G
                </div>
            </div>
        </div>

        {{-- Recent shipments: data asli dari database --}}
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-gray-800">Recent Shipments</h3>
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
                                <td class="p-3">{{ $s->shipment_number }}</td>
                                <td class="p-3">{{ $s->customer->name }}</td>
                                <td class="p-3">{{ $s->carrier->name }}</td>
                                <td class="p-3">{{ $s->origin->city }}</td>
                                <td class="p-3">{{ $s->destination->city }}</td>
                                <td class="p-3">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('Y-m-d') }}</td>
                                <td class="p-3">
                                    @if ($s->is_delayed)
                                        <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">DELAYED</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs {{ $badge[$s->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ str_replace('_', ' ', $s->status) }}
                                        </span>
                                    @endif
                                </td>
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