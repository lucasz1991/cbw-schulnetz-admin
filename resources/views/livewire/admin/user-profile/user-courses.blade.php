<div class="relative w-full">  
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <h2 class="text-2xl font-bold text-gray-800">
                {{ $user->role === 'tutor' ? 'Kurse' : 'Verträge und Bausteine' }}
            </h2>
            <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700">
                {{ method_exists($courses, 'total') ? $courses->total() : $courses->count() }}
            </span>
        </div>

        <input
            type="text"
            wire:model.live.debounce.400ms="search"
            placeholder="Suchen ... (Titel, Klasse, Termin, Status)"
            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-200 sm:w-80"
        />
    </div>

    @if($user->role !== 'tutor' && $user->persons->isEmpty() && !$user->person)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Keine Person verkn&uuml;pft. Es k&ouml;nnen keine Teilnehmer-Kurse geladen werden.
        </div>
    @elseif($user->role === 'tutor' && (!$courses || $courses->count() === 0))
        <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
            Keine Eintr&auml;ge gefunden.
        </div>
    @elseif($user->role !== 'tutor' && empty($contractCourseGroups))
        <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
            Keine Vertr&auml;ge oder Bausteine gefunden.
        </div>
    @else
        @php
            $displayGroups = $user->role === 'tutor'
                ? [[
                    'key' => 'tutor-courses',
                    'contract' => null,
                    'courses' => method_exists($courses, 'getCollection') ? $courses->getCollection() : collect($courses),
                ]]
                : $contractCourseGroups;
        @endphp

        <div class="space-y-5">
        @foreach($displayGroups as $group)
            @php
                $contract = $group['contract'] ?? null;
                $groupCourses = collect($group['courses'] ?? []);
                $period = $contract && (($contract['beginn_fmt'] ?? null) || ($contract['ende_fmt'] ?? null))
                    ? (($contract['beginn_fmt'] ?? '-') . ' - ' . ($contract['ende_fmt'] ?? '-'))
                    : null;
                $isUnassigned = (bool) ($contract['is_unassigned'] ?? false);
                $state = $contract['contract_state'] ?? 'unknown';
                [$stateLabel, $stateClasses] = match ($state) {
                    'current' => ['Aktueller Vertrag', 'border-emerald-200 bg-emerald-100 text-emerald-700'],
                    'open' => ['Folgevertrag', 'border-sky-200 bg-sky-100 text-sky-700'],
                    'closed' => ['Abgeschlossen', 'border-slate-200 bg-slate-100 text-slate-600'],
                    'unassigned' => ['Nicht eindeutig zugeordnet', 'border-amber-200 bg-amber-100 text-amber-800'],
                    default => ['Vertrag', 'border-slate-200 bg-slate-100 text-slate-600'],
                };
            @endphp

            <section wire:key="contract-course-group-{{ md5($group['key']) }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @if($contract)
                    <header class="border-b border-slate-200 bg-slate-50/80 px-4 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $stateClasses }}">
                                        {{ $stateLabel }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
                                        {{ $groupCourses->count() }} Baustein{{ $groupCourses->count() === 1 ? '' : 'e' }}
                                    </span>
                                </div>

                                <h3 class="mt-2 text-base font-semibold text-slate-900">
                                    @if($isUnassigned)
                                        {{ !empty($contract['teilnehmer_id']) ? 'Vertrag '.$contract['teilnehmer_id'] : 'Vertrag nicht ermittelbar' }}
                                    @else
                                        {{ $contract['program_title'] ?? 'Teilnehmervertrag' }}
                                    @endif
                                </h3>

                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                                    @if(!empty($contract['teilnehmer_id']))
                                        <span>Teilnehmer-ID: <strong>{{ $contract['teilnehmer_id'] }}</strong></span>
                                    @endif
                                    @if(!empty($contract['teilnehmer_nr']))
                                        <span>TN-Nr.: <strong>{{ $contract['teilnehmer_nr'] }}</strong></span>
                                    @endif
                                    @if($period)
                                        <span>Zeitraum: <strong>{{ $period }}</strong></span>
                                    @endif
                                    @if(!empty($contract['person_id']))
                                        <span>Person: <strong>{{ $contract['person_id'] }}</strong></span>
                                    @endif
                                </div>

                                @if($isUnassigned)
                                    <p class="mt-2 text-xs text-amber-800">
                                        Diese Alt-Datens&auml;tze besitzen noch keine eindeutige Vertragskennung und werden deshalb nicht automatisch einem Vertrag zugeschlagen.
                                    </p>
                                @endif
                            </div>

                            @if(!empty($contract['person_name']))
                                <div class="text-sm font-medium text-slate-700">{{ $contract['person_name'] }}</div>
                            @endif
                        </div>
                    </header>
                @endif

                @if($groupCourses->isEmpty())
                    <div class="px-4 py-5 text-sm text-slate-500">
                        Diesem Vertrag sind aktuell keine Bausteine zugeordnet.
                    </div>
                @else
        <div class="overflow-x-auto" x-data="{ openId: null }">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-gray-600">
                    <tr>
                        <th class="w-10 px-3 py-2.5"></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide">Baustein</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide">Kursstatus</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide">Zeitraum</th>
                        <th class="w-12 px-3 py-2.5"></th>
                    </tr>
                </thead>

                @foreach($groupCourses as $course)
                    @php
                        $rowKey = $course->id . '-' . ((int)($course->_person_id ?? 0)) . '-' . ((int)($course->_enrollment_id ?? 0));
                        $meta = $courseMeta[$rowKey] ?? [];

                        $courseResult = $meta['course_result'] ?? null;
                        $enrollmentResults = $meta['enrollment_results'] ?? [];
                        $attendance = $meta['attendance'] ?? null;

                        $materialAck = array_key_exists('material_ack', $meta) ? $meta['material_ack'] : null;
                        $materialAckAt = $meta['material_ack_at'] ?? null;

                        $resultLabel = $courseResult->result ?? ($enrollmentResults['result'] ?? ($enrollmentResults['status'] ?? null));
                        $resultStateRaw = mb_strtolower(trim(($courseResult->status ?? '') . ' ' . ($courseResult->result ?? '')));
                        $resultClasses = 'bg-slate-100 text-slate-700 border-slate-200';

                        if ($resultLabel) {
                            // Negative Begriffe zuerst: "nicht bestanden" enthaelt "bestanden"
                            // und wuerde sonst faelschlich gruen dargestellt.
                            if (str_contains($resultStateRaw, 'nicht') || str_contains($resultStateRaw, 'fail')) {
                                $resultClasses = 'bg-rose-100 text-rose-700 border-rose-200';
                            } elseif (str_contains($resultStateRaw, 'bestanden') || str_contains($resultStateRaw, 'passed')) {
                                $resultClasses = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                            }
                        }

                        // Kennz-Fallback: bei Pruefungs-Kennzeichen (B/D/X/XO/E) ist result NULL —
                        // ohne Fallback erschiene z. B. eine nicht bestandene (externe) Pruefung als "-".
                        if (! $resultLabel && $courseResult) {
                            $statusKennz = mb_strtoupper(trim((string) ($courseResult->status ?? '')));

                            [$kennzLabel, $kennzClasses] = match ($statusKennz) {
                                'B'       => ['Bestanden (extern)', 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                                'D'       => ['Nicht bestanden', 'bg-rose-100 text-rose-700 border-rose-200'],
                                'X'       => ['Nicht teilgenommen (extern)', 'bg-amber-100 text-amber-700 border-amber-200'],
                                'XO', 'E' => ['Ergebnis offen', 'bg-blue-100 text-blue-700 border-blue-200'],
                                default   => [null, null],
                            };

                            if ($kennzLabel) {
                                $resultLabel = $kennzLabel;
                                $resultClasses = $kennzClasses;
                            }
                        }
                    @endphp

                    <tbody wire:key="user-course-{{ $rowKey }}" class="group">
                        <tr
                            class="cursor-pointer border-t border-slate-100 transition"
                            :class="openId === '{{ $rowKey }}' ? 'bg-sky-50/50' : 'hover:bg-slate-50/70'"
                            @click="openId = (openId === '{{ $rowKey }}') ? null : '{{ $rowKey }}'"
                        >
                            <td class="px-2 py-3 align-top">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-gray-700"
                                      :class="openId === '{{ $rowKey }}' ? 'border-sky-300 bg-white text-sky-700' : 'border-gray-300 bg-white'">
                                    <svg x-show="openId !== '{{ $rowKey }}'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <svg x-show="openId === '{{ $rowKey }}'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold text-slate-900">{{ $course->title ?? '-' }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    Klasse: {{ $course->_enrollment_klassen_id ?? $course->klassen_id ?? '-' }}
                                    @if(!empty($course->_enrollment_kurzbez_ba))
                                        | {{ $course->_enrollment_kurzbez_ba }}
                                    @endif
                                    | Termin: {{ $course->_enrollment_termin_id ?? $course->termin_id ?? '-' }}
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <span class="{{ $course->status_badge_classes }}">{{ $course->status_label }}</span>
                            </td>

                            <td class="px-4 py-3 align-top text-slate-700">
                                {{ optional($course->planned_start_date)->format('d.m.Y') ?? '-' }}
                                -
                                {{ optional($course->planned_end_date)->format('d.m.Y') ?? '-' }}
                            </td>

                            <td class="relative px-2 py-3 align-top" @click.stop>
                                <x-ui.dropdown.anchor-dropdown
                                    align="right"
                                    width="48"
                                    dropdownClasses="mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                                    contentClasses="bg-white py-1"
                                    :overlay="false"
                                    :trap="false"
                                    :scrollOnOpen="false"
                                    :offset="6"
                                >
                                    <x-slot name="trigger">
                                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50" title="Aktionen">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                            </svg>
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <x-ui.dropdown.dropdown-link as="a" href="{{ route('admin.courses.show', $course->id) }}">
                                            <i class="far fa-eye mr-2"></i>
                                            Baustein Details
                                        </x-ui.dropdown.dropdown-link>
                                    </x-slot>
                                </x-ui.dropdown.anchor-dropdown>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="5">
                                <div x-cloak x-show="openId === '{{ $rowKey }}'" x-collapse>
                                    <div class="border-t border-sky-200 bg-gradient-to-b from-sky-50/60 to-sky-50/30 px-4 py-4">
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Dozent</h4>
                                                @php $tutor = $course->tutor ?? null; @endphp
                                                @if($tutor)
                                                    <x-user.public-info :person="$tutor" :size="10" />
                                                @else
                                                    <div class="text-sm text-gray-500">Kein Dozent zugeordnet.</div>
                                                @endif
                                            </div>

                                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Bildungsmittel-Best&auml;tigung</h4>
                                                @if(is_bool($materialAck))
                                                    @if($materialAck)
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                            <i class="far fa-check-circle"></i>
                                                            Best&auml;tigt
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                                            <i class="far fa-times-circle"></i>
                                                            Nicht best&auml;tigt
                                                        </span>
                                                    @endif
                                                    @if($materialAckAt)
                                                        <div class="mt-2 text-xs text-gray-500">
                                                            {{ \Carbon\Carbon::parse($materialAckAt)->format('d.m.Y H:i') }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <div class="text-sm text-gray-500">-</div>
                                                @endif
                                            </div>

                                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Ergebnisse</h4>
                                                @if($resultLabel)
                                                    <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $resultClasses }}">
                                                        <i class="far fa-award"></i>
                                                        {{ $resultLabel }}
                                                    </span>
                                                @elseif(is_array($enrollmentResults) && count($enrollmentResults))
                                                    <div class="space-y-1 text-sm text-gray-700">
                                                        @foreach($enrollmentResults as $key => $value)
                                                            @continue(in_array($key, ['materials_ack', 'acknowledged', 'attendance'], true))
                                                            <div>
                                                                <span class="text-gray-500">{{ is_string($key) ? $key . ':' : 'Wert:' }}</span>
                                                                <span>
                                                                    @if(is_array($value) || is_object($value))
                                                                        {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                                                    @else
                                                                        {{ $value === true ? 'Ja' : ($value === false ? 'Nein' : ($value ?? '-')) }}
                                                                    @endif
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-sm text-gray-500">-</div>
                                                @endif
                                            </div>

                                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Fehlzeiten / Anwesenheit</h4>
                                                @if($attendance && ($attendance['tracked_days'] ?? 0) > 0)
                                                    <div class="mb-2 flex flex-wrap gap-1.5 text-[11px]">
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-slate-700">
                                                            <i class="far fa-calendar-check"></i>
                                                            Erfasst: {{ $attendance['tracked_days'] }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-emerald-700">
                                                            <i class="far fa-check-circle"></i>
                                                            Anwesend: {{ $attendance['present'] }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-100 px-2 py-0.5 text-rose-700">
                                                            <i class="far fa-times-circle"></i>
                                                            Unentschuldigt: {{ $attendance['absent'] }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-amber-700">
                                                            <i class="far fa-envelope-open"></i>
                                                            Entschuldigt: {{ $attendance['excused'] }}
                                                        </span>
                                                    </div>
                                                    @if(($attendance['late_count'] ?? 0) > 0 || ($attendance['left_early_count'] ?? 0) > 0)
                                                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                                                            @if(($attendance['late_count'] ?? 0) > 0)
                                                                <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-amber-700">
                                                                    <i class="far fa-clock"></i>
                                                                    Versp&auml;tet: {{ $attendance['late_count'] }} ({{ $attendance['late_minutes'] }} Min.)
                                                                </span>
                                                            @endif
                                                            @if(($attendance['left_early_count'] ?? 0) > 0)
                                                                <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-100 px-2 py-0.5 text-indigo-700">
                                                                    <i class="far fa-walking"></i>
                                                                    Fr&uuml;her gegangen: {{ $attendance['left_early_count'] }} ({{ $attendance['left_early_minutes'] }} Min.)
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @else
                                                    <div class="text-sm text-gray-500">Keine personenbezogene Anwesenheit erfasst.</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
                @endif
            </section>
        @endforeach
        </div>

        @if($user->role === 'tutor' && method_exists($courses, 'links'))
            <div class="mt-3">
                {{ $courses->withQueryString()->links('pagination::tailwind') }}
            </div>
        @endif
    @endif

    <div
        wire:loading.delay.class.remove="opacity-0"
        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 opacity-0 transition-opacity"
    >
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
            <span class="loader"></span>
            <span class="text-sm text-gray-700">wird geladen...</span>
        </div>
    </div>
</div>
