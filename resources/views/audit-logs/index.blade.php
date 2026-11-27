<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Log</h2>
    </x-slot>

    @php
        $field = 'block w-full border-gray-300 rounded-md shadow-sm text-sm';
        $fmt = fn ($v) => is_null($v) ? '-' : (is_bool($v) ? ($v ? 'yes' : 'no') : (is_scalar($v) ? (string) $v : json_encode($v)));
    @endphp

    <form method="GET" action="{{ route('audit-logs.index') }}" class="bg-white shadow-sm rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="action" class="{{ $field }}">
                <option value="">Semua aksi</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                @endforeach
            </select>
            <select name="entity_type" class="{{ $field }}">
                <option value="">Semua entity</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(request('entity_type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
            <select name="user_id" class="{{ $field }}">
                <option value="">Semua pelaku</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}">
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}">
        </div>
        <div class="mt-3 flex items-center gap-3">
            <x-primary-button>Filter</x-primary-button>
            <a href="{{ route('audit-logs.index') }}" class="text-sm text-gray-500">Reset</a>
        </div>
    </form>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3">Waktu</th>
                    <th class="p-3">Pelaku</th>
                    <th class="p-3">Aksi</th>
                    <th class="p-3">Entity</th>
                    <th class="p-3">Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $old = $log->old_values ?? [];
                        $new = $log->new_values ?? [];
                        $keys = array_slice(array_unique(array_merge(array_keys($old), array_keys($new))), 0, 8);
                    @endphp
                    <tr class="border-t align-top">
                        <td class="p-3 whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="p-3">{{ $log->action }}</td>
                        <td class="p-3">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                        <td class="p-3">
                            @foreach ($keys as $key)
                                <div class="text-xs">
                                    <span class="text-gray-500">{{ $key }}:</span>
                                    {{ $fmt($old[$key] ?? null) }} → {{ $fmt($new[$key] ?? null) }}
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-3 text-gray-500">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>