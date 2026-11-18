@csrf
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