@csrf

<div x-data="geocoder()">
    <div class="mb-4">
        <x-input-label for="name" value="Nama lokasi" />
        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $location->name ?? '')" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="mb-4">
        <x-input-label for="city" value="Kota" />
        <x-text-input id="city" name="city" class="block mt-1 w-full" :value="old('city', $location->city ?? '')" />
        <x-input-error :messages="$errors->get('city')" class="mt-2" />
    </div>

    <div class="mb-4">
        <x-input-label for="country" value="Kode negara (2 huruf, mis. ID)" />
        <x-text-input id="country" name="country" maxlength="2" class="block mt-1 w-full uppercase"
                      :value="old('country', $location->country ?? '')" />
        <x-input-error :messages="$errors->get('country')" class="mt-2" />
    </div>

    <div class="mb-4">
        <x-input-label for="type" value="Tipe" />
        <select id="type" name="type" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
            @foreach (['port', 'warehouse', 'airport'] as $type)
                <option value="{{ $type }}" @selected(old('type', $location->type ?? 'port') === $type)>
                    {{ ucfirst($type) }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div class="mb-2 flex items-center gap-3">
        <button type="button" @click="lookup()" :disabled="loading"
                class="px-3 py-2 text-sm border rounded-md disabled:opacity-50">
            <span x-show="!loading">Cari koordinat dari nama dan kota</span>
            <span x-show="loading" x-cloak>Mencari...</span>
        </button>
        <span class="text-xs text-gray-500">
            Koordinat dari <a href="https://www.openstreetmap.org/copyright" class="underline" target="_blank" rel="noopener">OpenStreetMap</a> contributors (Nominatim)
        </span>
    </div>
    <p x-show="message" x-cloak x-text="message" class="mb-2 text-sm text-green-700"></p>
    <p x-show="error" x-cloak x-text="error" class="mb-2 text-sm text-red-600"></p>

    <div class="mb-4">
        <x-input-label for="latitude" value="Latitude (-90 sampai 90)" />
        <x-text-input id="latitude" name="latitude" type="number" step="any" class="block mt-1 w-full"
                      :value="old('latitude', $location->latitude ?? '')" />
        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
    </div>

    <div class="mb-4">
        <x-input-label for="longitude" value="Longitude (-180 sampai 180)" />
        <x-text-input id="longitude" name="longitude" type="number" step="any" class="block mt-1 w-full"
                      :value="old('longitude', $location->longitude ?? '')" />
        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
    </div>

    <x-primary-button>Simpan</x-primary-button>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('geocoder', () => ({
            loading: false,
            message: '',
            error: '',

            async lookup() {
                const get = (id) => document.getElementById(id);
                const query = [get('name').value, get('city').value, get('country').value]
                    .filter(Boolean).join(', ');

                this.message = '';
                this.error = '';

                if (query.length < 3) {
                    this.error = 'Isi nama lokasi atau kota dulu.';
                    return;
                }

                this.loading = true;

                try {
                    const response = await fetch('{{ route('geocode.search') }}?q=' + encodeURIComponent(query), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const json = await response.json();

                    if (!response.ok || !json.ok) {
                        this.error = json.message ?? 'Pencarian gagal. Isi koordinat manual.';
                        return;
                    }

                    get('latitude').value = json.latitude;
                    get('longitude').value = json.longitude;

                    if (json.country && !get('country').value) {
                        get('country').value = json.country;
                    }

                    this.message = 'Ditemukan: ' + json.name + '. Periksa dan sesuaikan jika perlu.';
                } catch (e) {
                    this.error = 'Layanan pencarian tidak dapat dihubungi. Isi koordinat manual.';
                } finally {
                    this.loading = false;
                }
            },
        }));
    });
</script>