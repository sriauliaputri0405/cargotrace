<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reports</h2>
    </x-slot>

    @php
        $pct = fn ($v) => $v === null ? '-' : number_format($v, 1) . '%';
        $field = 'block w-full border-gray-300 rounded-md shadow-sm text-sm';
    @endphp

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('reports.index') }}" class="bg-white shadow-sm rounded-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-gray-500">Dibuat dari tanggal</label>
                <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="text-xs text-gray-500">Sampai tanggal</label>
                <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="text-xs text-gray-500">Carrier</label>
                <select name="carrier_id" class="{{ $field }}">
                    <option value="">Semua carrier</option>
                    @foreach ($carriers as $c)
                        <option value="{{ $c->id }}" @selected((string) request('carrier_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <x-primary-button>Terapkan</x-primary-button>
            <a href="{{ route('reports.index') }}" class="text-sm text-gray-500">Reset</a>
            <a href="{{ route('reports.export', request()->query()) }}"
               class="ms-auto px-4 py-2 border rounded text-sm">Export CSV</a>
        </div>
    </form>

    <div class="space-y-6">

        {{-- KPI: ketiganya ditampilkan, KPI utama kelompok diberi tanda --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($kpiCards as $kpi)
                <div class="bg-white rounded-lg shadow-sm p-4 {{ $kpi['key'] === $primaryKpi ? 'border-l-4 border-indigo-500' : '' }}">
                    <div class="text-sm text-gray-500">
                        {{ $kpi['label'] }}
                        @if ($kpi['key'] === $primaryKpi)<span class="text-xs">(KPI utama)</span>@endif
                    </div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $kpi['value'] }}</div>
                    <p class="mt-1 text-xs text-gray-400">{{ $kpi['definition'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach ([
                ['Total shipment', $summary['total']],
                ['In transit', $summary['in_transit']],
                ['Delivered', $summary['delivered']],
                ['Delay rate', $pct($summary['delay_rate'])],
                ['Exception rate', $pct($summary['exception_rate'])],
            ] as [$label, $value])
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800 mb-2">Per status</h3>
                <table class="min-w-full text-sm">
                    @foreach ($statusCounts as $status => $total)
                        <tr class="border-t"><td class="py-1">{{ str_replace('_', ' ', $status) }}</td><td class="py-1 text-right">{{ $total }}</td></tr>
                    @endforeach
                </table>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800 mb-2">Delay per carrier</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500"><tr><th class="py-1">Carrier</th><th class="py-1 text-right">Terlambat</th><th class="py-1 text-right">Rate</th></tr></thead>
                    @forelse ($carrierRows as $row)
                        <tr class="border-t">
                            <td class="py-1">{{ $row['name'] }}</td>
                            <td class="py-1 text-right">{{ $row['delayed'] }} / {{ $row['total'] }}</td>
                            <td class="py-1 text-right">{{ $pct($row['rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-500">Belum ada data.</td></tr>
                    @endforelse
                </table>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-800 mb-2">Exception per jenis</h3>
                <table class="min-w-full text-sm">
                    @forelse ($exceptionRows as $type => $total)
                        <tr class="border-t"><td class="py-1">{{ str_replace('_', ' ', $type) }}</td><td class="py-1 text-right">{{ $total }}</td></tr>
                    @empty
                        <tr><td class="py-2 text-gray-500">Belum ada exception.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</x-app-layout>