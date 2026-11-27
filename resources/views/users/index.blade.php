<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Users</h2>
    </x-slot>

    @php $field = 'border-gray-300 rounded-md shadow-sm text-sm'; @endphp

    @if (session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama atau email" class="{{ $field }}">
            <select name="role" class="{{ $field }}">
                <option value="">Semua role</option>
                @foreach ($roles as $r)
                    <option value="{{ $r }}" @selected(request('role') === $r)>{{ ucfirst($r) }}</option>
                @endforeach
            </select>
            <x-primary-button>Filter</x-primary-button>
            <a href="{{ route('users.index') }}" class="text-sm text-gray-500">Reset</a>
        </form>
        <a href="{{ route('users.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Tambah User</a>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="p-3">Nama</th>
                    <th class="p-3">Email</th>
                    <th class="p-3">Role</th>
                    <th class="p-3">Terdaftar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr class="border-t">
                        <td class="p-3">{{ $u->name }}</td>
                        <td class="p-3">{{ $u->email }}</td>
                        <td class="p-3">
                            @if ($u->id === auth()->id())
                                {{ ucfirst($u->role) }} <span class="text-xs text-gray-400">(akun Anda)</span>
                            @else
                                <form method="POST" action="{{ route('users.role', $u) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="{{ $field }}">
                                        @foreach ($roles as $r)
                                            <option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="text-indigo-600">Simpan</button>
                                </form>
                            @endif
                        </td>
                        <td class="p-3">{{ $u->created_at?->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>