<x-dialog-modal wire:model="showDetailModal">

    {{-- TITLE --}}
    <x-slot name="title">
        @if($task)
            <div class="flex items-center gap-2">
                <i class="fal fa-tasks text-slate-500 text-lg"></i>
                <span>Aufgabe #{{ $task->id }}</span>
            </div>
        @else
            Aufgabe
        @endif
    </x-slot>


    {{-- CONTENT --}}
    <x-slot name="content">
        @if(!$task)
            <div class="py-6 text-center text-slate-500">
                Keine Aufgabe geladen.
            </div>
        @else

            <div class="space-y-6">

                {{-- Beschreibung --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Beschreibung</h3>
                    <p class="mt-1 text-slate-700 text-sm">
                        {{ $task->description ?: 'Keine Beschreibung angegeben.' }}
                    </p>
                </div>

                {{-- Kontext --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontext</h3>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ $task->context_text ?? 'Kein Kontext' }}
                    </p>
                </div>

                {{-- Zeit & Metadaten --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    <div class="space-y-1">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Zeiten</h3>

                        <p class="text-sm">
                            <strong>Erstellt:</strong>
                            {{ $task->created_at->format('d.m.Y H:i') }}
                        </p>

                        @if($task->due_at)
                            <p class="text-sm">
                                <strong>Fällig:</strong>
                                {{ $task->due_at->format('d.m.Y H:i') }}
                            </p>

                            @if($task->is_overdue)
                                <p class="text-xs text-red-600 font-semibold">
                                    Überfällig
                                </p>
                            @endif
                        @endif
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personen</h3>

                        <p class="text-sm">
                            <strong>Ersteller:</strong>
                            {{ $task->creator?->name ?? 'Unbekannt' }}
                        </p>

                        <p class="text-sm">
                            <strong>Zugewiesen an:</strong>
                            {{ $task->assignedAdmin?->name ?? 'Niemand' }}
                        </p>

                        @if($task->completed_at)
                            <p class="text-xs text-emerald-600 mt-1">
                                Abgeschlossen am {{ $task->completed_at->format('d.m.Y H:i') }}
                            </p>
                        @endif
                    </div>

                </div>

            </div>

            <div>
                {{-- Task Type --}}
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Typ</h3>
                <p class="mt-1 text-slate-700 text-sm">
                    {{ $task->task_type_text ?? 'Unbekannt' }}
                </p>
            </div>

        @endif
    </x-slot>


    {{-- FOOTER --}}
    <x-slot name="footer">
        @if($task)
            <div class="flex justify-between w-full items-center">

                {{-- Status Badge --}}
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                        @if($task->status === \App\Models\AdminTask::STATUS_OPEN)
                            bg-red-50 text-red-700
                        @elseif($task->status === \App\Models\AdminTask::STATUS_IN_PROGRESS)
                            bg-amber-50 text-amber-700
                        @else
                            bg-emerald-50 text-emerald-700
                        @endif
                    ">
                        <span class="mr-1">{{ $task->status_icon }}</span>
                        {{ $task->status_text }}
                    </span>
                </div>

                <div class="flex gap-2">

                    {{-- Übernehmen --}}
                    @if(!$task->assigned_to)
                        <x-secondary-button
                            wire:click="$dispatch('assignToMe', {{ $task->id }})"
                            class="flex items-center gap-1"
                        >
                            <i class="fal fa-user-plus text-sm"></i>
                            Übernehmen
                        </x-secondary-button>
                    @endif

                    {{-- Abschließen --}}
                    @if($task->status !== \App\Models\AdminTask::STATUS_COMPLETED)
                        <x-secondary-button
                            wire:click="$dispatch('markAsCompleted', {{ $task->id }})"
                            class="flex items-center gap-1 text-emerald-700 border-emerald-500 hover:bg-emerald-50"
                        >
                            <i class="fal fa-check-circle text-sm"></i>
                            Abschließen
                        </x-secondary-button>
                    @endif

                    {{-- Close --}}
                    <x-secondary-button wire:click="close">
                        Schließen
                    </x-secondary-button>

                </div>
            </div>
        @else
            <x-secondary-button wire:click="close">
                Schließen
            </x-secondary-button>
        @endif
    </x-slot>

</x-dialog-modal>
