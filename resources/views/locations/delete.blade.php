<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Lokasi</h2>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('locations.update', $location) }}" class="bg-white shadow-sm rounded-lg p-6">
            @method('PUT')
            @include('locations._form')
        </form>
    </div>
</x-app-layout>