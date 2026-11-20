<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shipments</h2>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <form method="GET" action="{{ route('shipments.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari no. shipment / customer"
                   class="border-gray-300 rounded-md shadow-sm text-sm">
            <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                <option value="">Semua status</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ str_replace('_', ' ', $st) }}</option>
                @endforeach
            </select>
            <label class="flex items-center text-sm text-gray-700">
                <input type="checkbox" name="delayed" value="1" class="rounded border-gray-300"
                       @checked(request()->boolean('delayed'))>
                <span class="ms-2">Hanya delayed</span>
            </label>
            <x-primary-button>Filter</x-primary-button>
            <a href="{{ route('shipments.index') }}" class="text-sm text-gray-500">Reset</a>
        </form>

        <a href="{{ route('shipments.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Buat Shipment</a>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3">No. Shipment</th>
                    @if (auth()->user()->isStaff())
                        <th class="p-3">Customer</th>
                    @endif
                    <th class="p-3">Carrier</th>
                    <th class="p-3">Rute</th>
                    <th class="p-3">ETA</th>
                    <th class="p-3">Biaya</th>
                    <th class="p-3">Status</th>
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
                    <tr><td colspan="8" class="p-3 text-gray-500">Belum ada shipment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $shipments->links() }}</div>
</x-app-layout>