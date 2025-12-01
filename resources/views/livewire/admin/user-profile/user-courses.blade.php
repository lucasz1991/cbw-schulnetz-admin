<div class="w-full relative">
    {{-- Kopf: Titel + Suche --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <h2 class="text-2xl font-bold text-gray-800">Kurse</h2>

        <input type="text"
               wire:model.live.debounce.400ms="search"
               placeholder="Suchen … (Titel, Klasse, Termin, Status)"
               class="w-full sm:w-72 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300" />
    </div>

    {{-- Keine Person --}}
    @if(!$user->person)
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-amber-800 text-sm">
            Keine Person verknüpft – es können keine Kurse geladen werden.
        </div>
    @else
        {{-- Keine Ergebnisse --}}
        @if(!$courses || $courses->count() === 0)
            <div class="rounded border border-gray-200 bg-white p-6 text-gray-500 text-sm">
                Keine Einträge gefunden.
            </div>
        @else
{{-- Tabelle --}}
<div class="overflow-x-auto rounded border border-gray-200 bg-white"
     x-data="{ openId: null }"> {{-- zentraler State --}}
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
            <tr>
                <th class="px-4 py-2 w-10"></th>
                <th class="px-4 py-2 text-left font-semibold">Baustein</th>
                <th class="px-4 py-2 text-left font-semibold">Status</th>
                <th class="px-4 py-2 text-left font-semibold">Zeitraum</th>
                <th class="px-4 py-2 w-12"></th>
            </tr>
        </thead>

        {{-- Wichtig: kein verschachteltes <tbody> innerhalb eines <tbody>.
             Stattdessen: mehrere Geschwister-<tbody>, je Kurs eines. --}}
        @foreach($courses as $course)
            <tbody x-data="{ menu:false }" class="group" wire:key="course-{{ $course->id }}">
                <tr>
                    {{-- Toggle: setzt zentralen openId --}}
                    <td class="px-2 py-2 align-top">
                        <button
                            @click="openId = (openId === {{ $course->id }}) ? null : {{ $course->id }}"
                            class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                            :aria-expanded="(openId === {{ $course->id }}) ? 'true' : 'false'"
                            title="Details ein-/ausklappen">
                            <svg x-show="openId !== {{ $course->id }}" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <svg x-show="openId === {{ $course->id }}" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </td>

                    {{-- Kurs --}}
                    <td class="px-4 py-2 align-top">
                        <div class="font-medium text-gray-900">{{ $course->title ?? '—' }}</div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            Klasse: {{ $course->enrollment?->klassen_id ?? '—' }} · Termin: {{ $course->enrollment?->termin_id ?? '—' }}
                            @if($course->enrollment?->kurzbez_ba)
                                · {{ $course->enrollment->kurzbez_ba }}
                            @endif
                        </div>
                    </td>

                    {{-- Status (Course-Accessors) --}}
                    <td class="px-4 py-2 align-top">
                        <span class="{{ $course->status_badge_classes }}">{{ $course->status_label }}</span>
                    </td>

                    {{-- Zeitraum --}}
                    <td class="px-4 py-2 align-top">
                        <div class="text-gray-800">
                            {{ optional($course->planned_start_date)->format('d.m.Y') ?? '—' }}
                            —
                            {{ optional($course->planned_end_date)->format('d.m.Y') ?? '—' }}
                        </div>
                    </td>

                    {{-- Kebab-Menü (eigener, lokaler State) --}}
                    <td class="px-2 py-2 align-top relative">
                        <div class="flex justify-end">
                            <button @click="menu = !menu" @click.outside="menu = false"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-gray-50"
                                    aria-haspopup="menu" :aria-expanded="menu.toString()" title="Aktionen">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                </svg>
                            </button>
                        </div>

                        <div x-cloak x-show="menu"
                             class="absolute right-0 z-40 mt-2 w-40 rounded border border-gray-200 bg-white shadow-lg">
                            <ul class="py-1 text-sm text-gray-700">
                                <li>
                                    <a href="{{ route('admin.courses.show', $course->id) }}"
                                       class="block w-full px-4 py-2 text-left hover:bg-gray-100">
                                        Details
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>

                {{-- Detailzeile: nur sichtbar, wenn openId === course.id --}}
                <tr>
                    <td colspan="5" class="px-4 pb-4">
                        <div x-cloak x-show="openId === {{ $course->id }}" x-collapse>
<div class="mt-2 rounded-lg border border-gray-200 bg-primary-light/50 p-4">

    {{-- ========== OBERSTE REIHE: Dozent + Bildungsmittel ========== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

        {{-- Dozent --}}
        <div class="rounded-md border border-gray-200 bg-white p-3">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Dozent
            </h4>
            @php
                $tutor = $course->tutor ?? null;
            @endphp
            @if($tutor)
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">
                            {{ trim($tutor->vorname . ' ' . $tutor->nachname) }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ $tutor->email_cbw ?? $tutor->email_priv ?? 'Keine E-Mail hinterlegt' }}
                        </div>
                        @if($tutor->telefon1)
                            <div class="text-sm text-gray-500">
                                📞 {{ $tutor->telefon1 }}
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-sm text-gray-500">Kein Dozent zugeordnet.</div>
            @endif
        </div>

        {{-- Bildungsmittel-Bestätigung --}}
        <div class="rounded-md border border-gray-200 bg-white p-3">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Bildungsmittel-Bestätigung
            </h4>
            @php
                $results = $course->enrollment?->results;
                $ack = $results['materials_ack'] ?? $results['acknowledged'] ?? null;
            @endphp
            @if(!is_null($ack))
                @if($ack)
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                        <i class="fas fa-check-circle"></i> bestätigt
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200">
                        <i class="fas fa-xmark-circle"></i> nicht bestätigt
                    </span>
                @endif
            @else
                <div class="text-sm text-gray-500">—</div>
            @endif
        </div>
    </div>

    {{-- ========== ZWEITE REIHE: Ergebnisse + Anwesenheit ========== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Ergebnisse --}}
        <div class="rounded-md border border-gray-200 bg-white p-3">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Ergebnisse
            </h4>
            @if(is_array($results) && count($results))
                <ul class="space-y-1 text-sm text-gray-700">
                    @foreach($results as $key => $val)
                        <li>
                            <span class="text-gray-500">{{ is_string($key) ? $key.': ' : '' }}</span>
                            <span class="text-gray-800">
                                @if(is_array($val) || is_object($val))
                                    {{ json_encode($val, JSON_UNESCAPED_UNICODE) }}
                                @else
                                    {{ $val === true ? 'Ja' : ($val === false ? 'Nein' : ($val ?? '—')) }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-sm text-gray-500">—</div>
            @endif
        </div>

        {{-- Anwesenheit --}}
        <div class="rounded-md border border-gray-200 bg-white p-3">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Anwesenheit
            </h4>
            @php $att = $results['attendance'] ?? null; @endphp
            @if($att)
                <div class="text-sm text-gray-800">
                    {{ is_array($att) ? json_encode($att, JSON_UNESCAPED_UNICODE) : $att }}
                </div>
            @else
                <div class="text-sm text-gray-500">—</div>
            @endif
        </div>
    </div>

    {{-- ========== UNTERSTE REIHE: Notizen ========== --}}
    @if(!empty($course->enrollment?->notes))
        <div class="mt-4 rounded-md border border-gray-200 bg-white p-3">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Notizen
            </h4>
            <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ json_encode($course->enrollment->notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>

                        </div>
                    </td>
                </tr>
            </tbody>
        @endforeach
    </table>
</div>


            {{-- Pagination --}}
            <div class="mt-3">
                {{ $courses->onEachSide(1)->links() }}
            </div>
        @endif
    @endif

                        {{-- Loading-Overlay beim Aktualisieren --}}
                    <div wire:loading.delay.class.remove="opacity-0"
                        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 opacity-0 transition-opacity">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                            <span class="loader"></span>
                            <span class="text-sm text-gray-700">wird geladen…</span>
                        </div>
                    </div>
</div>
