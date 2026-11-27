@props(['data', 'height' => 'h-72'])

<div>
    <div data-shipment-map data-map="{{ json_encode($data) }}" class="{{ $height }} w-full rounded-md z-0"></div>
    <p data-map-empty class="hidden text-sm text-gray-500">Belum ada koordinat untuk ditampilkan.</p>
    <p data-map-error class="hidden mt-2 text-xs text-amber-700">Sebagian peta gagal dimuat. Periksa koneksi internet.</p>
</div>