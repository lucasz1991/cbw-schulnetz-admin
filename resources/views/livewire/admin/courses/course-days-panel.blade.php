<div x-data="{ openId: null }" class="space-y-3">
    @if($days->isEmpty())
        <div class="text-sm text-neutral-400">
            Keine Termine vorhanden.
        </div>
    @else
        <div class="flex items-center justify-between text-xs text-neutral-500 mb-2">
            <span class="font-semibold text-neutral-700">Unterrichtstage</span>
            <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-700 border border-sky-100 px-2 py-0.5">
                <i class="fal fa-layer-group text-[11px]"></i>
                <span>{{ $days->count() }} Einheiten</span>
            </span>
        </div>

        <div class="space-y-3">
            @foreach($days as $day)
                <div
                    wire:key="course-day-{{ $day['id'] }}"
                    class="relative rounded-2xl border border-neutral-100 bg-white shadow-sm/5 overflow-hidden transition duration-150 hover:shadow-md"
                    :class="{ 'border-sky-200 bg-sky-50/40': openId === {{ $day['id'] }} }"
                >
                    <div class="absolute left-0 top-0 h-full w-1 bg-sky-100" aria-hidden="true"></div>

                    {{-- Header: kompletter Tages-Kasten als Button zum Aufklappen --}}
                    <button
                        type="button"
                        class="w-full px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between text-left"
                        @click="openId = (openId === {{ $day['id'] }}) ? null : {{ $day['id'] }}"
                    >
                        {{-- Linke Seite: Datum, Zeit, Raum --}}
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 border border-sky-100">
                                    <i class="fad fa-calendar-day text-[14px] text-sky-600"></i>
                                </div>
                                <div class="leading-tight">
                                    <div class="text-sm font-semibold text-neutral-800">
                                        {{ $day['date']->locale('de')->isoFormat('dddd, ll') }}
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        @if($day['start_time'] || $day['end_time'])
                                            {{ $day['start_time'] ?? '—' }}–{{ $day['end_time'] ?? '—' }}
                                        @else
                                            Zeit nicht hinterlegt
                                        @endif
                                        @if($day['room'])
                                            · Raum {{ $day['room'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rechte Seite: Status-Badges fuer Doku & Anwesenheit --}}
                        <div class="mt-2 sm:mt-0 flex flex-wrap items-center gap-2 text-[11px]">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] {{ $day['note_status_classes'] }}">
                                <i class="fal fa-flag text-[11px]"></i>
                                <span>Doku: {{ $day['note_status_label'] }}</span>
                            </span>

                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5
                                {{ $day['has_attendance'] ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-neutral-50 text-neutral-500 border-neutral-200' }}">
                                <i class="fal fa-user-check text-[11px]"></i>
                                <span>{{ $day['has_attendance'] ? 'Anwesenheit erfasst' : 'Keine Anwesenheit' }}</span>
                            </span>
                        </div>
                    </button>

                    {{-- Collapsible: Anwesenheit + Dokumentation --}}
                    <div
                        x-show="openId === {{ $day['id'] }}"
                        x-collapse
                        x-cloak
                    >
                        <div class="px-4 pb-4 border-t border-dashed border-neutral-200 bg-neutral-50/50">
                            <div class="pt-3 space-y-3">
                                {{-- Anwesenheitsuebersicht --}}
                                <div class="rounded-xl border border-neutral-100 bg-white p-3 shadow-sm/5">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-[11px] uppercase tracking-wide text-neutral-400">Anwesenheit</div>
                                        @php $a = $day['attendance']; @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 text-slate-600 border border-slate-200 px-2 py-0.5 text-[11px]">
                                            <i class="fal fa-users text-[11px]"></i>
                                            <span>Gesamt: {{ $a['total'] }}</span>
                                        </span>
                                    </div>

                                    @if($day['has_attendance'])
                                        <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5">
                                                <i class="fal fa-check-circle text-[11px]"></i>
                                                <span>anwesend: {{ $a['present'] }}</span>
                                            </span>

                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-2 py-0.5">
                                                <i class="fal fa-times-circle text-[11px]"></i>
                                                <span>fehlend: {{ $a['absent'] }}</span>
                                            </span>

                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5">
                                                <i class="fal fa-envelope-open-text text-[11px]"></i>
                                                <span>entschuldigt: {{ $a['excused'] }}</span>
                                            </span>

                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5">
                                                <i class="fal fa-user-clock text-[11px]"></i>
                                                <span>frueher gegangen: {{ $a['left_early'] }}</span>
                                            </span>
                                        </div>
                                    @else
                                        <div class="text-xs text-neutral-400">
                                            Keine Anwesenheit erfasst.
                                        </div>
                                    @endif
                                </div>

                                {{-- Dokumentation --}}
                                <div class="rounded-xl border border-neutral-100 bg-white p-3 shadow-inner/5">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-[11px] uppercase tracking-wide text-neutral-400">Dokumentation</div>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] {{ $day['note_status_classes'] }}">
                                            <i class="fal fa-flag text-[11px]"></i>
                                            <span>{{ $day['note_status_label'] }}</span>
                                        </span>
                                    </div>

                                    @if(!empty($day['notes_html']))
                                        <div class="prose prose-sm max-w-none">
                                            {!! $day['notes_html'] !!}
                                        </div>
                                    @else
                                        <div class="text-xs text-neutral-400">
                                            Keine Unterrichtsdokumentation hinterlegt.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
