<div>
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
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                    <i class="fal fa-calendar-day"></i>
                    Nur heute bearbeitbar
                </span>
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
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Teilnehmer</th>
                                    <th class="px-4 py-3">Status Start / Ende</th>
                                    <th class="px-4 py-3">Status setzen</th>
                                    <th class="px-4 py-3">Kommen / Gehen</th>
                                    <th class="px-4 py-3">Bemerkung</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($rows as $row)
                                    @php
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
                                    @endphp
                                    <tr wire:key="admin-attendance-row-{{ $row['id'] }}" class="align-top hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900">{{ $row['name'] ?: 'Teilnehmer #'.$row['id'] }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $row['teilnehmer_id'] ?: 'Keine Teilnehmer-ID' }}</div>
                                            @if(($row['state'] ?? null) === 'dirty')
                                                <div class="mt-1 text-[11px] font-medium text-amber-700">Synchronisation ausstehend</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="inline-flex overflow-hidden rounded-lg border border-slate-200 text-[11px] font-semibold shadow-sm" title="Start: {{ $startLabel }} · Ende: {{ $endLabel }}">
                                                <span class="px-2.5 py-1.5 {{ $startClasses }}">{{ $startLabel }}</span>
                                                <span class="border-l border-white/70 px-2.5 py-1.5 {{ $endClasses }}">{{ $endLabel }}</span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                                                @if($row['excused'])
                                                    <span class="rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">Entschuldigt</span>
                                                @endif
                                                @if($row['late_minutes'] > 0)
                                                    <span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Verspätet: {{ $row['arrived_at'] ?? '–' }}</span>
                                                @endif
                                                @if($row['left_early_minutes'] > 0)
                                                    <span class="rounded-md border border-orange-200 bg-orange-50 px-2 py-0.5 text-orange-700">Gegangen: {{ $row['left_at'] ?? '–' }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                <button type="button" wire:click="markPresent({{ $row['id'] }})" wire:loading.attr="disabled" class="rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:opacity-50">
                                                    Anwesend
                                                </button>
                                                <button type="button" wire:click="markAbsent({{ $row['id'] }})" wire:loading.attr="disabled" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1 disabled:opacity-50">
                                                    Fehlend
                                                </button>
                                                <button type="button" wire:click="markExcused({{ $row['id'] }})" wire:loading.attr="disabled" class="rounded-lg border border-blue-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 disabled:opacity-50">
                                                    Entschuldigt
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex min-w-52 items-end gap-2">
                                                <label class="block text-[11px] font-medium text-slate-600">
                                                    Kommen
                                                    <input type="time" wire:model.defer="arrivalInput.{{ $row['id'] }}" @disabled(! $row['present']) class="mt-1 block w-24 rounded-lg border-slate-300 px-2 py-1.5 text-xs focus:border-sky-500 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400">
                                                </label>
                                                <label class="block text-[11px] font-medium text-slate-600">
                                                    Gehen
                                                    <input type="time" wire:model.defer="leaveInput.{{ $row['id'] }}" @disabled(! $row['present']) class="mt-1 block w-24 rounded-lg border-slate-300 px-2 py-1.5 text-xs focus:border-sky-500 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400">
                                                </label>
                                                <button type="button" wire:click="saveTimes({{ $row['id'] }})" wire:loading.attr="disabled" @disabled(! $row['present']) class="h-8 rounded-lg bg-sky-600 px-2.5 text-xs font-semibold text-white transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:bg-slate-300">
                                                    Speichern
                                                </button>
                                            </div>
                                            @error('arrivalInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                            @error('leaveInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex min-w-64 items-end gap-2">
                                                <label class="block flex-1 text-[11px] font-medium text-slate-600">
                                                    Bemerkung
                                                    <input type="text" wire:model.defer="noteInput.{{ $row['id'] }}" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300 px-2.5 py-1.5 text-xs focus:border-sky-500 focus:ring-sky-500">
                                                </label>
                                                <button type="button" wire:click="saveNote({{ $row['id'] }})" wire:loading.attr="disabled" class="h-8 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:opacity-50">
                                                    Speichern
                                                </button>
                                            </div>
                                            @error('noteInput.'.$row['id']) <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div wire:loading.delay class="absolute inset-0 z-10 rounded-xl bg-white/75 backdrop-blur-[1px]">
                    <div class="flex h-full min-h-40 items-center justify-center">
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm">
                            <span class="loader"></span>
                            Anwesenheiten werden verarbeitet…
                        </div>
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between gap-3">
                <button type="button" wire:click="refreshFromUvs" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:opacity-50">
                    <i class="fal fa-sync"></i>
                    Aus UVS aktualisieren
                </button>
                <x-secondary-button wire:click="close">Schließen</x-secondary-button>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
