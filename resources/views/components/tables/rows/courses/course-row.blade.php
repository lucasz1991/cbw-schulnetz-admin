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
<div class="flex justify-center px-2 py-2 text-xs {{ $hc(1) }}">
    <div class="inline-flex flex-col items-center justify-center px-2 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
        <div class="font-semibold text-[10px] leading-tight">
            {{ $termin_id }}
        </div>

        <div class="w-6 border-t border-slate-200 my-0.5"></div>

        @if($startLbl || $endLbl)
            <div class="font-medium text-[10px] leading-tight">
                {{ $startLbl ?? '—' }} – {{ $endLbl ?? '—' }}
            </div>
        @else
            <div class="text-gray-400 text-[10px]">—</div>
        @endif
    </div>
</div>


{{-- 3: Status (nur Icons mit Tooltip) bg-yellow-100  text-yellow-600 text-blue-400 bg-blue-100 --}}
<div class="px-2 py-2 flex items-center gap-2 {{ $hc(2) }}">
    <i class="{{ $item->status_icon }}" title="{{ $item->status_icon_title }}"></i>
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
                        min-w-4 h-4 text-[10px] font-semibold bg-white text-blue-700 border border-blue-400 p-[2px]
                        rounded-full shadow-sm"
                >
                    {{ $item->participants_count ?? 0 }}
                </span> 
        </div>


        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Dokumentation vollständig">
            <i class="fal fa-chalkboard-teacher fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-white/50 rounded-full aspect-square  p-[2px]">
                {!! $item->documentation_icon_html !!}
            </div>
        </div>

        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Roten Faden hochgeladen">
            <i class="fal fa-file-pdf fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-white/50 rounded-full aspect-square  p-[2px]">
                {!! $item->red_thread_icon_html !!}
            </div>
        </div>

        <div class=" relative inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-50 text-gray-700 border border-gray-400 mr-2" title="Teilnahmebestätigungen ausstehend">
            <i class="fal fa-file-signature fa-lg"></i>
            <div class="absolute -top-2 -right-2 bg-white/50 rounded-full aspect-square p-[2px]">
                {!! $item->participants_confirmations_icon_html !!}
            </div>
        </div>


    </div>
</div>

