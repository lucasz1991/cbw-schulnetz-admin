<div>
    <x-dialog-modal wire:model="showModal" maxWidth="4xl">
        <x-slot name="title">
            Datenuebertragung aus geloeschten Kursen
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4" wire:loading.class="opacity-60 pointer-events-none">
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Zielkurs</div>
                    <div class="mt-1 text-base font-semibold text-sky-950">
                        {{ $targetCourseTitle ?: 'Unbekannter Kurs' }}
                    </div>
                    @if($targetCourseShortName)
                        <div class="mt-1 text-sm text-sky-700">
                            {{ $targetCourseShortName }}
                        </div>
                    @endif
                    <p class="mt-3 text-sm text-sky-800">
                        In dieser Liste wird bereits geprueft, ob Berichtsheft-Eintraege zeitlich zum Zielkurs passen. Es werden nur Eintraege beruecksichtigt, deren Datum im Zielkurs bereits als Kurstag existiert.
                    </p>
                    <p class="mt-2 text-xs text-sky-700">
                        Laut aktueller Loeschlogik werden Kurstage, Tagesdoku, Anwesenheit, Teilnehmer-Zuordnungen, Ergebnisse, Bewertungen und Kursdateien beim Loeschen haeufig direkt entfernt. Berichtshefte bleiben dagegen in der Regel erhalten, werden aber nur fuer bereits vorhandene Ziel-Kurstage uebernommen. Es werden keine neuen Kurstage angelegt.
                    </p>
                </div>

                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <label class="block flex-1">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Suche</span>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Titel, Kurzbezeichnung, Klassen- oder Termin-ID"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                    </label>

                    <label class="block md:w-40">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Eintraege</span>
                        <select
                            wire:model.live="perPage"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                            <option value="8">8</option>
                            <option value="12">12</option>
                            <option value="20">20</option>
                        </select>
                    </label>
                </div>

                @php
                    $selectedSourceCourse = $this->selectedSourceCourse;
                    $selectedPreview = $selectedSourceCourse->transfer_preview ?? null;
                @endphp
                @if($selectedSourceCourse)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Ausgewaehlter Quellkurs</div>
                        <div class="mt-1 text-sm font-semibold text-emerald-950">
                            {{ $selectedSourceCourse->title ?: 'Ohne Titel' }}
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-emerald-800">
                            @if($selectedSourceCourse->course_short_name)
                                <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1">{{ $selectedSourceCourse->course_short_name }}</span>
                            @endif
                            @if($selectedSourceCourse->klassen_id)
                                <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1">Klasse {{ $selectedSourceCourse->klassen_id }}</span>
                            @endif
                            @if($selectedSourceCourse->termin_id)
                                <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1">Termin {{ $selectedSourceCourse->termin_id }}</span>
                            @endif
                            <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1">
                                geloescht am {{ optional($selectedSourceCourse->deleted_at)->format('d.m.Y H:i') ?: '-' }}
                            </span>
                        </div>

                        @if($selectedPreview)
                            <div class="mt-3 rounded-2xl border border-emerald-200 bg-white/70 p-3">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Transfervorschau</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($selectedPreview['recoverable'] as $item)
                                        <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-xs text-emerald-800">
                                            {{ $item['label'] }}
                                            @if(($item['count'] ?? 0) > 0)
                                                <span class="font-semibold">{{ $item['count'] }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>

                                @if(!empty($selectedPreview['missing']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($selectedPreview['missing'] as $item)
                                            <span class="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs text-rose-700">
                                                {{ $item }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <p class="mt-3 text-xs text-emerald-800">
                                    {{ $selectedPreview['summary'] }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                    @forelse($deletedCourses as $deletedCourse)
                        @php
                            $isSelected = $selectedSourceCourseId === $deletedCourse->id;
                            $tutorName = trim(($deletedCourse->tutor->vorname ?? '').' '.($deletedCourse->tutor->nachname ?? ''));
                            $preview = $deletedCourse->transfer_preview ?? null;
                        @endphp

                        <article
                            wire:key="deleted-course-{{ $deletedCourse->id }}"
                            @class([
                                'rounded-2xl border p-4 transition',
                                'border-sky-300 bg-sky-50 shadow-sm' => $isSelected,
                                'border-slate-200 bg-white hover:border-slate-300' => ! $isSelected,
                            ])
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-semibold text-slate-900">
                                            {{ $deletedCourse->title ?: 'Ohne Titel' }}
                                        </h3>

                                        @if($isSelected)
                                            <span class="rounded-full border border-sky-200 bg-white px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-sky-700">
                                                ausgewaehlt
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $deletedCourse->course_short_name ?: 'Keine Kurzbezeichnung' }}
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-700">
                                        @if($deletedCourse->klassen_id)
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1">Klasse {{ $deletedCourse->klassen_id }}</span>
                                        @endif

                                        @if($deletedCourse->termin_id)
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1">Termin {{ $deletedCourse->termin_id }}</span>
                                        @endif

                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1">
                                            {{ optional($deletedCourse->planned_start_date)->format('d.m.Y') ?: '-' }}
                                            bis
                                            {{ optional($deletedCourse->planned_end_date)->format('d.m.Y') ?: '-' }}
                                        </span>

                                        <span class="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-rose-700">
                                            geloescht am {{ optional($deletedCourse->deleted_at)->format('d.m.Y H:i') ?: '-' }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:w-fit sm:min-w-[16rem]">
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                            <div class="font-semibold">Tage</div>
                                            <div class="mt-1 text-lg font-bold leading-none">{{ (int) $deletedCourse->course_days_total }}</div>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                            <div class="font-semibold">Teilnehmer</div>
                                            <div class="mt-1 text-lg font-bold leading-none">{{ (int) $deletedCourse->active_participants_total }}</div>
                                        </div>
                                    </div>

                                    @if($tutorName !== '')
                                        <div class="mt-3 text-xs text-slate-500">
                                            Dozent: {{ $tutorName }}
                                        </div>
                                    @endif

                                    @if($preview)
                                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                Aktuell uebertragbar
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach($preview['recoverable'] as $item)
                                                    <span class="rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-xs text-emerald-800">
                                                        {{ $item['label'] }}
                                                        @if(($item['count'] ?? 0) > 0)
                                                            <span class="font-semibold">{{ $item['count'] }}</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>

                                            @if(!empty($preview['missing']))
                                                <div class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-rose-500">
                                                    Aktuell nicht mehr vorhanden
                                                </div>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach($preview['missing'] as $item)
                                                        <span class="rounded-full border border-rose-200 bg-white px-2.5 py-1 text-xs text-rose-700">
                                                            {{ $item }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <p class="mt-3 text-xs text-slate-600">
                                                {{ $preview['summary'] }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <x-secondary-button wire:click="selectSourceCourse({{ $deletedCourse->id }})">
                                        {{ $isSelected ? 'Ausgewaehlt' : 'Auswaehlen' }}
                                    </x-secondary-button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                            Keine geloeschten Kurse mit passenden uebertragbaren Daten fuer diesen Zielkurs gefunden.
                        </div>
                    @endforelse
                </div>

                @error('selectedSourceCourseId')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                @if($deletedCourses->hasPages())
                    <div>
                        {{ $deletedCourses->links() }}
                    </div>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Die Vorschau zeigt Berichtshefte nur dann als uebertragbar an, wenn im Zielkurs bereits ein Kurstag mit demselben Datum existiert.
                </p>

                <div class="flex items-center gap-2">
                    <x-secondary-button wire:click="close">
                        Abbrechen
                    </x-secondary-button>

                    <x-button
                        wire:click="confirmSourceSelection"
                        wire:loading.attr="disabled"
                        :disabled="! $selectedSourceCourseId"
                    >
                        Quelle auswaehlen
                    </x-button>
                </div>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
