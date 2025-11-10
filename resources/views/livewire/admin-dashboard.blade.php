<div class="" wire:loading.class="cursor-wait"
     @if($autoRefresh) wire:poll.60s="refreshAll" @endif>
    <div class="">
        <div class="mt-2">
            {{-- State Cards --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-4 mb-4">
                {{-- Gesamte Benutzer --}}
                <div class="flex items-center justify-between p-4 bg-white rounded-md border border-gray-200 shadow-sm">
                    <div>
                        <h6 class="text-xs font-medium leading-none tracking-wider text-gray-500 uppercase mb-2">
                            Gesamte Benutzer
                        </h6>
                        <span class="text-2xl font-semibold">{{ number_format($totalUsers) }}</span>
                    </div>
                    <svg class="w-10 h-10 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-4a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>

                {{-- Neue Benutzer (Monat) --}}
                <div class="flex items-center justify-between p-4 bg-white rounded-md border border-gray-200 shadow-sm">
                    <div>
                        <h6 class="text-xs font-medium leading-none tracking-wider text-gray-500 uppercase mb-2">
                            Neue Benutzer (Monat)
                        </h6>
                        <span class="text-2xl font-semibold">{{ number_format($newUsersMonth) }}</span>
                    </div>
                    <svg class="w-10 h-10 text-teal-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v16m8-8H4"/>
                    </svg>
                </div>

                {{-- Kurse heute --}}
                <div class="flex items-center justify-between p-4 bg-white rounded-md border border-gray-200 shadow-sm">
                    <div>
                        <h6 class="text-xs font-medium leading-none tracking-wider text-gray-500 uppercase mb-2">
                            Kurse heute
                        </h6>
                        <span class="text-2xl font-semibold">{{ number_format($coursesToday) }}</span>
                    </div>
                    <svg class="w-10 h-10 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                </div>

                {{-- Offene Anfragen --}}
                <div class="flex items-center justify-between p-4 bg-white rounded-md border border-gray-200 shadow-sm">
                    <div>
                        <h6 class="text-xs font-medium leading-none tracking-wider text-gray-500 uppercase mb-2">
                            Offene Anfragen
                        </h6>
                        <span class="text-2xl font-semibold">{{ number_format($openUserRequests) }}</span>
                    </div>
                    <svg class="w-10 h-10 text-orange-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            {{-- Sektionen --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                {{-- Aktive Nutzer (bestehend) --}}
                <div class="bg-white rounded-md border border-gray-200 shadow-sm">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-lg">Aktive Nutzer</p>
                            <button wire:click="toggleAutoRefresh"
                                    class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50">
                                {{ $autoRefresh ? 'Auto-Refresh: an' : 'Auto-Refresh: aus' }}
                            </button>
                        </div>
                        <livewire:admin.charts.active-users :height="250" />
                    </div>
                </div>

                {{-- Diese Woche: Kurse & Prüfungen --}}
                <div class="bg-white rounded-md border border-gray-200 shadow-sm">
                    <div class="p-4">
                        <p class="font-semibold text-lg">Diese Woche</p>
                        <div class="mt-3 grid grid-cols-2 gap-4">
                            <div class="p-3 rounded bg-sky-50 border border-sky-100">
                                <div class="text-xs uppercase text-sky-700">Kurse (Woche)</div>
                                <div class="text-2xl font-semibold text-sky-900">{{ number_format($coursesThisWeek) }}</div>
                            </div>
                            <div class="p-3 rounded bg-purple-50 border border-purple-100">
                                <div class="text-xs uppercase text-purple-700">Prüfungen (Woche)</div>
                                <div class="text-2xl font-semibold text-purple-900">{{ number_format($examsThisWeek) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Letzte Uploads (7 Tage) --}}
                <div class="bg-white rounded-md border border-gray-200 shadow-sm">
                    <div class="p-4">
                        <p class="font-semibold text-lg">Uploads – letzte 7 Tage</p>
                        <div class="mt-3">
                            @if(!empty($recentUploads))
@foreach($recentUploads as $file)
    <div class="flex items-center justify-between py-2">
        <div class="flex items-center gap-2">
            <img src="{{ $file->icon_or_thumbnail }}" alt="icon" class="w-6 h-6 rounded">
            <div>
                <p class="font-medium text-gray-800">{{ $file->name }}</p>
                <p class="text-xs text-gray-500">
                    {{ $file->mime_type }} · {{ $file->created_at->format('d.m.Y H:i') }}
                </p>
            </div>
        </div>
        <span class="text-xs text-gray-500">
            {{ number_format($file->size / 1024, 1) }} KB
        </span>
    </div>
@endforeach

                            @else
                                <p class="text-sm text-gray-500">Keine Uploads in den letzten 7 Tagen.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Ungelesene Nachrichten (Top 5) --}}
                <div class="bg-white rounded-md border border-gray-200 shadow-sm">
                    <div class="p-4">
                        <p class="font-semibold text-lg">Ungelesene Nachrichten</p>
                        <div class="mt-3">
                            @if(!empty($recentMessages))
                                <ul class="divide-y divide-gray-100 text-sm">
@foreach($recentMessages as $m)
    <p class="font-semibold">{{ $m->subject }}</p>
    <p class="text-xs text-gray-500">
        {{ $m->sender?->name ?? 'Unbekannt' }} · {{ $m->created_at->diffForHumans() }}
    </p>
    <p>Dateianhänge: {{ $m->files->count() }}</p>
@endforeach
                                </ul>
                            @else
                                <p class="text-sm text-gray-500">Keine ungelesenen Nachrichten.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div> {{-- /Sektionen --}}
        </div>
    </div>
</div>
