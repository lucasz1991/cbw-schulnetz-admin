<div class="px-4 py-4 !pt-0 space-y-6">
    <div class="flex items-start justify-between gap-4">
        {{-- linke Buttons --}}
        <div>
            <x-ui.buttons.button-basic href="{{ url()->previous() }}" :size="'sm'" class="px-2">← Zurück</x-ui.buttons.button-basic>
        </div>
        {{-- rechte Buttons --}}
        <x-ui.dropdown.anchor-dropdown
                align="right"
                width="48"
                dropdownClasses="mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                contentClasses="bg-white"
                :overlay="false"
                :trap="false"
                :scrollOnOpen="false"
                :offset="6"
            >
                {{-- Trigger bleibt wie bisher --}}
                <x-slot name="trigger">
                    <x-ui.buttons.button-basic
                        type="button"
                        :size="'sm'"
                        class="px-2"
                    >
                        <i class="fad fa-download text-[16px]"></i>
                        <span class="hidden md:inline-block ml-2">Exporte</span>
                    </x-ui.buttons.button-basic>
                </x-slot>

                <x-slot name="content">
                    <div class="py-1 text-sm text-gray-700">
                        <button
                            type="button"
                            wire:click="exportAttendancePdf"
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Anwesenheit</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportDokuPdf"
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Dokumentation</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportMaterialConfirmationsPdf"
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Material-Bestätigungen</span>
                        </button>

                        <button
                            type="button"
                            wire:click="exportInvoicePdf"
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Dozenten-Rechnung</span>
                        </button>

                        <button
                            type="button"
                            wire:click="exportExamResultsPdf"
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Prüfungsergebnisse</span>
                        </button>
                    </div>
                </x-slot>
            </x-ui.dropdown.anchor-dropdown>
    </div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $course->title ?? 'Kurs' }}</h1>
            <div class="mt-1 text-sm text-gray-500">{{ $course->course_short_name }}</div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                @if($course->klassen_id)
                    <span class="px-2 py-0.5 rounded border border-slate-200 bg-slate-50 text-slate-700">Klasse: {{ $course->klassen_id }}</span>
                @endif
                @if($course->termin_id)
                    <span class="px-2 py-0.5 rounded border border-slate-200 bg-slate-50 text-slate-700">Termin: {{ $course->termin_id }}</span>
                @endif
                @if($course->room)
                    <span class="px-2 py-0.5 rounded border border-amber-200 bg-amber-50 text-amber-700">Raum {{ $course->room }}</span>
                @endif 

                {{-- Zeitraum --}}
                <span class="px-2 py-0.5 rounded border border-green-200 bg-green-50 text-green-700">
                    {{ optional($course->planned_start_date)->locale('de')->isoFormat('ll') ?? '—' }}
                    –
                    {{ optional($course->planned_end_date)->locale('de')->isoFormat('ll') ?? '—' }}
                </span>
                @php
                    $status = $this->status;
                    $badge = match($status) {
                        'planned'  => ['bg' => 'bg-sky-50',     'text' => 'text-sky-700',     'label' => 'Geplant'],
                        'active'   => ['bg' => 'bg-green-50',   'text' => 'text-green-700',   'label' => 'Aktiv'],
                        'finished' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'label' => 'Abgeschlossen'],
                        default    => ['bg' => 'bg-gray-50',    'text' => 'text-gray-600',    'label' => '—'],
                    };
                @endphp
                <span class="px-2 py-0.5 rounded border border-gray-200 {{ $badge['bg'] }} {{ $badge['text'] }}">{{ $badge['label'] }}</span>
                @if($course->is_active)
                    <span class="px-2 py-0.5 rounded border border-lime-200 bg-lime-50 text-lime-700">aktiv</span>
                @else
                    <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-gray-600">inaktiv</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Tutor</div>
            <div class="font-medium">
                @if($course->tutor)
                    <x-user.public-info :person="$course->tutor" />
                @else
                    <span class="text-neutral-400">—</span>
                @endif
            </div>
        </div>

        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Teilnehmer</div>
            <div class="font-medium">{{ (int)($course->participants_count ?? 0) }}</div>
        </div>

        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Termine</div>
            <div class="font-medium">{{ (int)($course->dates_count ?? 0) }}</div>
        </div>
    </div>

    @if($course->description)
        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-2">Beschreibung</div>
            <div class="prose max-w-none">{{ $course->description }}</div>
        </div>
    @endif

    <x-ui.accordion.tabs
        :tabs="[
            'courseDays' => [
                'label' => 'Unterrichts Einheiten',
                'icon'  => 'fad fa-calendar-day',
            ],
            'courseParticipants' => [
                'label' => 'Teilnehmer',
                'icon'  => 'fad fa-users',
            ],
        ]"
        :collapseAt="'md'"
        default="courseDays"
        persist-key="tutor.course.{{ $course->id }}.tabs"
        class="mt-4"
    >
        <x-ui.accordion.tab-panel for="courseDays">
            <div class="">
                <div class="flex items-center justify-between mb-3">
                    <div class="inline-flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-sky-50">
                            <i class="fad fa-calendar-day text-[13px] text-sky-600"></i>
                        </span>
                        <div class="text-sm font-semibold">Unterrichts Einheiten</div>
                    </div>
                </div>

                @if($course->days->isNotEmpty())
                    <ul class="divide-y divide-neutral-100 text-sm">
                        @foreach($course->days as $d)
                            <li class="py-2 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="font-medium text-neutral-800">
                                        {{ optional($d->date)->locale('de')->isoFormat('dd, ll') }}
                                    </span>
                                    <span class="text-xs text-neutral-500">
                                        @if(!empty($d->start_time) || !empty($d->end_time))
                                            {{ $d->start_time ?? '—' }}–{{ $d->end_time ?? '—' }}
                                        @else
                                            Zeit nicht hinterlegt
                                        @endif
                                    </span>
                                </div>
                                <div class="text-xs text-neutral-500">
                                    @if(!empty($d->room))
                                        Raum {{ $d->room }}
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-neutral-400">Keine Termine vorhanden.</div>
                @endif
            </div>
        </x-ui.accordion.tab-panel>
        <x-ui.accordion.tab-panel for="courseParticipants">
            <div class="">
                <div class="flex items-center justify-between mb-3">
                    <div class="inline-flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50">
                            <i class="fad fa-users text-[13px] text-emerald-600"></i>
                        </span>
                        <div class="text-sm font-semibold">Teilnehmer</div>
                    </div>
                </div>

                @if($course->participants->isNotEmpty())
                    <ul class="grid md:grid-cols-2 gap-2 text-sm">
                        @foreach($course->participants as $p)
                            <li class="px-3 py-2 rounded-xl border border-transparent hover:border-emerald-100 hover:bg-emerald-50/60 transition">
                                <x-user.public-info :person="$p" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-neutral-400">Keine Teilnehmer vorhanden.</div>
                @endif
            </div>
        </x-ui.accordion.tab-panel>
    </x-ui.accordion.tabs>
</div>  
