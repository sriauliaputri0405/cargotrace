<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $shipment->shipment_number }}</h2>
    </x-slot>

    @php
        $isStaff = auth()->user()->isStaff();
        $sevColor = [
            'LOW'    => 'bg-yellow-100 text-yellow-800',
            'MEDIUM' => 'bg-orange-100 text-orange-800',
            'HIGH'   => 'bg-red-100 text-red-800',
        ];
        $nextExceptionStatus = [
            'OPEN'          => ['INVESTIGATING' => 'Investigasi', 'RESOLVED' => 'Selesaikan'],
            'INVESTIGATING' => ['RESOLVED' => 'Selesaikan'],
        ];
        $field = 'block w-full border-gray-300 rounded-md shadow-sm text-sm';
    @endphp

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
            <ul class="list-disc ms-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Kolom kiri --}}
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
                    <dt class="text-gray-500">Lokasi terkini</dt>
                    <dd>
                        @if ($currentEvent)
                            {{ $currentEvent->location->name }}, {{ $currentEvent->location->city }}
                            <span class="text-gray-500">({{ $currentEvent->occurred_at->format('d M Y H:i') }})</span>
                        @else
                            <span class="text-gray-400">Belum ada</span>
                        @endif
                    </dd>
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

            {{-- ETA dan keterlambatan: alasan dan nilai yang dipakai --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">ETA dan keterlambatan</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    @foreach ($delayInfo as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                {{-- Peta rute --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Peta rute</h3>
                <x-shipment-map :data="$mapData" />
                <p class="mt-2 text-xs text-gray-500">
                    Hijau: asal · Merah: tujuan · Biru: titik tracking · Kuning: posisi terakhir. Garis putus-putus: rute rencana.
                </p>
            </div>

            {{-- Cuaca: dimuat terpisah agar halaman tidak menunggu layanan eksternal --}}
            <div class="bg-white shadow-sm rounded-lg p-6"
                x-data="{ state: 'loading', info: {}, error: '' }"
                x-init="fetch('{{ route('shipments.weather', $shipment) }}', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json().then(j => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (ok && j.ok) { info = j; state = 'ok'; }
                        else { error = j.message ?? 'Data cuaca tidak tersedia.'; state = 'error'; }
                    })
                    .catch(() => { error = 'Layanan cuaca tidak dapat dihubungi.'; state = 'error'; })">
                <h3 class="font-semibold text-gray-800 mb-3">Cuaca di lokasi terkini</h3>

                <p x-show="state === 'loading'" class="text-sm text-gray-500">Memuat data cuaca...</p>
                <p x-show="state === 'error'" x-cloak x-text="error" class="text-sm text-amber-700"></p>

                <div x-show="state === 'ok'" x-cloak class="text-sm text-gray-700 space-y-1">
                    <p x-text="info.place"></p>
                    <p>
                        <span x-text="info.description"></span>,
                        <span x-text="info.temperature"></span> <span x-text="info.temperature_unit"></span>,
                        angin <span x-text="info.wind_speed"></span> <span x-text="info.wind_unit"></span>
                    </p>
                    <p class="text-xs text-gray-400">Waktu data: <span x-text="info.time"></span></p>
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    Informasi cuaca hanya referensi dan bukan bukti atau prediksi keterlambatan.
                    Data: <a href="https://open-meteo.com/" class="underline" target="_blank" rel="noopener">Open-Meteo.com</a>.
                </p>
            </div>
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

            {{-- Exceptions --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Exceptions</h3>

                @forelse ($shipment->exceptions as $ex)
                    <div class="border-t py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <span class="font-semibold">{{ str_replace('_', ' ', $ex->type) }}</span>
                                <span class="ms-2 px-2 py-0.5 rounded text-xs {{ $sevColor[$ex->severity] ?? '' }}">{{ $ex->severity }}</span>
                                <span class="ms-1 text-xs text-gray-500">{{ $ex->status }}</span>
                            </div>

                            @if ($isStaff)
                                <div class="flex gap-3">
                                    @foreach ($nextExceptionStatus[$ex->status] ?? [] as $value => $label)
                                        <form method="POST" action="{{ route('exceptions.status', $ex) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $value }}">
                                            <button class="text-xs text-indigo-600">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($ex->description)
                            <p class="text-gray-600 mt-1">{{ $ex->description }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1">
                            Terdeteksi {{ $ex->detected_at->format('d M Y H:i') }}
                            @if ($ex->resolved_at) · selesai {{ $ex->resolved_at->format('d M Y H:i') }} @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Tidak ada exception.</p>
                @endforelse

                @if ($isStaff)
                    <form method="POST" action="{{ route('exceptions.store', $shipment) }}"
                          class="mt-4 pt-4 border-t grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf
                        <select name="type" class="{{ $field }}">
                            @foreach ($exceptionTypes as $type)
                                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                        <select name="severity" class="{{ $field }}">
                            @foreach (['LOW', 'MEDIUM', 'HIGH'] as $sv)
                                <option value="{{ $sv }}">{{ $sv }}</option>
                            @endforeach
                        </select>
                        <textarea name="description" rows="2" placeholder="Deskripsi (opsional)"
                                  class="{{ $field }} md:col-span-2"></textarea>
                        <div class="md:col-span-2"><x-primary-button>Laporkan exception</x-primary-button></div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-6">

            @can('changeStatus', $shipment)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Perbarui status</h3>

                    @if (count($nextStatuses))
                        <form method="POST" action="{{ route('shipments.status', $shipment) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <select name="status" class="{{ $field }}">
                                @foreach ($nextStatuses as $st)
                                    <option value="{{ $st }}">{{ str_replace('_', ' ', $st) }}</option>
                                @endforeach
                            </select>
                            <select name="location_id" class="{{ $field }}">
                                <option value="">Lokasi (opsional)</option>
                                @foreach ($locations as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}, {{ $l->city }}</option>
                                @endforeach
                            </select>
                            <textarea name="note" rows="2" placeholder="Catatan (opsional)" class="{{ $field }}"></textarea>
                            <x-primary-button>Simpan status</x-primary-button>
                        </form>
                    @endif
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Perbarui ETA</h3>
                    <form method="POST" action="{{ route('shipments.eta', $shipment) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <input type="datetime-local" name="estimated_arrival_at" class="{{ $field }}"
                               value="{{ old('estimated_arrival_at', optional($shipment->estimated_arrival_at)->format('Y-m-d\TH:i')) }}">
                        <input type="text" name="note" placeholder="Catatan (opsional)" class="{{ $field }}">
                        <x-primary-button>Simpan ETA</x-primary-button>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Tambah event tracking</h3>
                    <form method="POST" action="{{ route('tracking.store', $shipment) }}" class="space-y-3">
                        @csrf
                        <select name="event_type" class="{{ $field }}">
                            @foreach ($eventTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('event_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="location_id" class="{{ $field }}">
                            <option value="">Lokasi (opsional)</option>
                            @foreach ($locations as $l)
                                <option value="{{ $l->id }}" @selected((string) old('location_id') === (string) $l->id)>
                                    {{ $l->name }}, {{ $l->city }}
                                </option>
                            @endforeach
                        </select>
                        <input type="datetime-local" name="occurred_at" class="{{ $field }}"
                               value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="any" name="latitude" placeholder="Latitude" class="{{ $field }}" value="{{ old('latitude') }}">
                            <input type="number" step="any" name="longitude" placeholder="Longitude" class="{{ $field }}" value="{{ old('longitude') }}">
                        </div>
                        <p class="text-xs text-gray-500">Koordinat kosong: dipakai koordinat lokasi yang dipilih.</p>
                        <textarea name="description" rows="2" placeholder="Deskripsi (opsional)" class="{{ $field }}">{{ old('description') }}</textarea>
                        <x-primary-button>Tambah event</x-primary-button>
                    </form>
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Timeline</h3>

                <ol class="relative border-s border-gray-200 ms-3">
                    @forelse ($shipment->trackingEvents as $event)
                        <li class="mb-6 ms-4">
                            <span class="absolute w-3 h-3 rounded-full -start-1.5 mt-1.5 {{ $event->is_exception ? 'bg-red-600' : 'bg-indigo-600' }}"></span>
                            <time class="text-xs text-gray-500">{{ $event->occurred_at->format('d M Y H:i') }}</time>
                            <p class="text-sm font-semibold {{ $event->is_exception ? 'text-red-700' : '' }}">
                                {{ str_replace('_', ' ', $event->event_type) }}
                                @if ($event->location) · {{ $event->location->city }} @endif
                            </p>
                            @if ($event->description)
                                <p class="text-sm text-gray-600">{{ $event->description }}</p>
                            @endif
                            @if ($event->latitude !== null)
                                <p class="text-xs text-gray-400">{{ $event->latitude }}, {{ $event->longitude }}</p>
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