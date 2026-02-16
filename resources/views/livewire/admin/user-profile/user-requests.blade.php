<div class="w-full space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Anträge</h2>
            </div>

            <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-4 lg:w-auto">
                <input
                    type="text"
                    placeholder="Suchen ..."
                    wire:model.live.debounce.400ms="search"
                    class="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-200"
                />

                <select
                    wire:model.live="type"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-200"
                >
                    <option value="">Typ: alle</option>
                    <option value="{{ \App\Models\UserRequest::TYPE_ABSENCE }}">Fehlzeit</option>
                    <option value="{{ \App\Models\UserRequest::TYPE_MAKEUP }}">Nachprüfung</option>
                    <option value="{{ \App\Models\UserRequest::TYPE_EXTERNAL_MAKEUP }}">Externe Prüfung</option>
                    <option value="{{ \App\Models\UserRequest::TYPE_GENERAL }}">Allgemein</option>
                </select>

                <div class="flex gap-2">
                    <select
                        wire:model.live="perPage"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-200"
                    >
                        <option value="10">10 / Seite</option>
                        <option value="25">25 / Seite</option>
                        <option value="50">50 / Seite</option>
                    </select>
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-2 font-semibold">Typ</th>
                    <th class="px-4 py-2 font-semibold">Titel / Inhalt</th>
                    <th class="px-4 py-2 font-semibold">Klasse / Modul</th>
                    <th class="px-4 py-2 font-semibold">
                        <button class="inline-flex items-center gap-1" wire:click="sortBy('submitted_at')">
                            Eingereicht
                        </button>
                    </th>
                    <th class="px-4 py-2 font-semibold">Zeitraum</th>
                    <th class="px-4 py-2 font-semibold">Status</th>
                    <th class="px-4 py-2 text-right font-semibold">Details</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $r)
                    @php
                        $typeMap = [
                            'absence' => 'Fehlzeit',
                            'makeup' => 'Nachprüfung',
                            'external_makeup' => 'Externe Prüfung',
                            'general' => 'Allgemein',
                        ];
                        $statusColors = [
                            'pending'   => 'bg-amber-100 text-amber-800',
                            'in_review' => 'bg-blue-100 text-blue-800',
                            'approved'  => 'bg-green-100 text-green-800',
                            'rejected'  => 'bg-red-100 text-red-800',
                            'canceled'  => 'bg-gray-100 text-gray-700',
                        ];
                    @endphp

                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $typeMap[$r->type] ?? $r->type }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900">{{ $r->title ?? '—' }}</div>
                            @if($r->message)
                                <div class="mt-1 line-clamp-2 text-gray-600">{{ $r->message }}</div>
                            @endif
                            @if($r->attachment_path)
                                <div class="mt-2">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                        <i class="far fa-paperclip mr-1"></i>
                                        Anhang
                                    </span>
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-gray-900">{{ $r->class_label ?? $r->class_code ?? '—' }}</div>
                            <div class="text-xs text-gray-600">{{ $r->module_code ?? '—' }}</div>
                            @if($r->instructor_name)
                                <div class="text-xs text-gray-500">Dozent: {{ $r->instructor_name }}</div>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                            {{ optional($r->submitted_at)->format('d.m.Y H:i') ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-gray-700">
                            @if($r->full_day)
                                Ganztägig
                            @else
                                @php
                                    $df = optional($r->date_from)?->format('d.m.Y');
                                    $dt = optional($r->date_to)?->format('d.m.Y');
                                @endphp
                                @if($df && $dt && $df !== $dt)
                                    {{ $df }} – {{ $dt }}
                                @elseif($df)
                                    {{ $df }}
                                @else
                                    —
                                @endif

                                @if($r->time_arrived_late || $r->time_left_early)
                                    <div class="mt-1 text-xs text-gray-500">
                                        @if($r->time_arrived_late) spät: {{ $r->time_arrived_late }} @endif
                                        @if($r->time_left_early) · früh: {{ $r->time_left_early }} @endif
                                    </div>
                                @endif
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $statusColors[$r->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $r->status_label }}
                            </span>
                            @if($r->decided_at)
                                <div class="mt-1 text-xs text-gray-500">am {{ $r->decided_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-right">
                            <x-ui.buttons.button-basic
                                :mode="'basic'"
                                :size="'sm'"
                                x-on:click="$wire.dispatch('admin:open-request-detail',[ { id: {{ $r->id }} }])"
                            >
                                <i class="far fa-eye mr-2"></i>
                                Anzeigen
                            </x-ui.buttons.button-basic>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                            Keine Einträge gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-col gap-2 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
        <div>
            Seite {{ $requests->currentPage() }} von {{ $requests->lastPage() }} · {{ $requests->total() }} Einträge
        </div>
        <div>{{ $requests->onEachSide(1)->links() }}</div>
    </div>
</div>
