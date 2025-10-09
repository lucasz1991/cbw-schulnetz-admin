@php
    // Kurzhelfer pro Spaltenindex
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    // Felder aus dem neuen Course-Modell
    $title     = $item->title ?? '—';
    $vtz     = $item->vtz ?? null;         // falls du ein Kurzlabel führst
    $klassenId = $item->klassen_id ?? null;
    $room      = $item->room ?? null;

    // Tutor (Person)
    $tutorVor  = $item->tutor->vorname ?? null;
    $tutorNach = $item->tutor->nachname ?? null;
    $tutorName = trim(($tutorNach ? $tutorNach.', ' : '').($tutorVor ?? ''));

    // Zeitraum (casts: 'date')
    $start = $item->planned_start_date ?? null;
    $end   = $item->planned_end_date   ?? null;

    $startLbl = $start?->locale('de')->isoFormat('ll');
    $endLbl   = $end?->locale('de')->isoFormat('ll');

    // Status aus Zeitraum ableiten
    $now = now();
    $status = 'unknown';
    if ($start && $end) {
        $status = $now->lt($start) ? 'scheduled' : ($now->between($start, $end) ? 'active' : 'completed');
    } elseif ($start && !$end) {
        $status = $now->lt($start) ? 'scheduled' : 'active';
    }

    // Counts (aus Accessors/withCounts)
    $participantsCount = (int) ($item->participants_count ?? 0);
    $datesCount        = (int) ($item->dates_count ?? 0);

    // Aktiv-Flag (optional Badge im Status)
    $isActive = (bool) ($item->is_active ?? false);
@endphp

{{-- 0: Titel --}}
<div class="px-2 py-2 flex items-center justify-between gap-2 pr-4 {{ $hc(0) }}">
    <div class="font-semibold truncate">{{ $title }}</div>



    @if($klassenId)
        <span class="px-2 py-0.5 text-[10px] leading-5 font-semibold rounded bg-slate-50 text-slate-700 border border-slate-200">
            {{ $klassenId }}
        </span>
    @endif

</div>

{{-- 1: Tutor (aus Person) --}}
<div class="px-2 py-2 text-gray-700 truncate {{ $hc(1) }}">
    @if($tutorName !== '')
        <span class="inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 14a4 4 0 10-8 0v3h8v-3zM12 7a3 3 0 110-6 3 3 0 010 6z"/>
            </svg>
            {{ $tutorName }}
        </span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</div>

{{-- 2: Zeitraum (planned_start_date / planned_end_date) --}}
<div class="px-2 py-2 text-xs text-gray-600 {{ $hc(2) }}">
    @if($startLbl || $endLbl)
        <span class="text-green-700">{{ $startLbl ?? '—' }}</span>
        <span>–</span>
        <span class="text-red-700">{{ $endLbl ?? '—' }}</span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</div>

{{-- 3: Status (inkl. Aktiv-Badge) --}}
<div class="px-2 py-2 flex items-center gap-2 {{ $hc(3) }}">
    @switch($status)
        @case('scheduled')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-sky-50 text-sky-700">Geplant</span>
            @break
        @case('active')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-green-50 text-green-700">Aktiv (läuft)</span>
            @break
        @case('completed')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-emerald-50 text-emerald-700">Abgeschlossen</span>
            @break
        @default
            <span class="text-xs text-gray-400">—</span>
    @endswitch

    @if($isActive)
        <span class="px-2 py-1 text-[10px] font-semibold rounded bg-lime-50 text-lime-700 border border-lime-100">aktiv</span>
    @else
        <span class="px-2 py-1 text-[10px] font-semibold rounded bg-gray-50 text-gray-600 border border-gray-200">inaktiv</span>
    @endif
</div>

{{-- 4: Aktivitäten (Teilnehmer & Termine) --}}
<div class="px-2 py-2 text-xs {{ $hc(4) }}">
    <div class="flex flex-wrap gap-2 items-center">
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 14a4 4 0 10-8 0v3h8v-3zM12 7a3 3 0 110-6 3 3 0 010 6z"/>
            </svg>
            {{ $participantsCount }} TN
        </span>

        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-purple-50 text-purple-700 border border-purple-100">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M6 11h12M10 15h8"/>
            </svg>
            {{ $datesCount }} Termine
        </span>
    </div>
</div>
