@csrf
<div class="mb-4">
    <x-input-label for="name" value="Nama" />
    <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $carrier->name ?? '')" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mb-4">
    <x-input-label for="code" value="Kode" />
    <x-text-input id="code" name="code" class="block mt-1 w-full" :value="old('code', $carrier->code ?? '')" />
    <x-input-error :messages="$errors->get('code')" class="mt-2" />
</div>

<div class="mb-4">
    <x-input-label for="contact_email" value="Email kontak" />
    <x-text-input id="contact_email" name="contact_email" type="email" class="block mt-1 w-full"
                  :value="old('contact_email', $carrier->contact_email ?? '')" />
    <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
</div>

<label class="flex items-center mb-4">
    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300"
           @checked(old('is_active', $carrier->is_active ?? true))>
    <span class="ms-2 text-sm text-gray-700">Aktif</span>
</label>

<x-primary-button>Simpan</x-primary-button>