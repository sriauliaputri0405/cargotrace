<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Exceptions</h2>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('exceptions.index') }}" class="flex flex-wrap gap-3 mb-4">
        <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
            <option value="">Semua status</option>
            @foreach ($statuses as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>
        <select name="severity" class="border-gray-300 rounded-md shadow-sm text-sm">
            <option value="">Semua severity</option>
            @foreach ($severities as $sv)
                <option value="{{ $sv }}" @selected(request('severity') === $sv)>{{ $sv }}</option>
            @endforeach
        </select>
        <x-primary-button>Filter</x-primary-button>
        <a href="{{ route('exceptions.index') }}" class="self-center text-sm text-gray-500">Reset</a>
    </form>

    @php
        $sevColor = [
            'LOW'    => 'bg-yellow-100 text-yellow-800',
            'MEDIUM' => 'bg-orange-100 text-orange-800',
            'HIGH'   => 'bg-red-100 text-red-800',
        ];
    @endphp

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3">Shipment</th>
                    <th class="p-3">Jenis</th>
                    <th class="p-3">Severity</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Terdeteksi</th>
                    <th class="p-3">Selesai</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($exceptions as $ex)
                    <tr class="border-t">
                        <td class="p-3">
                            <a href="{{ route('shipments.show', $ex->shipment) }}" class="text-indigo-600">
                                {{ $ex->shipment->shipment_number }}
                            </a>
                        </td>
                        <td class="p-3">{{ str_replace('_', ' ', $ex->type) }}</td>
                        <td class="p-3"><span class="px-2 py-1 rounded text-xs {{ $sevColor[$ex->severity] ?? '' }}">{{ $ex->severity }}</span></td>
                        <td class="p-3">{{ $ex->status }}</td>
                        <td class="p-3">{{ $ex->detected_at->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ $ex->resolved_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="p-3">
                            <div class="flex gap-3">
                                @if ($ex->status === 'OPEN')
                                    <form method="POST" action="{{ route('exceptions.status', $ex) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="INVESTIGATING">
                                        <button class="text-indigo-600">Investigasi</button>
                                    </form>
                                @endif
                                @if ($ex->status !== 'RESOLVED')
                                    <form method="POST" action="{{ route('exceptions.status', $ex) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="RESOLVED">
                                        <button class="text-green-700">Selesaikan</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-3 text-gray-500">Belum ada exception.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $exceptions->links() }}</div>
</x-app-layout>