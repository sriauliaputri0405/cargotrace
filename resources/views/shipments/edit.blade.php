<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Shipment {{ $shipment->shipment_number }}</h2>
    </x-slot>

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('shipments.update', $shipment) }}" class="bg-white shadow-sm rounded-lg p-6">
            @method('PUT')
            @include('shipments._form')
        </form>
    </div>
</x-app-layout>