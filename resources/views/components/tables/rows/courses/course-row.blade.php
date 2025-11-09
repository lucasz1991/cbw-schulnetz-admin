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



{{-- 2: Zeitraum (planned_start_date / planned_end_date) --}}
<div class="px-2 py-2 text-xs text-gray-600 {{ $hc(1) }}">
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

{{-- 3: Status (nur Icons mit Tooltip) --}}
<div class="px-2 py-2 flex items-center gap-2 {{ $hc(2) }}">
    @switch($status)
        @case('scheduled')
            {{-- Geplant --}}
            <i
                class="fad fa-calendar text-sky-600 text-xl"
                title="Geplant"
            ></i>
            @break

        @case('active')
            {{-- Aktiv (läuft) --}}
            <i
                class="fad fa-play-circle text-green-600 text-xl"
                title="Aktiv (läuft)"
            ></i>
            @break

        @case('completed')
            {{-- Abgeschlossen --}}
            <i
                class="fad fa-check-circle text-gray-400 text-xl"
                title="Abgeschlossen"
            ></i>
            @break

        @default
            {{-- unbekannt --}}
            <i
                class="fad fa-question-circle text-gray-400 text-lg"
                title="Unbekannt"
            ></i>
    @endswitch
</div>


{{-- 1: Tutor (aus Person) --}}
<div class="px-2 py-2 text-gray-700 truncate {{ $hc(3) }}">
    @if($item->tutor !== null)
        <span class="inline-flex items-center gap-1">
            <x-user.public-info :person="$item->tutor" />
        </span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</div>

{{-- 4: Aktivitäten (Teilnehmer & Termine) --}}
<div class="px-2 py-1 text-xs {{ $hc(4) }}">
    <div class="flex  gap-2 items-center  pr-4">
        <div class=" relative h-max inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-50 text-blue-700 border border-blue-400 mr-2" title="Teilnehmer">
            <i class="fal fa-users fa-lg"></i>
            <span
                    class="absolute -top-3 -right-3 flex items-center justify-center
                        min-w-4 h-4 text-[11px] font-semibold bg-white text-blue-700 border border-blue-400 p-[3px]
                        rounded-full shadow-sm"
                >
                    {{ $item->participants_count ?? 0 }}
                </span> 
        </div>


        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Dokumentation vollständig">
            <i class="fal fa-chalkboard-teacher fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-white rounded-full aspect-square ">
                <i class="fad fa-check-circle text-green-600  fa-lg"></i>
            </div>
        </div>

        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Roten Faden hochgeladen">
            <i class="fal fa-file-pdf fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-white rounded-full aspect-square ">
                <i class="fad fa-times-circle text-red-600  fa-lg"></i>
            </div>
        </div>

        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Teilnahmebestätigungen ausstehend">
            <i class="fal fa-file-signature fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-yellow-100 rounded-full aspect-square p-[3px]">
                <i class="fad fa-spinner text-yellow-600 "></i>
            </div>
        </div>


    </div>
</div>

