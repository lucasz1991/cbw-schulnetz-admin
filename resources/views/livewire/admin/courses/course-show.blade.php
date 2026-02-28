<div class="px-4 py-4 !pt-0 space-y-6">
    <div class="flex items-start justify-between gap-4">
        {{-- linke Buttons --}}
        <div>
            <x-back-button />
        </div>
        {{-- rechte Buttons --}}
        @can('courses.export')
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
                        <x-dropdown-link wire:click.prevent="exportAttendancePdf"  :disabled="!$this->canExportAttendance" class="{{ $this->canExportAttendance ? '' : 'opacity-40 cursor-not-allowed' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Anwesenheit</span>
                        </x-dropdown-link>
                        <x-dropdown-link wire:click.prevent="exportDokuPdf"  :disabled="!$this->canExportDoku" class="{{ $this->canExportDoku ? '' : 'opacity-40 cursor-not-allowed' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Dokumentation</span>
                        </x-dropdown-link>
                        <x-dropdown-link wire:click.prevent="exportMaterialConfirmationsPdf" :disabled="!$this->canExportMaterialConfirmations" class="{{ $this->canExportMaterialConfirmations ? '' : 'opacity-40 cursor-not-allowed' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Bildungsmittel-Bestät.</span>
                        </x-dropdown-link>


                        <x-dropdown-link wire:click.prevent="exportRedThreadPdf"  :disabled="!$this->canExportRedThread" class="{{ $this->canExportRedThread ? '' : 'opacity-40 cursor-not-allowed ' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Roter Faden</span>
                        </x-dropdown-link>
                        <x-dropdown-link wire:click.prevent="exportExamResultsPdf"  :disabled="!$this->canExportExamResults" class="{{ $this->canExportExamResults ? '' : 'opacity-40 cursor-not-allowed ' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Prüfungsergebnisse</span>
                        </x-dropdown-link>
                        <x-dropdown-link wire:click.prevent="exportCourseRatingsPdf" :can="'courses.ratings.view'" :disabled="!$this->canExportCourseRatings" class="{{ $this->canExportCourseRatings ? '' : 'opacity-40 cursor-not-allowed ' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Baustein-Bewertung</span>
                        </x-dropdown-link>
                        
                        <x-dropdown-link wire:click.prevent="exportInvoicePdf" :can="'invoices.view'" :disabled="!$this->canExportInvoice" class="{{ $this->canExportInvoice ? '' : 'opacity-40 cursor-not-allowed ' }}">
                            <i class="fal fa-download text-[14px] text-gray-500 mr-2"></i>
                            <span>Dozenten-Rechnung</span>
                        </x-dropdown-link>
                    </div>
                </x-slot>
            </x-ui.dropdown.anchor-dropdown>
        @endcan
    </div>
    @php
        $status = $this->status;
        $badge = match($status) {
            'planned'  => ['bg' => 'bg-sky-100',     'text' => 'text-sky-800',     'label' => 'Geplant'],
            'active'   => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'label' => 'Aktiv'],
            'finished' => ['bg' => 'bg-indigo-100',  'text' => 'text-indigo-800',  'label' => 'Abgeschlossen'],
            default    => ['bg' => 'bg-slate-100',   'text' => 'text-slate-700',    'label' => 'Unbekannt'],
        };

        $resourceCards = [
            [
                'label' => 'Dokumentation',
                'icon' => 'fal fa-chalkboard-teacher',
                'badge' => $course->documentation_icon_html,
                'can' => $this->canExportDoku,
                'action' => 'exportDokuPdf',
            ],
            [
                'label' => 'Anwesenheit',
                'icon' => 'fal fa-clipboard-list-check',
                'badge' => $course->attendance_icon_html,
                'can' => $this->canExportAttendance,
                'action' => 'exportAttendancePdf',
            ],
            [
                'label' => 'Roter Faden',
                'icon' => 'fal fa-file-pdf',
                'badge' => $course->red_thread_icon_html,
                'can' => $this->canExportRedThread,
                'action' => 'exportRedThreadPdf',
            ],
            [
                'label' => 'Material-Bestaetigungen',
                'icon' => 'fal fa-file-signature',
                'badge' => $course->participants_confirmations_icon_html,
                'can' => $this->canExportMaterialConfirmations,
                'action' => 'exportMaterialConfirmationsPdf',
            ],
            [
                'label' => 'Pruefungsergebnisse',
                'icon' => 'fal fa-clipboard-check',
                'badge' => $course->exam_results_icon_html,
                'can' => $this->canExportExamResults,
                'action' => 'exportExamResultsPdf',
            ],
            [
                'label' => 'Baustein-Bewertung',
                'icon' => 'fal fa-star',
                'badge' => $course->course_ratings_icon_html,
                'can' => $this->canExportCourseRatings && Gate::allows('courses.ratings.view'),
                'action' => 'exportCourseRatingsPdf',
            ],
            [
                'label' => 'Dozenten-Rechnung',
                'icon' => 'fal fa-money-check-alt',
                'badge' => $course->invoice_icon_html,
                'can' => $this->canExportInvoice && Gate::allows('invoices.view'),
                'action' => 'exportInvoicePdf',
            ],
            ];
            @endphp

    <section class="rounded-3xl border border-slate-200 bg-white p-4 md:p-5 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                    <i class="fal fa-layer-group text-[11px]"></i>
                    <span>Bausteinprofil</span>
                </div>

                <h1 class="mt-3 truncate text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">{{ $course->title ?? 'Kurs' }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $course->course_short_name }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-1.5 text-xs">
                    @if($course->klassen_id)
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-700">Klasse {{ $course->klassen_id }}</span>
                    @endif

                    @if($course->termin_id)
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-700">Termin {{ $course->termin_id }}</span>
                    @endif

                    @if($course->room)
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700">Raum {{ $course->room }}</span>
                    @endif

                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">
                        {{ optional($course->planned_start_date)->locale('de')->isoFormat('ll') ?? '-' }}
                        bis
                        {{ optional($course->planned_end_date)->locale('de')->isoFormat('ll') ?? '-' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 md:justify-end">
                <span class="rounded-full border border-slate-200 px-2.5 py-1 text-xs font-semibold {{ $badge['bg'] }} {{ $badge['text'] }}">{{ $badge['label'] }}</span>
                @if($course->is_active)
                    <span class="rounded-full border border-lime-200 bg-lime-50 px-2.5 py-1 text-xs font-semibold text-lime-700">Aktiv</span>
                @else
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">Inaktiv</span>
                @endif
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">ID {{ $course->id }}</span>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section class="xl:col-span-1 rounded-3xl border border-slate-200 bg-white p-5 md:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Baustein Ueberblick</h2>
                <i class="fal fa-info-circle text-slate-300"></i>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-sky-200 bg-sky-50 text-sky-700">
                        <i class="fal fa-user-tie text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Tutor</div>
                        <div class="mt-1 truncate text-sm font-semibold text-slate-800">
                            @if($course->tutor)
                                <x-user.public-info :person="$course->tutor" />
                            @else
                                <span class="text-slate-400">Noch nicht zugewiesen</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-blue-800">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Teilnehmer</span>
                        <i class="fal fa-users text-[13px]"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold leading-none">{{ (int)($course->participants_count ?? 0) }}</div>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Tage</span>
                        <i class="fal fa-calendar-day text-[13px]"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold leading-none">{{ (int)($course->dates_count ?? 0) }}</div>
                </div>
            </div>
        </section>

        <section class="xl:col-span-2 rounded-3xl border border-slate-200 bg-white p-3.5">
            <div class="mb-2.5 flex items-center justify-between">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Unterlagen und Status</h2>
            </div>

            <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 @cannot('courses.export') opacity-50 cursor-not-allowed @endcannot" >
                @foreach($resourceCards as $card)
                    <article class="group rounded-xl border border-slate-200 bg-slate-50/70 px-2.5 py-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700">
                                <i class="{{ $card['icon'] }} text-[11px]"></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[11px] font-semibold text-slate-800">{{ $card['label'] }}</div>
                            </div>

                            <div class="shrink-0 rounded-full border border-slate-200 bg-white px-1 py-0.5">
                                {!! $card['badge'] !!}
                            </div>

                            @if($card['can'])
                                <button
                                    type="button"
                                    wire:click="{{ $card['action'] }}"
                                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100"
                                    title="{{ $card['label'] }} herunterladen"
                                >
                                    <i class="fal fa-download text-[10px]"></i>
                                </button>
                            @else
                                <span
                                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700"
                                    title="{{ $card['label'] }} nicht verfuegbar"
                                >
                                    <i class="fal fa-minus text-[10px]"></i>
                                </span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
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
            <livewire:admin.courses.course-days-panel
                :course="$course"
                :key="'course-days-'.$course->id"
            />  
        </x-ui.accordion.tab-panel>
        <x-ui.accordion.tab-panel for="courseParticipants">
            <livewire:admin.courses.course-participants-panel
                :course="$course"
                :key="'course-participants-'.$course->id"
            />
        </x-ui.accordion.tab-panel>
    </x-ui.accordion.tabs>
</div>  
