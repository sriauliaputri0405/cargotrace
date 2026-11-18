<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Carriers</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <a href="{{ route('carriers.create') }}"
               class="inline-block mb-4 px-4 py-2 bg-gray-800 text-white rounded">Tambah Carrier</a>

            <div class="bg-white shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Kode</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Aktif</th>
                            <th class="p-3">Shipment</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carriers as $carrier)
                            <tr class="border-t">
                                <td class="p-3">{{ $carrier->name }}</td>
                                <td class="p-3">{{ $carrier->code }}</td>
                                <td class="p-3">{{ $carrier->contact_email }}</td>
                                <td class="p-3">{{ $carrier->is_active ? 'Ya' : 'Tidak' }}</td>
                                <td class="p-3">{{ $carrier->shipments_count }}</td>
                                <td class="p-3 flex gap-3">
                                    <a href="{{ route('carriers.edit', $carrier) }}" class="text-indigo-600">Ubah</a>
                                    <form method="POST" action="{{ route('carriers.destroy', $carrier) }}"
                                          onsubmit="return confirm('Hapus carrier ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-3 text-gray-500">Belum ada carrier.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $carriers->links() }}</div>
        </div>
    </div>
</x-app-layout>