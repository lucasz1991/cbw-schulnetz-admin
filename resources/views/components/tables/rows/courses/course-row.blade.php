@php
    // Kurzhelfer pro Spaltenindex
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    // Felder aus dem neuen Course-Modell
    $title     = $item->title ?? '—';
    $vtz     = $item->vtz ?? null;         // falls du ein Kurzlabel führst
    $klassenId = $item->klassen_id ?? null;
    $room      = $item->room ?? null;


    // Zeitraum (casts: 'date')
    $start = $item->planned_start_date ?? null;
    $end   = $item->planned_end_date   ?? null;

    $startLbl = $start?->locale('de')->isoFormat('ll');
    $endLbl   = $end?->locale('de')->isoFormat('ll');
    $termin_id = $item->termin_id ?? null;

    // Status aus Zeitraum ableiten
    $now = now();
    $status = 'unknown';
    if ($start && $end) {
        $status = $now->lt($start) ? 'scheduled' : ($now->between($start, $end) ? 'active' : 'completed');
    } elseif ($start && !$end) {
        $status = $now->lt($start) ? 'scheduled' : 'active';
    }



@endphp

{{-- 0: Titel --}}
<div class="px-2 py-2  pr-4 {{ $hc(0) }} cursor-pointer" wire:click="$dispatch('toggleCourseSelection', [{{ $item->id }}])">
<div class="grid grid-cols-[auto_1fr] gap-2 items-center">
    <div class="flex items-center">
        <div 
            class="w-4 h-4 rounded-full border cursor-pointer transition {{ $isSelected ? 'ring-4 ring-green-300 bg-green-100 border-green-600' : 'border-gray-400' }}"
        >
        </div>
    </div>

    <!-- WICHTIG: min-w-0 auf diese Spalte -->
    <div class="flex flex-col min-w-0">
        <!-- truncate funktioniert jetzt -->
        <div class="px-1 font-semibold truncate">
            {{ $title }}
        </div>
    </div>
</div>





</div>

{{-- 1: Tutor (aus Person) --}}
<div class="px-2 py-2 text-gray-700 truncate {{ $hc(1) }}">
    @if($item->tutor !== null)
        <span class="inline-flex items-center gap-1">
            <x-user.public-info :person="$item->tutor" />
        </span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</div>

{{-- 2: Zeitraum (planned_start_date / planned_end_date) --}}
<div class="px-2 py-2 text-xs text-gray-600 {{ $hc(2) }}">
    <div class="mb-1">
        <span class="px-2 py-0.5 text-[10px] leading-5 font-semibold rounded bg-slate-50 text-slate-700 border border-slate-200">
            {{ $termin_id }}
        </span>
    </div>
    @if($startLbl || $endLbl)
        <span class="pl-1 text-green-700">{{ $startLbl ?? '—' }}</span>
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

</div>

{{-- 4: Aktivitäten (Teilnehmer & Termine) --}}
<div class="px-2 py-2 text-xs {{ $hc(4) }}">
    <div class="flex flex-wrap gap-2 items-center">
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 14a4 4 0 10-8 0v3h8v-3zM12 7a3 3 0 110-6 3 3 0 010 6z"/>
            </svg>
            {{ $item->participants_count ?? 0 }}
        </span>


    </div>
</div>

