{{-- resources/views/components/tables/rows/admin-tasks/task-row.blade.php --}}
@props(['item'])

@php
    /** @var \App\Models\AdminTask $task */
    $task = $item;

    // Kurzhelfer pro Spaltenindex (aus deinem Table-Setup)
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    $id          = $task->id;
    $typeText    = $task->task_type_text ?? '—';
    $description = $task->description ?: 'Keine Beschreibung angegeben.';
    $contextText = $task->context_text ?? null;
    $contextDesc = $task->context_description ?? null;

    $creatorName   = $task->creator?->name ?? 'Unbekannt';
    $assignedName  = $task->assignedAdmin?->name ?? 'Niemand';
    $createdAtLbl  = $task->created_at?->format('d.m.Y H:i');
    $dueAtLbl      = $task->due_at?->format('d.m.Y H:i');
@endphp

{{-- 0: ID --}}
<div
    class="px-2 py-2 {{ $hc(0) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <div class="flex items-center gap-2 text-xs text-slate-600">
        <span class="h-2.5 w-2.5 rounded-full
            @if($task->status === \App\Models\AdminTask::STATUS_OPEN)
                bg-red-400
            @elseif($task->status === \App\Models\AdminTask::STATUS_IN_PROGRESS)
                bg-amber-400
            @else
                bg-emerald-400
            @endif
        "></span>

        <span class="font-mono text-[11px] text-slate-500">
            #{{ $id }}
        </span>
    </div>
</div>

{{-- 1: Typ + Kurzbeschreibung --}}
<div
    class="px-2 py-2 pr-4 {{ $hc(1) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <div class="flex flex-col min-w-0 space-y-1" title="{{ $typeText }}">
        <div class="flex items-center gap-2 min-w-0">
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-[13px] text-slate-800 truncate">
                    {{ $typeText }}
                </div>
            </div>

            @if($task->priority_text === 'Hoch')
                <span class="inline-flex items-center rounded-full bg-red-50 text-red-700 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                    <i class="fas fa-bolt mr-1 text-[9px]"></i> Prio hoch
                </span>
            @elseif($task->priority_text === 'Niedrig')
                <span class="inline-flex items-center rounded-full bg-slate-50 text-slate-500 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide">
                    <i class="fal fa-feather mr-1 text-[9px]"></i> Niedrig
                </span>
            @endif
        </div>

        <p class="text-[11px] text-slate-500 truncate">
            {{ \Illuminate\Support\Str::limit($description, 110) }}
        </p>

        <div class="mt-1 flex flex-wrap gap-2 text-[10px] text-slate-400 md:hidden">
            <span class="inline-flex items-center gap-1">
                <i class="fal fa-user text-[10px]"></i>
                {{ $creatorName }}
            </span>
            <span class="inline-flex items-center gap-1">
                <i class="fal fa-user-cog text-[10px]"></i>
                {{ $assignedName }}
            </span>
            @if($task->due_at)
                <span class="inline-flex items-center gap-1">
                    <i class="fal fa-clock text-[10px]"></i>
                    {{ $task->due_at->format('d.m. H:i') }}
                </span>
            @endif
        </div>
    </div>
</div>

{{-- 2: Kontext --}}
<div
    class="px-2 py-2 {{ $hc(2) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    @if($task->context)
        <div class="flex flex-col gap-0.5 min-w-0">
            <span class="text-[12px] font-medium text-slate-700 truncate">
                {{ $contextText }}
            </span>
            @if($contextDesc)
                <span class="text-[11px] text-slate-400 truncate">
                    {{ $contextDesc }}
                </span>
            @endif
        </div>
    @else
        <span class="text-[11px] text-slate-400 italic">Kein Kontext</span>
    @endif
</div>

{{-- 3: Ersteller --}}
<div
    class="px-2 py-2 {{ $hc(3) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <div class="flex flex-col gap-0.5">
        <span class="text-[12px] text-slate-700 truncate">
            {{ $creatorName }}
        </span>
        @if($createdAtLbl)
            <span class="text-[11px] text-slate-400">
                {{ $createdAtLbl }}
            </span>
        @endif
    </div>
</div>

{{-- 4: Zugewiesen --}}
<div
    class="px-2 py-2 {{ $hc(4) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <div class="flex flex-col gap-1">
        <span class="text-[12px] text-slate-700 truncate">
            {{ $assignedName }}
        </span>

        @if(!$task->assigned_to)
            <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] text-slate-500">
                <i class="fal fa-user-plus mr-1 text-[9px]"></i> Frei verfügbar
            </span>
        @elseif($task->assigned_to === auth()->id())
            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-600">
                <i class="fal fa-user-check mr-1 text-[9px]"></i> Dir zugewiesen
            </span>
        @endif
    </div>
</div>

{{-- 5: Fällig bis --}}
<div
    class="px-2 py-2 {{ $hc(5) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <div class="flex flex-col items-start gap-0.5">
        @if($task->due_at)
            <span class="text-[12px] text-slate-700">
                {{ $dueAtLbl }}
            </span>
            @if($task->is_overdue)
                <span class="inline-flex items-center rounded-full bg-red-50 text-red-700 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="fal fa-exclamation-circle mr-1 text-[10px]"></i>
                    Überfällig
                </span>
            @endif
        @else
            <span class="text-[11px] text-slate-400">Kein Fälligkeitsdatum</span>
        @endif
    </div>
</div>

{{-- 6: Status --}}
<div
    class="px-2 py-2 flex items-center justify-end gap-2 {{ $hc(6) }} cursor-pointer"
    wire:click="$dispatch('openAdminTaskDetail',[ { taskId: {{ $task->id }}  }])"
>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
        @if($task->status === \App\Models\AdminTask::STATUS_OPEN)
            bg-red-50 text-red-700
        @elseif($task->status === \App\Models\AdminTask::STATUS_IN_PROGRESS)
            bg-amber-50 text-amber-700
        @else
            bg-emerald-50 text-emerald-700
        @endif
    ">
        <span class="mr-1 text-xs">{{ $task->status_icon }}</span>
        {{ $task->status_text }}
    </span>

</div>
