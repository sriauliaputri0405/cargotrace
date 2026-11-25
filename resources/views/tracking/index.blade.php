<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tracking</h2>
    </x-slot>

    <form method="GET" action="{{ route('tracking.index') }}" class="flex gap-3 mb-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nomor shipment"
               class="border-gray-300 rounded-md shadow-sm text-sm w-72">
        <x-primary-button>Cari</x-primary-button>
        <a href="{{ route('tracking.index') }}" class="self-center text-sm text-gray-500">Reset</a>
    </form>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3">No. Shipment</th>
                    <th class="p-3">Rute</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Lokasi terkini</th>
                    <th class="p-3">ETA</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shipments as $s)
                    <tr class="border-t">
                        <td class="p-3">{{ $s->shipment_number }}</td>
                        <td class="p-3">{{ $s->origin->city }} → {{ $s->destination->city }}</td>
                        <td class="p-3"><x-status-badge :shipment="$s" /></td>
                        <td class="p-3">
                            @if ($s->currentEvent)
                                {{ $s->currentEvent->location->name }}, {{ $s->currentEvent->location->city }}
                                <span class="text-xs text-gray-500">({{ $s->currentEvent->occurred_at->format('d M H:i') }})</span>
                            @else
                                <span class="text-gray-400">Belum ada</span>
                            @endif
                        </td>
                        <td class="p-3">{{ ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M Y H:i') }}</td>
                        <td class="p-3"><a href="{{ route('shipments.show', $s) }}" class="text-indigo-600">Timeline</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-3 text-gray-500">Tidak ada shipment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $shipments->links() }}</div>
</x-app-layout>