<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Buat Shipment</h2>
    </x-slot>

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('shipments.store') }}" class="bg-white shadow-sm rounded-lg p-6">
            @include('shipments._form')
        </form>
    </div>
</x-app-layout>