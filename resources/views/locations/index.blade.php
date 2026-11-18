<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Locations</h2>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
    @endif

    <a href="{{ route('locations.create') }}"
       class="inline-block mb-4 px-4 py-2 bg-gray-800 text-white rounded">Tambah Lokasi</a>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Nama</th>
                    <th class="p-3">Kota</th>
                    <th class="p-3">Negara</th>
                    <th class="p-3">Tipe</th>
                    <th class="p-3">Latitude</th>
                    <th class="p-3">Longitude</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $location)
                    <tr class="border-t">
                        <td class="p-3">{{ $location->name }}</td>
                        <td class="p-3">{{ $location->city }}</td>
                        <td class="p-3">{{ $location->country }}</td>
                        <td class="p-3">{{ ucfirst($location->type) }}</td>
                        <td class="p-3">{{ $location->latitude }}</td>
                        <td class="p-3">{{ $location->longitude }}</td>
                        <td class="p-3 flex gap-3">
                            <a href="{{ route('locations.edit', $location) }}" class="text-indigo-600">Ubah</a>
                            <form method="POST" action="{{ route('locations.destroy', $location) }}"
                                  onsubmit="return confirm('Hapus lokasi ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-3 text-gray-500">Belum ada lokasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $locations->links() }}</div>
</x-app-layout>