@php
    // Kurzhelfer pro Spaltenindex
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    // Titel & Badges
    $title   = $item->title ?? '—';
    $kurzbez = $item->short ?? null;
    $courseId = $item->id ?? null;

    // Zeitraum (Carbon aus DTO)
    $start = $item->start_time ?? null;
    $end   = $item->end_time   ?? null;

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

    // Counts
    $participantsCount = (int) ($item->participants_count ?? 0);
    $teachersCount     = (int) ($item->teachers_count ?? 0);
@endphp

{{-- 0: Titel --}}
<div class="px-2 py-2 flex items-center gap-2 {{ $hc(0) }}">
    <div class="font-semibold truncate">{{ $title }}</div>

    @if($kurzbez)
        <span class="px-2 py-0.5 text-[10px] leading-5 font-semibold rounded bg-blue-50 text-blue-700 border border-blue-100">
            {{ $kurzbez }}
        </span>
    @endif

</div>

{{-- 1: Tutor (derzeit nicht von der API geliefert) --}}
<div class="px-2 py-0 text-gray-700 truncate {{ $hc(1) }}">
    @if($courseId)
        <span class="px-2 py-0.5 text-[10px] leading-5 font-semibold rounded bg-slate-50 text-slate-700 border border-slate-200">
            #{{ $courseId }}
        </span>
    @endif
</div>

{{-- 2: Zeitraum --}}
<div class="px-2 py-2 text-xs text-gray-600 {{ $hc(2) }}">
    @if($startLbl || $endLbl)
        <span class="text-green-700">{{ $startLbl ?? '—' }}</span>
        <span>–</span>
        <span class="text-red-700">{{ $endLbl ?? '—' }}</span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</div>

{{-- 3: Status --}}
<div class="px-2 py-2 {{ $hc(3) }}">
    @switch($status)
        @case('scheduled')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-sky-50 text-sky-700">Geplant</span>
            @break
        @case('active')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-green-50 text-green-700">Aktiv</span>
            @break
        @case('completed')
            <span class="px-2 py-1 text-xs font-semibold rounded bg-emerald-50 text-emerald-700">Abgeschlossen</span>
            @break
        @default
            <span class="text-xs text-gray-400">—</span>
    @endswitch
</div>

{{-- 4: Aktivitäten --}}
<div class="px-2 py-2 text-xs {{ $hc(4) }}">
    <div class="flex flex-wrap gap-2 items-center">
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 14a4 4 0 10-8 0v3h8v-3zM12 7a3 3 0 110-6 3 3 0 010 6z"/></svg>
            {{ $participantsCount }} TN
        </span>

        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-purple-50 text-purple-700 border border-purple-100">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5v14"/></svg>
            {{ $teachersCount }} Doz.
        </span>
    </div>
</div>
