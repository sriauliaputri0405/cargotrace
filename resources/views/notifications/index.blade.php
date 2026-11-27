<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notifikasi</h2>
    </x-slot>

    @php
        $levelColor = ['info' => 'border-blue-400', 'warning' => 'border-amber-400', 'danger' => 'border-red-500'];
    @endphp

    <div class="flex justify-end mb-3">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="text-sm text-indigo-600">Tandai semua dibaca</button>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}"
               class="block bg-white shadow-sm rounded-lg p-4 border-l-4 {{ $levelColor[$n->data['level'] ?? 'info'] ?? 'border-gray-300' }} {{ $n->read_at ? 'opacity-70' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-sm">{{ $n->data['title'] ?? 'Notifikasi' }}</span>
                    <span class="text-xs text-gray-500">{{ $n->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ $n->data['message'] ?? '' }}</p>
                @unless ($n->read_at)
                    <span class="text-xs text-indigo-600">Belum dibaca</span>
                @endunless
            </a>
        @empty
            <p class="text-sm text-gray-500">Belum ada notifikasi.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app-layout>