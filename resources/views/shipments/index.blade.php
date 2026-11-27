<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shipments</h2>
    </x-slot>

    @php
        $sortUrl = function (string $column) {
            $current = request('sort', 'created_at');
            $next = ($current === $column && request('direction', 'desc') === 'asc') ? 'desc' : 'asc';

            return route('shipments.index', array_merge(request()->query(), [
                'sort' => $column, 'direction' => $next, 'page' => null,
            ]));
        };
        $arrow = fn (string $column) => request('sort', 'created_at') === $column
            ? (request('direction', 'desc') === 'asc' ? ' ↑' : ' ↓')
            : '';
        $field = 'block w-full border-gray-300 rounded-md shadow-sm text-sm';
    @endphp

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('shipments.index') }}" class="bg-white shadow-sm rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="No. shipment / customer" class="{{ $field }}">

            <select name="status" class="{{ $field }}">
                <option value="">Semua status</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ str_replace('_', ' ', $st) }}</option>
                @endforeach
            </select>

            <select name="carrier_id" class="{{ $field }}">
                <option value="">Semua carrier</option>
                @foreach ($carriers as $c)
                    <option value="{{ $c->id }}" @selected((string) request('carrier_id') === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <select name="delay" class="{{ $field }}">
                <option value="">Semua (terlambat atau tidak)</option>
                <option value="delayed" @selected(request('delay') === 'delayed')>Hanya terlambat</option>
                <option value="ontime" @selected(request('delay') === 'ontime')>Hanya tidak terlambat</option>
            </select>

            <select name="origin_id" class="{{ $field }}">
                <option value="">Semua asal</option>
                @foreach ($locations as $l)
                    <option value="{{ $l->id }}" @selected((string) request('origin_id') === (string) $l->id)>{{ $l->city }} ({{ $l->country }})</option>
                @endforeach
            </select>

            <select name="destination_id" class="{{ $field }}">
                <option value="">Semua tujuan</option>
                @foreach ($locations as $l)
                    <option value="{{ $l->id }}" @selected((string) request('destination_id') === (string) $l->id)>{{ $l->city }} ({{ $l->country }})</option>
                @endforeach
            </select>

            <div>
                <label class="text-xs text-gray-500">Tiba (rencana) dari</label>
                <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="text-xs text-gray-500">Tiba (rencana) sampai</label>
                <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}">
            </div>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <x-primary-button>Filter</x-primary-button>
            <a href="{{ route('shipments.index') }}" class="text-sm text-gray-500">Reset</a>
            <a href="{{ route('shipments.create') }}" class="ms-auto px-4 py-2 bg-gray-800 text-white rounded text-sm">Buat Shipment</a>
        </div>
    </form>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3"><a href="{{ $sortUrl('shipment_number') }}">No. Shipment{{ $arrow('shipment_number') }}</a></th>
                    @if (auth()->user()->isStaff())
                        <th class="p-3">Customer</th>
                    @endif
                    <th class="p-3">Carrier</th>
                    <th class="p-3">Rute</th>
                    <th class="p-3"><a href="{{ $sortUrl('expected_arrival_at') }}">ETA{{ $arrow('expected_arrival_at') }}</a></th>
                    <th class="p-3">Biaya</th>
                    <th class="p-3"><a href="{{ $sortUrl('status') }}">Status{{ $arrow('status') }}</a></th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shipments as $s)
                    <tr class="border-t">
                        <td class="p-3">{{ $s->shipment_number }}</td>
                        @if (auth()->user()->isStaff())
                            <td class="p-3">{{ $s->customer->name }}</td>
                        @endif
                        <td class="p-3">{{ $s->carrier->name }}</td>
                        <td class="p-3">{{ $s->origin->city }} → {{ $s->destination->city }}</td>
                        <td class="p-3">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('Y-m-d') }}</td>
                        <td class="p-3">
                            @if ($s->freight_cost !== null)
                                {{ number_format($s->freight_cost, 2) }} {{ $s->currency_code }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="p-3"><x-status-badge :shipment="$s" /></td>
                        <td class="p-3"><a href="{{ route('shipments.show', $s) }}" class="text-indigo-600">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-3 text-gray-500">Tidak ada shipment yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $shipments->links() }}</div>
</x-app-layout>