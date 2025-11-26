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
                        class="px-2 "
                    >
                        <i class="fad fa-download text-[16px]"></i>
                        <span class="hidden md:inline-block ml-2">Downloads</span>
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
                            @if(! $this->canExportDoku) disabled @endif
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50
                                {{ $this->canExportDoku ? '' : 'opacity-40 cursor-not-allowed pointer-events-none' }}"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Dokumentation</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportMaterialConfirmationsPdf"
                            @if(! $this->canExportMaterialConfirmations) disabled @endif
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50
                                {{ $this->canExportMaterialConfirmations ? '' : 'opacity-40 cursor-not-allowed pointer-events-none' }}"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Material-Bestätigungen</span>
                        </button>

                        <button
                            type="button"
                            wire:click="exportInvoicePdf"
                            @if(! $this->canExportInvoice) disabled @endif
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50
                                {{ $this->canExportInvoice ? '' : 'opacity-40 cursor-not-allowed pointer-events-none' }}"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Dozenten-Rechnung</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportRedThreadPdf"
                            @if(! $this->canExportRedThread) disabled @endif
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50
                                {{ $this->canExportRedThread ? '' : 'opacity-40 cursor-not-allowed pointer-events-none' }}"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Roter Faden</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportExamResultsPdf"
                            @if(! $this->canExportExamResults) disabled @endif
                            class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50
                                {{ $this->canExportExamResults ? '' : 'opacity-40 cursor-not-allowed pointer-events-none' }}"
                        >
                            <i class="fal fa-download text-[14px] text-gray-500"></i>
                            <span>Prüfungsergebnisse</span>
                        </button>

                    </div>
                </x-slot>
            </x-ui.dropdown.anchor-dropdown>
    </div>
    <div class="p-4 rounded-2xl border border-neutral-200 bg-white">

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
    </div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Kasten 1: Tutor + Teilnehmer + Termine --}}
    <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
        <div class="flex items-center justify-between text-xs text-neutral-500 mb-2">
            <span class="font-semibold text-neutral-700">Kursüberblick</span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-600 border border-slate-200">
                <i class="fal fa-hashtag text-[11px]"></i>
                <span class="text-[11px]">ID {{ $course->id }}</span>
            </span>
        </div>

        {{-- Tutor --}}
        <div class="flex items-start gap-3">
            <div class="mt-1 flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 border border-sky-100">
                <i class="fal fa-user-tie text-sky-600"></i>
            </div>
            <div class="flex-1">
                <div class="text-[11px] uppercase tracking-wide text-neutral-500">Tutor</div>
                <div class="font-medium text-sm">
                    @if($course->tutor)
                        <x-user.public-info :person="$course->tutor" />
                    @else
                        <span class="text-neutral-400">Noch nicht zugewiesen</span>
                    @endif
                </div>

                {{-- Teilnehmer & Termine als kleine Stats --}}
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <div class="inline-flex items-center gap-2 px-2 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                        <i class="fal fa-users text-[13px]"></i>
                        <span class="font-semibold text-[12px]">
                            {{ (int)($course->participants_count ?? 0) }}
                        </span>
                        <span class="text-[11px]">Teilnehmer</span>
                    </div>

                    <div class="inline-flex items-center gap-2 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i class="fal fa-calendar-day text-[13px]"></i>
                        <span class="font-semibold text-[12px]">
                            {{ (int)($course->dates_count ?? 0) }}
                        </span>
                        <span class="text-[11px]">Unterrichtstage</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kasten 2: Indikatoren (Doku, Roter Faden, Bestätigungen, Rechnung) --}}
    <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
        <div class="flex items-center justify-between text-xs text-neutral-500 mb-2">
            <span class="font-semibold text-neutral-700">Unterlagen & Status</span>
            <i class="fal fa-info-circle text-neutral-400" title="Status relevanter Kursunterlagen"></i>
        </div>

