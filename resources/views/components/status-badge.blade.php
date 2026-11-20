@props(['shipment'])

@php
    $map = [
        'CREATED'    => 'bg-gray-100 text-gray-700',
        'BOOKED'     => 'bg-sky-100 text-sky-700',
        'PICKED_UP'  => 'bg-indigo-100 text-indigo-700',
        'IN_TRANSIT' => 'bg-blue-100 text-blue-700',
        'DELIVERED'  => 'bg-green-100 text-green-700',
    ];
@endphp

@if ($shipment->is_delayed)
    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">DELAYED</span>
@else
    <span class="px-2 py-1 rounded text-xs {{ $map[$shipment->status] ?? 'bg-gray-100 text-gray-700' }}">
        {{ str_replace('_', ' ', $shipment->status) }}
    </span>
@endif