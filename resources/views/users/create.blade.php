<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah User</h2>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('users.store') }}" class="bg-white shadow-sm rounded-lg p-6">
            @csrf

            <div class="mb-4">
                <x-input-label for="name" value="Nama" />
                <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mb-4">
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mb-4">
                <x-input-label for="password" value="Password (minimal 8 karakter)" />
                <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mb-4">
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    @foreach ($roles as $r)
                        <option value="{{ $r }}" @selected(old('role', 'operator') === $r)>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <x-primary-button>Simpan</x-primary-button>
        </form>
    </div>
</x-app-layout>