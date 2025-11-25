<div x-data="{ openId: null }" class="space-y-3">
    @if($days->isEmpty())
        <div class="text-sm text-neutral-400">
            Keine Termine vorhanden.
        </div>
    @else
        <div class="text-xs text-neutral-500 mb-1">
            Unterrichtstage mit Dokumentation & Anwesenheitsübersicht
        </div>

        <div class="space-y-2">
            @foreach($days as $day)
                <div
                    class="rounded-2xl border border-neutral-100 bg-white shadow-sm/5"
                    :class="{ 'border-sky-200 bg-sky-50/40': openId === {{ $day['id'] }} }"
                >
                    {{-- Header: kompletter Tages-Kasten als Button zum Aufklappen --}}
                    <button
                        type="button"
                        class="w-full px-3 py-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between text-left"
                        @click="openId = (openId === {{ $day['id'] }}) ? null : {{ $day['id'] }}"
                    >
                        {{-- Linke Seite: Datum, Zeit, Raum --}}
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-sky-50">
                                    <i class="fad fa-calendar-day text-[13px] text-sky-600"></i>
                                </span>
                                <div>
                                    <div class="text-sm font-semibold text-neutral-800">
                                        {{ $day['date']->locale('de')->isoFormat('dd, ll') }}
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

                        {{-- Rechte Seite: Anwesenheits-Stats --}}
                        <div class="mt-2 sm:mt-0 flex flex-wrap items-center gap-2 text-[11px]">
                            @php $a = $day['attendance']; @endphp

                            <div class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5">
                                <i class="fal fa-check-circle text-[11px]"></i>
                                <span>anwesend: {{ $a['present'] }}</span>
                            </div>

                            <div class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-2 py-0.5">
                                <i class="fal fa-times-circle text-[11px]"></i>
                                <span>fehlend: {{ $a['absent'] }}</span>
                            </div>

                            <div class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5">
                                <i class="fal fa-envelope-open-text text-[11px]"></i>
                                <span>entschuldigt: {{ $a['excused'] }}</span>
                            </div>

                            <div class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5">
                                <i class="fal fa-user-clock text-[11px]"></i>
                                <span>früher gegangen: {{ $a['left_early'] }}</span>
                            </div>
                        </div>
                    </button>

                    {{-- Collapsible Dokumentation --}}
                    <div
                        x-show="openId === {{ $day['id'] }}"
                        x-collapse
                        x-cloak
                    >
                        <div class="px-3 pb-3 border-t border-dashed border-neutral-200">
                            <div class="pt-2">
                                <div class="text-[11px] uppercase tracking-wide text-neutral-400 mb-1">
                                    Dokumentation
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
            @endforeach
        </div>
    @endif
</div>