<div class="mt-1 grid grid-cols-2 gap-3 text-xs">

    {{-- Dokumentation --}}
    <div
        class="relative group/box flex items-center justify-between gap-2 rounded-xl border border-neutral-100 bg-gray-50 px-2 py-2"
    >
        {{-- Icon + Label --}}
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50">
                <i class="fal fa-chalkboard-teacher text-[13px] text-slate-700"></i>
            </span>
            <span class="text-[11px] leading-snug">Dokumentation</span>
        </div>

        {{-- Status Icon --}}
        <div class="shrink-0 transition-opacity duration-150 group-hover/box:opacity-0">
            {!! $course->documentation_icon_html !!}
        </div>

        {{-- Download Button --}}
        <button
            type="button"
            wire:click="exportDokuPdf"
            class="absolute right-2 top-1/2 -translate-y-1/2 
                   opacity-0 group-hover/box:opacity-100
                   transition-opacity duration-150 
                   flex items-center justify-center
                   h-7 w-7 rounded-full
                   bg-primary-100 text-primary-700 
                   hover:bg-primary-200
                   cursor-pointer shadow-sm"
        >
            <i class="fal fa-download text-[14px]"></i>
        </button>
    </div>



    {{-- Roter Faden --}}
    <div
        class="relative group/box flex items-center justify-between gap-2 rounded-xl border border-neutral-100 bg-gray-50 px-2 py-2"
    >
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50">
                <i class="fal fa-file-pdf text-[13px] text-slate-700"></i>
            </span>
            <span class="text-[11px] leading-snug">Roter Faden</span>
        </div>

        <div class="shrink-0 transition-opacity duration-150 group-hover/box:opacity-0">
            {!! $course->red_thread_icon_html !!}
        </div>

        <button
            type="button"
            wire:click="exportRedThreadPdf"
            class="absolute right-2 top-1/2 -translate-y-1/2 
                   opacity-0 group-hover/box:opacity-100
                   transition-opacity duration-150 
                   flex items-center justify-center
                   h-7 w-7 rounded-full
                   bg-primary-100 text-primary-700 
                   hover:bg-primary-200
                   cursor-pointer shadow-sm"
        >
            <i class="fal fa-download text-[14px]"></i>
        </button>
    </div>



    {{-- Materialbestätigungen --}}
    <div
        class="relative group/box flex items-center justify-between gap-2 rounded-xl border border-neutral-100 bg-gray-50 px-2 py-2"
    >
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50">
                <i class="fal fa-file-signature text-[13px] text-slate-700"></i>
            </span>
            <span class="text-[11px] leading-snug">Materialbestätigungen</span>
        </div>

        <div class="shrink-0 transition-opacity duration-150 group-hover/box:opacity-0">
            {!! $course->participants_confirmations_icon_html !!}
        </div>

        <button
            type="button"
            wire:click="exportMaterialConfirmationsPdf"
            class="absolute right-2 top-1/2 -translate-y-1/2 
                   opacity-0 group-hover/box:opacity-100
                   transition-opacity duration-150 
                   flex items-center justify-center
                   h-7 w-7 rounded-full
                   bg-primary-100 text-primary-700 
                   hover:bg-primary-200
                   cursor-pointer shadow-sm"
        >
            <i class="fal fa-download text-[14px]"></i>
        </button>
    </div>



    {{-- Dozenten-Rechnung --}}
    <div
        class="relative group/box flex items-center justify-between gap-2 rounded-xl border border-neutral-100 bg-gray-50 px-2 py-2"
    >
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50">
                <i class="fal fa-money-check-alt text-[13px] text-slate-700"></i>
            </span>
            <span class="text-[11px] leading-snug">Dozenten-Rechnung</span>
        </div>

        <div class="shrink-0 transition-opacity duration-150 group-hover/box:opacity-0">
            {!! $course->invoice_icon_html !!}
        </div>

        <button
            type="button"
            wire:click="exportInvoicePdf"
            class="absolute right-2 top-1/2 -translate-y-1/2 
                   opacity-0 group-hover/box:opacity-100
                   transition-opacity duration-150 
                   flex items-center justify-center
                   h-7 w-7 rounded-full
                   bg-primary-100 text-primary-700 
                   hover:bg-primary-200
                   cursor-pointer shadow-sm"
        >
            <i class="fal fa-download text-[14px]"></i>
        </button>
    </div>

</div>

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
            <livewire:admin.courses.course-days-panel :course="$course" lazy />  
        </x-ui.accordion.tab-panel>
        <x-ui.accordion.tab-panel for="courseParticipants">
            <livewire:admin.courses.course-participants-panel :course="$course" lazy />
        </x-ui.accordion.tab-panel>
    </x-ui.accordion.tabs>
</div>  
