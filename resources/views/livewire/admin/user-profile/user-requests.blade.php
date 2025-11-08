<div class="w-full space-y-4">
    {{-- Kopf: Titel + Suche/Filter --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Anträge</h2>
            <p class="text-sm text-gray-500">
                {{ $this->stats['total'] }} insgesamt ·
                {{ $this->stats['pending'] }} offen ·
                {{ $this->stats['approved'] }} genehmigt ·
                {{ $this->stats['rejected'] }} abgelehnt
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 w-full sm:w-auto">
            <input type="text" placeholder="Suchen …"
                   wire:model.live.debounce.400ms="search"
                   class="sm:col-span-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300" />

            <select wire:model.live="type"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300">
                <option value="">Typ: alle</option>
                <option value="{{ \App\Models\UserRequest::TYPE_ABSENCE }}">Fehlzeit</option>
                <option value="{{ \App\Models\UserRequest::TYPE_MAKEUP }}">Nachprüfung</option>
                <option value="{{ \App\Models\UserRequest::TYPE_EXTERNAL_MAKEUP }}">Externe Prüfung</option>
                <option value="{{ \App\Models\UserRequest::TYPE_GENERAL }}">Allgemein</option>
            </select>

            <select wire:model.live="status"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300">
                <option value="">Status: alle</option>
                <option value="{{ \App\Models\UserRequest::STATUS_PENDING }}">Eingereicht</option>
                <option value="{{ \App\Models\UserRequest::STATUS_IN_REVIEW }}">In Prüfung</option>
                <option value="{{ \App\Models\UserRequest::STATUS_APPROVED }}">Genehmigt</option>
                <option value="{{ \App\Models\UserRequest::STATUS_REJECTED }}">Abgelehnt</option>
                <option value="{{ \App\Models\UserRequest::STATUS_CANCELED }}">Storniert</option>
            </select>

            <input type="date" wire:model.live="from"
                   class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300" />
            <input type="date" wire:model.live="to"
                   class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300" />

            <div class="flex gap-2 sm:col-span-6">
                <select wire:model.live="perPage"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300">
                    <option value="10">10 / Seite</option>
                    <option value="25">25 / Seite</option>
                    <option value="50">50 / Seite</option>
                </select>
                <button type="button" wire:click="resetFilters"
                        class="rounded-md border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Filter zurücksetzen</button>
            </div>
        </div>
    </div>

    {{-- Tabelle --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-600">
                    <th class="px-4 py-2">
                        <button class="flex items-center gap-1" wire:click="sortBy('type')">Typ</button>
                    </th>
                    <th class="px-4 py-2">
                        <button class="flex items-center gap-1" wire:click="sortBy('title')">Titel</button>
                    </th>
                    <th class="px-4 py-2">Klasse/Modul</th>
                    <th class="px-4 py-2">
                        <button class="flex items-center gap-1" wire:click="sortBy('submitted_at')">Eingereicht</button>
                    </th>
                    <th class="px-4 py-2">Zeitraum</th>
                    <th class="px-4 py-2">Attest</th>
                    <th class="px-4 py-2">Gebühr</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $r)
                    <tr class="align-top">
                        <td class="px-4 py-2 font-medium">
                            @php
                                $typeMap = [
                                    'absence' => 'Fehlzeit',
                                    'makeup' => 'Nachprüfung',
                                    'external_makeup' => 'Externe Prüfung',
                                    'general' => 'Allgemein',
                                ];
                            @endphp
                            {{ $typeMap[$r->type] ?? $r->type }}
                        </td>

                        <td class="px-4 py-2">
                            <div class="font-semibold text-gray-900">{{ $r->title ?? '—' }}</div>
                            @if($r->message)
                                <div class="text-gray-600 line-clamp-2">{{ $r->message }}</div>
                            @endif
                            @if($r->attachment_path)
                                <div class="mt-1 text-xs">
                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5">Anhang</span>
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            <div class="text-gray-900">{{ $r->class_label ?? $r->class_code ?? '—' }}</div>
                            <div class="text-gray-600 text-xs">{{ $r->module_code ?? '—' }}</div>
                            @if($r->instructor_name)
                                <div class="text-gray-500 text-xs">Dozent: {{ $r->instructor_name }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            {{ optional($r->submitted_at)->format('d.m.Y H:i') ?? '—' }}
                        </td>

                        <td class="px-4 py-2">
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
                                    <div class="text-xs text-gray-500">
                                        @if($r->time_arrived_late) spät: {{ $r->time_arrived_late }} @endif
                                        @if($r->time_left_early) · früh: {{ $r->time_left_early }} @endif
                                    </div>
                                @endif
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs
                                {{ $r->with_attest ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $r->with_attest_label }}
                            </span>
                        </td>

                        <td class="px-4 py-2">
                            {{ $r->fee_formatted ?? '—' }}
                        </td>

                        <td class="px-4 py-2">
                            @php
                                $statusColors = [
                                    'pending'   => 'bg-amber-100 text-amber-800',
                                    'in_review' => 'bg-blue-100 text-blue-800',
                                    'approved'  => 'bg-green-100 text-green-800',
                                    'rejected'  => 'bg-red-100 text-red-800',
                                    'canceled'  => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $statusColors[$r->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $r->status_label }}
                            </span>
                            @if($r->decided_at)
                                <div class="text-xs text-gray-500 mt-1">am {{ $r->decided_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            <div class="flex justify-end gap-2">
                                <button class="rounded-md border px-2 py-1 text-xs hover:bg-gray-50"
        x-on:click="$wire.dispatch('admin:open-request-detail',[ { id: {{ $r->id }} }])">
    Details
</button>
                                <button class="rounded-md border px-2 py-1 text-xs hover:bg-green-50"
                                        wire:click="approve({{ $r->id }})"
                                        @disabled($r->status === \App\Models\UserRequest::STATUS_APPROVED)">
                                    Genehmigen
                                </button>
                                <button class="rounded-md border px-2 py-1 text-xs hover:bg-amber-50"
                                        wire:click="cancel({{ $r->id }})"
                                        @disabled($r->status === \App\Models\UserRequest::STATUS_CANCELED)">
                                    Stornieren
                                </button>
                                <button class="rounded-md border px-2 py-1 text-xs hover:bg-red-50"
                                        wire:click="reject({{ $r->id }})"
                                        @disabled($r->status === \App\Models\UserRequest::STATUS_REJECTED)">
                                    Ablehnen
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Keine Einträge gefunden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-600">
            Seite {{ $requests->currentPage() }} von {{ $requests->lastPage() }} ·
            {{ $requests->total() }} Einträge
        </div>
        <div>{{ $requests->onEachSide(1)->links() }}</div>
    </div>
</div>
