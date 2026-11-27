@csrf

@php
    $s = $shipment ?? null;
    $isStaff = auth()->user()->isStaff();
    $initialItems = old('items', $s
        ? $s->items->map(fn ($i) => [
            'description' => $i->description,
            'quantity'    => $i->quantity,
            'weight_kg'   => $i->weight_kg,
        ])->all()
        : [['description' => '', 'quantity' => 1, 'weight_kg' => '']]);
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    $select = 'block mt-1 w-full border-gray-300 rounded-md shadow-sm';
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    @if ($isStaff)
        <div class="md:col-span-2">
            <x-input-label for="customer_id" value="Customer" />
            <select id="customer_id" name="customer_id" class="{{ $select }}">
                <option value="">Pilih customer</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}"
                        @selected((string) old('customer_id', $s->customer_id ?? '') === (string) $c->id)>
                        {{ $c->name }} ({{ $c->email }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
        </div>
    @endif

    <div>
        <x-input-label for="carrier_id" value="Carrier" />
        <select id="carrier_id" name="carrier_id" class="{{ $select }}">
            <option value="">Pilih carrier</option>
            @foreach ($carriers as $c)
                <option value="{{ $c->id }}" @selected((string) old('carrier_id', $s->carrier_id ?? '') === (string) $c->id)>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('carrier_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="cargo_type" value="Jenis kargo" />
        <x-text-input id="cargo_type" name="cargo_type" class="block mt-1 w-full"
                      :value="old('cargo_type', $s->cargo_type ?? '')" />
        <x-input-error :messages="$errors->get('cargo_type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="origin_id" value="Asal" />
        <select id="origin_id" name="origin_id" class="{{ $select }}">
            <option value="">Pilih lokasi asal</option>
            @foreach ($locations as $l)
                <option value="{{ $l->id }}" @selected((string) old('origin_id', $s->origin_id ?? '') === (string) $l->id)>
                    {{ $l->name }}, {{ $l->city }} ({{ $l->country }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('origin_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="destination_id" value="Tujuan" />
        <select id="destination_id" name="destination_id" class="{{ $select }}">
            <option value="">Pilih lokasi tujuan</option>
            @foreach ($locations as $l)
                <option value="{{ $l->id }}" @selected((string) old('destination_id', $s->destination_id ?? '') === (string) $l->id)>
                    {{ $l->name }}, {{ $l->city }} ({{ $l->country }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="weight_kg" value="Berat total (kg)" />
        <x-text-input id="weight_kg" name="weight_kg" type="number" step="any" class="block mt-1 w-full"
                      :value="old('weight_kg', $s->weight_kg ?? '')" />
        <x-input-error :messages="$errors->get('weight_kg')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="volume_cbm" value="Volume (CBM, opsional)" />
        <x-text-input id="volume_cbm" name="volume_cbm" type="number" step="any" class="block mt-1 w-full"
                      :value="old('volume_cbm', $s->volume_cbm ?? '')" />
        <x-input-error :messages="$errors->get('volume_cbm')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="expected_departure_at" value="Perkiraan berangkat" />
        <x-text-input id="expected_departure_at" name="expected_departure_at" type="datetime-local"
                      class="block mt-1 w-full"
                      :value="old('expected_departure_at', $dt($s->expected_departure_at ?? null))" />
        <x-input-error :messages="$errors->get('expected_departure_at')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="expected_arrival_at" value="Perkiraan tiba" />
        <x-text-input id="expected_arrival_at" name="expected_arrival_at" type="datetime-local"
                      class="block mt-1 w-full"
                      :value="old('expected_arrival_at', $dt($s->expected_arrival_at ?? null))" />
        <x-input-error :messages="$errors->get('expected_arrival_at')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="freight_cost" value="Biaya kirim (opsional)" />
        <x-text-input id="freight_cost" name="freight_cost" type="number" step="any" class="block mt-1 w-full"
                      :value="old('freight_cost', $s->freight_cost ?? '')" />
        <x-input-error :messages="$errors->get('freight_cost')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="currency_code" value="Mata uang" />
        <select id="currency_code" name="currency_code" class="{{ $select }}">
            @foreach (config('currencies') as $code => $name)
                <option value="{{ $code }}"
                    @selected(old('currency_code', $s->currency_code ?? 'USD') === $code)>
                    {{ $code }} — {{ $name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('currency_code')" class="mt-2" />
    </div>

    @if ($isStaff)
    <div class="md:col-span-2" x-data="fxRate()">
        <x-input-label for="exchange_rate" value="Kurs ke IDR (1 satuan mata uang = berapa rupiah)" />
        <div class="flex gap-2">
            <x-text-input id="exchange_rate" name="exchange_rate" type="number" step="any"
                          class="block mt-1 w-full" :value="old('exchange_rate', $s->exchange_rate ?? '')" />
            <button type="button" @click="fetchRate()" :disabled="loading"
                    class="mt-1 px-3 py-2 text-sm border rounded-md whitespace-nowrap disabled:opacity-50">
                Ambil kurs terbaru
            </button>
        </div>
        <p class="mt-1 text-xs" :class="error ? 'text-red-600' : 'text-gray-500'"
           x-text="error || note || 'Kosongkan jika belum ada. Jika mata uangnya IDR, kurs otomatis 1.'"></p>
        <x-input-error :messages="$errors->get('exchange_rate')" class="mt-2" />
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('fxRate', () => ({
                loading: false,
                note: '',
                error: '',

                async fetchRate() {
                    const code = document.getElementById('currency_code').value;
                    this.loading = true;
                    this.note = '';
                    this.error = '';

                    try {
                        const response = await fetch('{{ route('fx.rate') }}?currency=' + encodeURIComponent(code), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const json = await response.json();

                        if (!response.ok || !json.ok) {
                            this.error = json.message ?? 'Kurs tidak tersedia. Isi manual.';
                            return;
                        }

                        document.getElementById('exchange_rate').value = json.rate;
                        this.note = 'Kurs ' + json.currency + ' = Rp ' + json.rate + ' (tanggal ' + json.date + ', sumber ' + json.source + ')';
                    } catch (e) {
                        this.error = 'Layanan kurs tidak dapat dihubungi. Isi kurs manual.';
                    } finally {
                        this.loading = false;
                    }
                },
            }));
        });
    </script>
@endif

{{-- Daftar barang (baris bisa ditambah dan dihapus) --}}
<div class="mt-6" x-data="{ items: @js($initialItems) }">
    <h3 class="font-semibold text-gray-800 mb-2">Barang</h3>

    <template x-for="(item, i) in items" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="text" class="col-span-6 border-gray-300 rounded-md shadow-sm text-sm"
                   :name="`items[${i}][description]`" x-model="item.description" placeholder="Deskripsi barang">
            <input type="number" min="1" class="col-span-2 border-gray-300 rounded-md shadow-sm text-sm"
                   :name="`items[${i}][quantity]`" x-model="item.quantity" placeholder="Jumlah">
            <input type="number" step="any" class="col-span-3 border-gray-300 rounded-md shadow-sm text-sm"
                   :name="`items[${i}][weight_kg]`" x-model="item.weight_kg" placeholder="Berat (kg)">
            <button type="button" class="col-span-1 text-red-600" x-show="items.length > 1"
                    @click="items.splice(i, 1)">✕</button>
        </div>
    </template>

    <button type="button" class="text-sm text-indigo-600"
            @click="items.push({ description: '', quantity: 1, weight_kg: '' })">+ Tambah barang</button>

    @foreach ($errors->getMessages() as $key => $messages)
        @if (str_starts_with($key, 'items'))
            <p class="mt-1 text-sm text-red-600">{{ $messages[0] }}</p>
        @endif
    @endforeach
</div>

<div class="mt-6">
    <x-primary-button>Simpan</x-primary-button>
</div>