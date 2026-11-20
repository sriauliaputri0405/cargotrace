<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $shipment->shipment_number }}</h2>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Kolom kiri: detail dan barang --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">Detail</h3>
                    <x-status-badge :shipment="$shipment" />
                </div>

                <dl class="grid grid-cols-2 gap-y-3 text-sm">
                    <dt class="text-gray-500">Customer</dt><dd>{{ $shipment->customer->name }}</dd>
                    <dt class="text-gray-500">Carrier</dt><dd>{{ $shipment->carrier->name }}</dd>
                    <dt class="text-gray-500">Asal</dt><dd>{{ $shipment->origin->name }}, {{ $shipment->origin->city }}</dd>
                    <dt class="text-gray-500">Tujuan</dt><dd>{{ $shipment->destination->name }}, {{ $shipment->destination->city }}</dd>
                    <dt class="text-gray-500">Jenis kargo</dt><dd>{{ $shipment->cargo_type }}</dd>
                    <dt class="text-gray-500">Berat</dt><dd>{{ number_format($shipment->weight_kg, 2) }} kg</dd>
                    <dt class="text-gray-500">Volume</dt><dd>{{ $shipment->volume_cbm ? $shipment->volume_cbm . ' CBM' : '-' }}</dd>
                    <dt class="text-gray-500">Perkiraan berangkat</dt><dd>{{ $shipment->expected_departure_at->format('d M Y H:i') }}</dd>
                    <dt class="text-gray-500">Perkiraan tiba</dt><dd>{{ $shipment->expected_arrival_at->format('d M Y H:i') }}</dd>
                    <dt class="text-gray-500">Biaya kirim</dt>
                    <dd>
                        @if ($shipment->freight_cost !== null)
                            {{ number_format($shipment->freight_cost, 2) }} {{ $shipment->currency_code }}
                            @if ($shipment->freight_cost_idr !== null)
                                <span class="text-gray-500">(≈ Rp {{ number_format($shipment->freight_cost_idr, 0, ',', '.') }}
                                    @ kurs {{ rtrim(rtrim(number_format($shipment->exchange_rate, 8, '.', ''), '0'), '.') }})</span>
                            @endif
                        @else
                            -
                        @endif
                    </dd>
                </dl>

                <div class="mt-5 flex gap-3 text-sm">
                    @can('update', $shipment)
                        <a href="{{ route('shipments.edit', $shipment) }}" class="text-indigo-600">Ubah</a>
                    @endcan
                    @can('delete', $shipment)
                        <form method="POST" action="{{ route('shipments.destroy', $shipment) }}"
                              onsubmit="return confirm('Hapus shipment ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600">Hapus</button>
                        </form>
                    @endcan
                    <a href="{{ route('shipments.index') }}" class="text-gray-500">Kembali</a>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Barang</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr><th class="py-2">Deskripsi</th><th class="py-2">Jumlah</th><th class="py-2">Berat (kg)</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($shipment->items as $item)
                            <tr class="border-t">
                                <td class="py-2">{{ $item->description }}</td>
                                <td class="py-2">{{ $item->quantity }}</td>
                                <td class="py-2">{{ $item->weight_kg ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kolom kanan: ubah status dan timeline --}}
        <div class="space-y-6">
            @can('changeStatus', $shipment)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Perbarui status</h3>

                    @if (count($nextStatuses))
                        <form method="POST" action="{{ route('shipments.status', $shipment) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')

                            <select name="status" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                @foreach ($nextStatuses as $st)
                                    <option value="{{ $st }}">{{ str_replace('_', ' ', $st) }}</option>
                                @endforeach
                            </select>

                            <select name="location_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Lokasi (opsional)</option>
                                @foreach ($locations as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}, {{ $l->city }}</option>
                                @endforeach
                            </select>

                            <textarea name="note" rows="2" placeholder="Catatan (opsional)"
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>

                            <x-primary-button>Simpan status</x-primary-button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">Shipment sudah selesai (DELIVERED).</p>
                    @endif
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Timeline</h3>

                <ol class="relative border-s border-gray-200 ms-3">
                    @forelse ($shipment->trackingEvents as $event)
                        <li class="mb-6 ms-4">
                            <span class="absolute w-3 h-3 bg-indigo-600 rounded-full -start-1.5 mt-1.5"></span>
                            <time class="text-xs text-gray-500">{{ $event->occurred_at->format('d M Y H:i') }}</time>
                            <p class="text-sm font-semibold">
                                {{ str_replace('_', ' ', $event->event_type) }}
                                @if ($event->location) · {{ $event->location->city }} @endif
                            </p>
                            @if ($event->description)
                                <p class="text-sm text-gray-600">{{ $event->description }}</p>
                            @endif
                            <p class="text-xs text-gray-400">oleh {{ $event->creator->name }}</p>
                        </li>
                    @empty
                        <li class="ms-4 text-sm text-gray-500">Belum ada riwayat.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>