<div>
    @once
        <style>
            .attendance-time-input,
            .attendance-time-select {
                height: 2.5rem;
                border: 1px solid #d1d5db;
                background-color: #f9fafb;
                font-size: .875rem;
                line-height: 1.25rem;
                transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
            }
            .attendance-time-input {
                width: 100%;
                border-right: 0;
                border-radius: .5rem 0 0 .5rem;
                padding: .625rem 2.5rem .625rem .625rem;
                color: #111827;
                font-variant-numeric: tabular-nums;
            }
            .attendance-time-select {
                width: 2.5rem;
                cursor: pointer;
                border-radius: 0 .5rem .5rem 0;
                padding: .5rem;
                color: transparent;
            }
            .attendance-time-input:hover,
            .attendance-time-select:hover { background-color: #fff; }
            .attendance-time-input:focus,
            .attendance-time-select:focus {
                z-index: 1;
                border-color: #3b82f6;
                outline: none;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, .18);
            }
            .attendance-time-input:disabled,
            .attendance-time-select:disabled { cursor: not-allowed; opacity: .55; }
        </style>
    @endonce
    <x-dialog-modal wire:model="showModal" maxWidth="6xl">
        <x-slot name="title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-semibold text-slate-900">Anwesenheit bearbeiten</div>
                    <div class="mt-1 text-xs font-normal text-slate-500">
                        {{ $courseTitle }} · {{ $dayLabel }}
                        @if($plannedStart || $plannedEnd)
                            · {{ $plannedStart ?? '–' }}–{{ $plannedEnd ?? '–' }} Uhr
                        @endif
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="relative min-h-40 space-y-4">
                @if($syncError)
                    <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <i class="fal fa-exclamation-triangle mt-0.5"></i>
                        <span>{{ $syncError }}</span>
                    </div>
                @endif

                @if(empty($rows))
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        Für diesen Baustein sind keine aktiven Teilnehmer zugeordnet.
                    </div>
                @else
                    <div class="inline-flex overflow-hidden rounded-full border border-gray-200 bg-white text-xs shadow-sm">
                        <span class="inline-flex items-center gap-1 bg-green-50 px-2.5 py-1 text-green-800">
                            <i class="fas fa-check-circle text-green-600"></i>
                            <span class="font-semibold">{{ $stats['present'] }}</span>
                            <span class="hidden md:inline">Anwesend</span>
                        </span>
                        <span class="w-px bg-gray-200"></span>
                        <span class="inline-flex items-center gap-1 bg-yellow-50 px-2.5 py-1 text-yellow-800">
                            <i class="fas fa-clock text-yellow-600"></i>
                            <span class="font-semibold">{{ $stats['late'] }}</span>
                            <span class="hidden md:inline">Teilweise</span>
                        </span>
                        <span class="w-px bg-gray-200"></span>
                        <span class="inline-flex items-center gap-1 bg-blue-50 px-2.5 py-1 text-blue-800">
                            <i class="fas fa-file-medical text-blue-600"></i>
                            <span class="font-semibold">{{ $stats['excused'] }}</span>
                            <span class="hidden md:inline">Entschuldigt</span>
                        </span>
                        <span class="w-px bg-gray-200"></span>
                        <span class="inline-flex items-center gap-1 bg-red-50 px-2.5 py-1 text-red-800">
                            <i class="fas fa-times-circle text-red-600"></i>
                            <span class="font-semibold">{{ $stats['absent'] }}</span>
                            <span class="hidden md:inline">Fehlend</span>
                        </span>
                        <span class="w-px bg-gray-200"></span>
                        <span class="inline-flex items-center gap-1 bg-gray-50 px-2.5 py-1 text-gray-700">
                            <i class="fas fa-question-circle text-gray-600"></i>
                            <span class="font-semibold">{{ $stats['unknown'] }}</span>
                            <span class="hidden md:inline">Unbekannt</span>
                        </span>
                        <span class="w-px bg-gray-200"></span>
                        <span class="inline-flex items-center gap-1 bg-gray-50 px-2.5 py-1 text-gray-800">
                            <i class="fas fa-users text-gray-600"></i>
                            <span class="font-semibold">{{ $stats['total'] }}</span>
                            <span class="hidden md:inline">Gesamt</span>
                        </span>
                    </div>

                    <div class="rounded border bg-white">
                        <table class="min-w-full table-fixed text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-1/3 px-4 py-2 text-left font-semibold">Teilnehmer</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($rows as $row)
                                    @php
                                        if (! $row['has_entry']) {
                                            $statusKey = 'unknown';
                                        } elseif ($row['excused']) {
                                            $statusKey = 'excused';
                                        } elseif ($row['present'] && $row['late_minutes'] > 0) {
                                            $statusKey = 'partial';
                                        } elseif ($row['present']) {
                                            $statusKey = 'present';
                                        } else {
                                            $statusKey = 'absent';
                                        }

                                        $startPresent = $row['has_entry'] && $row['present'] && $row['late_minutes'] === 0;
                                        $endPresent = $row['has_entry'] && $row['present'] && $row['left_early_minutes'] === 0;
                                        $startLabel = $row['has_entry'] ? ($startPresent ? 'Anwesend' : 'Fehlend') : 'Offen';
                                        $endLabel = $row['has_entry'] ? ($endPresent ? 'Anwesend' : 'Fehlend') : 'Offen';
                                        $startClasses = ! $row['has_entry']
                                            ? 'bg-slate-100 text-slate-600'
                                            : ($startPresent ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800');
                                        $endClasses = ! $row['has_entry']
                                            ? 'bg-slate-100 text-slate-600'
                                            : ($endPresent ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800');

                                        $isAbsent = $statusKey === 'absent';
                                        $isPresent = $statusKey === 'present';
                                        $isPartial = $statusKey === 'partial';
                                        $canEditTime = $isPresent || $isPartial;
                                        $canNote = ($row['has_entry'] && ($isAbsent || $isPartial)) || $row['note'] !== '';
                                    @endphp

                                    <tr
                                        x-data="{
                                            lateOpen: false,
                                            noteOpen: false,
                                            saving: false,
                                            arrive: @entangle('arrivalInput.' . $row['id']).live ?? '',
                                            leave: @entangle('leaveInput.' . $row['id']).live ?? '',
                                            note: @entangle('noteInput.' . $row['id']).live ?? '',
                                        }"
                                        wire:key="admin-attendance-row-{{ $row['id'] }}"
                                        class="hover:bg-gray-50"
                                    >
                                        <td class="px-2 py-2 md:px-4">
                                            <div class="font-medium text-gray-900">{{ $row['name'] ?: 'Teilnehmer #'.$row['id'] }}</div>
                                            <div class="mt-0.5 text-xs text-gray-500">{{ $row['teilnehmer_id'] ?: 'Keine Teilnehmer-ID' }}</div>
                                        </td>
                                        <td class="px-1 py-2 md:px-4">
                                            <div
                                                wire:key="admin-attendance-status-{{ $row['id'] }}-{{ (int) $row['present'] }}-{{ (int) $row['late_minutes'] }}-{{ (int) $row['left_early_minutes'] }}"
                                                class="inline-flex w-64 flex-col overflow-hidden rounded-xl border border-slate-300 bg-white text-[11px] font-semibold shadow-sm"
                                                title="Morgens: {{ $startLabel }} · Ende: {{ $endLabel }}"
                                            >
                                                <div class="flex w-full">
                                                    <span class="inline-flex w-1/2 items-center justify-center gap-1.5 border-r border-slate-300 px-1.5 py-1 {{ $startClasses }}">
                                                        <i class="fad fa-play-circle w-4 text-center text-sm" aria-hidden="true"></i>
                                                        <span>{{ $startLabel }}</span>
                                                    </span>
                                                    <span class="inline-flex w-1/2 items-center justify-center gap-1.5 px-1.5 py-1 {{ $endClasses }}">
                                                        <i class="fad fa-flag-checkered w-4 text-center text-sm" aria-hidden="true"></i>
                                                        <span>{{ $endLabel }}</span>
                                                    </span>
                                                </div>
                                                @if($row['excused'] || $row['late_minutes'] > 0 || $row['left_early_minutes'] > 0)
                                                    <div class="flex w-full items-center divide-x divide-slate-200 border-t border-slate-300 bg-slate-50/70 px-px py-px text-[9px] font-medium tabular-nums">
                                                        @if($row['excused'])
                                                            <span class="inline-flex items-center gap-1 px-1 py-px text-blue-700" title="Entschuldigt">
                                                                <i class="fad fa-file-medical" aria-hidden="true"></i>
                                                                Entsch.
                                                            </span>
                                                        @endif
                                                        @if($row['late_minutes'] > 0)
                                                            <span class="inline-flex items-center gap-1 px-1 py-px text-amber-700" title="Gekommen um {{ $row['arrived_at'] ?? '–' }} Uhr">
                                                                <i class="fad fa-user-clock" aria-hidden="true"></i>
                                                                {{ $row['arrived_at'] ?? '–' }}
                                                            </span>
                                                        @endif
                                                        @if($row['left_early_minutes'] > 0)
                                                            <span class="inline-flex items-center gap-1 px-1 py-px text-orange-700" title="Gegangen um {{ $row['left_at'] ?? '–' }} Uhr">
                                                                <i class="fad fa-door-open" aria-hidden="true"></i>
                                                                {{ $row['left_at'] ?? '–' }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-1 py-2 pr-2 md:px-4">
                                            <div class="relative flex items-center justify-end gap-1">
                                                <div class="flex w-8 items-center justify-center">
                                                    <div x-cloak x-show="saving" class="flex items-center"><i class="fad fa-spinner-third fa-spin text-base text-blue-500"></i></div>
                                                    <div wire:loading.flex wire:target="markPresent({{ $row['id'] }})" class="items-center"><i class="fad fa-spinner-third fa-spin text-base text-blue-500"></i></div>
                                                    <div wire:loading.flex wire:target="markAbsent({{ $row['id'] }})" class="items-center"><i class="fad fa-spinner-third fa-spin text-base text-blue-500"></i></div>
                                                    <div wire:loading.flex wire:target="clearTimes({{ $row['id'] }})" class="items-center"><i class="fad fa-spinner-third fa-spin text-base text-blue-500"></i></div>
                                                </div>

                                                @if(! $isPresent)
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded border border-green-500 text-green-500 transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1"
                                                        title="Anwesend"
                                                        wire:click="markPresent({{ $row['id'] }})"
                                                        wire:loading.class="pointer-events-none cursor-wait opacity-50"
                                                        wire:target="markPresent({{ $row['id'] }})"
                                                    ><i class="fas fa-check text-sm"></i></button>
                                                @endif

                                                @if(! $isAbsent)
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded border border-red-500 text-red-500 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                                                        title="Abwesend"
                                                        wire:click="markAbsent({{ $row['id'] }})"
                                                        wire:loading.class="pointer-events-none cursor-wait opacity-50"
                                                        wire:target="markAbsent({{ $row['id'] }})"
                                                    ><i class="fas fa-times text-sm"></i></button>
                                                @endif

                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        class="relative inline-flex h-8 w-8 items-center justify-center rounded border transition {{ ($row['arrived_at'] || $row['left_at']) ? 'border-yellow-500 bg-yellow-50 text-yellow-600 hover:bg-yellow-100' : 'border-gray-300 text-gray-500 hover:bg-gray-200' }}"
                                                        title="Verspätung / Früh weg eintragen"
                                                        @click="@if($canEditTime) lateOpen = !lateOpen @endif"
                                                        @disabled(! $canEditTime)
                                                    >
                                                        <i class="far fa-clock text-sm"></i>
                                                        @if($row['arrived_at'] || $row['left_at'])
                                                            <span class="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-yellow-400"></span>
                                                            <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-yellow-400"></span>
                                                        @endif
                                                    </button>

                                                    <div x-cloak x-show="lateOpen" @click.outside="lateOpen=false" class="absolute right-0 z-20 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                                                        <div class="absolute right-0 top-0 flex gap-4 p-2">
                                                            <button type="button" class="text-xs text-gray-600 hover:text-red-600" wire:click="clearTimes({{ $row['id'] }})" wire:loading.attr="disabled" wire:target="clearTimes({{ $row['id'] }})" title="Zeiten löschen"><i class="far fa-trash-alt"></i></button>
                                                            <button type="button" class="text-xs text-gray-600" @click="lateOpen=false"><i class="far fa-times-circle"></i></button>
                                                        </div>

                                                        <div class="space-y-4">
                                                            <div>
                                                                <label for="admin-arrive-{{ $row['id'] }}" class="mb-2 block text-xs font-medium text-gray-600">Gekommen (Uhrzeit)</label>
                                                                <div class="flex items-end">
                                                                    <div class="relative flex-1">
                                                                        <div class="pointer-events-none absolute inset-y-0 end-0 top-0 flex items-center pe-3.5"><i class="far fa-clock text-gray-500"></i></div>
                                                                        <input
                                                                            x-model="arrive"
                                                                            type="time"
                                                                            id="admin-arrive-{{ $row['id'] }}"
                                                                            class="attendance-time-input block"
                                                                            min="{{ $plannedStart }}"
                                                                            max="{{ $plannedEnd }}"
                                                                            step="60"
                                                                            @change="saving = true; $wire.saveArrival({{ $row['id'] }}, $event.target.value).finally(() => saving = false)"
                                                                            :disabled="saving"
                                                                            @disabled(! $canEditTime)
                                                                        >
                                                                    </div>
                                                                    <div class="w-10 shrink-0">
                                                                        <select
                                                                            id="admin-arrive-quick-{{ $row['id'] }}"
                                                                            @change="arrive = $event.target.value; saving = true; $wire.saveArrival({{ $row['id'] }}, arrive).finally(() => saving = false);"
                                                                            class="attendance-time-select block"
                                                                            :disabled="saving"
                                                                            @disabled(! $canEditTime)
                                                                        >
                                                                            <option class="text-gray-700" value="">Bitte wählen</option>
                                                                            <option class="text-gray-700" value="{{ $plannedStart }}">Pünktlich</option>
                                                                            <option class="text-gray-700" value="08:30">08:30</option>
                                                                            <option class="text-gray-700" value="09:00">09:00</option>
                                                                            <option class="text-gray-700" value="09:30">09:30</option>
                                                                            <option class="text-gray-700" value="10:00">10:00</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                @error('arrivalInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                                            </div>

                                                            <div>
                                                                <label for="admin-leave-{{ $row['id'] }}" class="mb-2 block text-xs font-medium text-gray-600">Gegangen (Uhrzeit)</label>
                                                                <div class="flex items-end">
                                                                    <div class="relative flex-1">
                                                                        <div class="pointer-events-none absolute inset-y-0 end-0 top-0 flex items-center pe-3.5"><i class="far fa-clock text-gray-500"></i></div>
                                                                        <input
                                                                            x-model="leave"
                                                                            type="time"
                                                                            id="admin-leave-{{ $row['id'] }}"
                                                                            class="attendance-time-input block"
                                                                            min="{{ $plannedStart }}"
                                                                            max="{{ $plannedEnd }}"
                                                                            step="60"
                                                                            @change="saving = true; $wire.saveLeave({{ $row['id'] }}, $event.target.value).finally(() => saving = false)"
                                                                            :disabled="saving"
                                                                            @disabled(! $canEditTime)
                                                                        >
                                                                    </div>
                                                                    <div class="w-10 shrink-0">
                                                                        <select
                                                                            id="admin-leave-quick-{{ $row['id'] }}"
                                                                            @change="leave = $event.target.value; saving = true; $wire.saveLeave({{ $row['id'] }}, leave).finally(() => saving = false);"
                                                                            class="attendance-time-select block"
                                                                            :disabled="saving"
                                                                            @disabled(! $canEditTime)
                                                                        >
                                                                            <option class="text-gray-700" value="">Bitte wählen</option>
                                                                            <option class="text-gray-700" value="12:30">12:30</option>
                                                                            <option class="text-gray-700" value="13:00">13:00</option>
                                                                            <option class="text-gray-700" value="13:30">13:30</option>
                                                                            <option class="text-gray-700" value="14:00">14:00</option>
                                                                            <option class="text-gray-700" value="14:30">14:30</option>
                                                                            <option class="text-gray-700" value="15:00">15:00</option>
                                                                            <option class="text-gray-700" value="15:30">15:30</option>
                                                                            <option class="text-gray-700" value="16:00">16:00</option>
                                                                            <option class="text-gray-700" value="16:30">16:30</option>
                                                                            <option class="text-gray-700" value="{{ $plannedEnd }}">Pünktlich</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                @error('leaveInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    class="relative"
                                                    x-data="{
                                                        tipOpen: false,
                                                        showTooltip() { this.tipOpen = true; clearTimeout(this.__tipT); this.__tipT = setTimeout(() => this.tipOpen = false, 4500); },
                                                        hideTooltip() { this.tipOpen = false; clearTimeout(this.__tipT); },
                                                    }"
                                                >
                                                    <button
                                                        type="button"
                                                        class="relative inline-flex h-8 w-8 items-center justify-center rounded border transition {{ $row['note'] !== '' ? 'border-blue-300 bg-blue-50/70 text-blue-400 hover:bg-blue-100' : 'border-gray-300 text-gray-500 hover:bg-gray-50' }} {{ ! $canNote ? 'opacity-80' : '' }}"
                                                        title="Notiz hinzufügen"
                                                        @mouseenter="@if(! $canNote) showTooltip() @endif"
                                                        @mouseleave="hideTooltip()"
                                                        @focus="@if(! $canNote) showTooltip() @endif"
                                                        @blur="hideTooltip()"
                                                        @click="@if($canNote) noteOpen = !noteOpen @else showTooltip() @endif"
                                                    >
                                                        <i class="fas fa-pen text-sm"></i>
                                                        @if($row['note'] !== '')
                                                            <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white bg-blue-300"></span>
                                                            <span class="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-blue-200"></span>
                                                        @endif
                                                    </button>

                                                    @if(! $canNote)
                                                        <div x-cloak x-show="tipOpen" x-transition.opacity.duration.150ms @click.outside="hideTooltip()" class="absolute right-0 z-30 mt-2 w-64 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 shadow">
                                                            <div class="mb-0.5 font-semibold">Notiz erst nach Eintrag</div>
                                                            <div>Bitte zuerst <span class="font-medium">Fehlend/Teilweise Anwesend</span> setzen, dann kannst du eine Notiz speichern.</div>
                                                        </div>
                                                    @endif

                                                    <div x-cloak x-show="noteOpen" @click.outside="noteOpen=false" class="absolute right-0 z-20 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                                                        <label class="mb-1 block text-xs text-gray-600">Notiz</label>
                                                        <textarea
                                                            x-model="note"
                                                            rows="3"
                                                            maxlength="1000"
                                                            class="w-full rounded border-gray-300 text-sm"
                                                            @change="saving = true; $wire.saveNote({{ $row['id'] }}, $event.target.value).finally(() => saving = false)"
                                                            :disabled="saving"
                                                        ></textarea>
                                                        @error('noteInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                                        <div class="mt-2 flex justify-end"><button type="button" class="text-xs text-gray-600 underline" @click="noteOpen=false">Schließen</button></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between gap-3">
                @if(auth()->user()?->isAdmin())
                    <button type="button" wire:click="refreshFromUvs" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:opacity-50">
                        <i wire:loading wire:target="refreshFromUvs" class="fad fa-spinner-third fa-spin text-base"></i>
                        <i wire:loading.remove wire:target="refreshFromUvs" class="fal fa-sync"></i>
                        <span wire:loading.remove wire:target="refreshFromUvs">Aus UVS aktualisieren</span>
                        <span wire:loading wire:target="refreshFromUvs">Wird aktualisiert…</span>
                    </button>
                @else
                    <span></span>
                @endif
                <x-secondary-button wire:click="close">Schließen</x-secondary-button>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
