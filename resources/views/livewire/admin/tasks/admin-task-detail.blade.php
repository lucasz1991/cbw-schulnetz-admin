<x-modal.modal wire:model="showDetailModal" :maxWidth="'4xl'">

    {{-- TITLE --}}
    <x-slot name="title">
        @if($task)
            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-3">

                    {{-- Linke Seite --}}
                    <div class="flex items-center gap-2">
                        <i class="fal fa-tasks text-slate-500 text-lg"></i>
                        <span class="font-semibold">Aufgabe #{{ $task->id }}</span>
                    </div>

                    {{-- Rechte Seite: Zuweisung --}}
                    <div class="flex items-center gap-2 text-xs">
                        @if($task->assignedAdmin)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                <i class="fal fa-user-check text-[11px]"></i>
                                <span>{{ $task->assignedAdmin->name }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                <i class="fal fa-user-clock text-[11px]"></i>
                                <span>Nicht zugewiesen</span>
                            </span>
                        @endif
                    </div>
                </div>
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

            {{-- VIEW: Aufgabenübersicht --}}
            @if($viewMode === 'task')
                <div class="space-y-6">
                    {{-- Zeiten & Verursacher --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        {{-- Verursacher --}}
                        <div class="space-y-1">
                            <h3 class="text-xs  text-slate-500">Verursacher</h3>
                            <x-user.public-info :person="$task->creator->person" />
                            <p class="mt-1 text-sm text-slate-700 w-max">
                            </p>
                        </div>
                        {{-- Zeiten --}}
                        <div class="space-y-1 ">
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
                                    <p class="text-xs text-red-600 font-semibold">Überfällig</p>
                                @endif
                            @endif
                        </div>



                    </div>
                    {{-- Kontextkurzinfo --}}
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontext</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            <span class="inline-flex items-center mt-2 gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                {{ $task->context_text ?? 'Kein Kontext' }}
                            </span>
                            @if(class_basename($task->context_type) == 'ReportBook' )
                                <span class="inline-flex items-center mt-2 gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                                    {{ $task->context->course->planned_start_date ? Carbon\Carbon::parse($task->context->course->planned_start_date)->format('d.m.Y') : '-' }} - {{ $task->context->course->planned_end_date ? Carbon\Carbon::parse($task->context->course->planned_end_date)->format('d.m.Y') : '-' }}
                                </span>
                            @endif
                        </p>
                    </div>
                    {{-- Beschreibung --}}
                    <div class="pb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Beschreibung
                        </h3>
                        <p class="mt-1 text-slate-700 text-sm">
                            {{ $task->description ?: 'Keine Beschreibung angegeben.' }}
                        </p>
                    </div>




                </div>
            @endif

            {{-- VIEW: Kontext --}}
            @if($viewMode === 'context' && $task->context)

                <div class="space-y-4">
                    @if($task->task_type === 'reportbook_review')
                        @include('livewire.admin.tasks.partials._reportbook-context', ['task' => $task])

                    @elseif($task->task_type === 'user_request_review')
                        @include('livewire.admin.tasks.partials._user-request-context', ['task' => $task])

                    @else
                        <p class="text-sm text-slate-500">
                            Für diesen Aufgabentyp ist noch keine Detailansicht hinterlegt.
                        </p>
                    @endif
                </div>

            @endif

        @endif
    </x-slot>

{{-- FOOTER --}}
<x-slot name="footer">

    @if($task)
        @php $currentUserId = auth()->id(); @endphp

        <div class="flex justify-between w-full items-center">

            {{-- Linke Button-Gruppe --}}
            <div class="flex gap-2">

                {{-- Kontext einsehen / zurück --}}
                @if($task->context && (int) $task->assigned_to === (int) $currentUserId || Auth::user()->role == "admin")
                    <x-ui.buttons.button-basic
                        :mode="'blue'"
                        :size="'sm'"
                        wire:click="switchTo{{ $viewMode === 'task' ? 'Context' : 'Task' }}"
                        class="flex items-center gap-1"
                    >
                        <i class="fal fa-eye text-sm"></i>
                        @if($viewMode === 'task')
                            Einsehen
                        @else
                            Zurück zur Aufgabe
                        @endif
                    </x-ui.buttons.button-basic>
                @endif

                {{-- Übernehmen --}}
                @if(is_null($task->assigned_to))
                    <x-ui.buttons.button-basic
                        :mode="'primary'"
                        :size="'sm'"
                        wire:click="assignToMe"
                        class="flex items-center gap-1"
                    >
                        <i class="fal fa-user-plus text-sm"></i>
                        Übernehmen
                    </x-ui.buttons.button-basic>
                @endif

                {{-- Zurückgeben (wieder freigeben) --}}
                @if(
                    !is_null($task->assigned_to) &&
                    (int) $task->status === (int) \App\Models\AdminTask::STATUS_IN_PROGRESS &&
                    (int) $task->status !== (int) \App\Models\AdminTask::STATUS_COMPLETED &&
                    (
                        (int) $task->assigned_to === (int) $currentUserId ||
                        Auth::user()->role == "admin"
                    )
                )
                    <x-ui.buttons.button-basic
                        :mode="'secondary'"
                        :size="'sm'"
                        wire:click="releaseTask"
                        class="flex items-center gap-1"
                    >
                        <i class="fal fa-user-minus text-sm"></i>
                        Zurückgeben
                    </x-ui.buttons.button-basic>
                @endif

                
            </div>
            
            {{-- Rechte Button-Gruppe --}}
            <div class="flex gap-2">
                {{-- Abschließen --}}
                @if(
                    $task->status !== \App\Models\AdminTask::STATUS_COMPLETED &&
                    (int) $task->assigned_to === (int) $currentUserId &&
                    $viewMode === 'task'
                )
                    <x-ui.buttons.button-basic
                        :mode="'success'"
                         :size="'sm'"
                        wire:click="markAsCompleted"
                        class="flex items-center gap-1"
                    >
                        <i class="fal fa-check-circle text-sm"></i>
                        Abschließen
                    </x-ui.buttons.button-basic>
                @endif
                <x-ui.buttons.button-basic
                   :mode="'white'"
                     :size="'sm'"
                    wire:click="close"
                    class="flex items-center gap-1"
                >
                    <i class="fal fa-xmark-circle text-sm"></i>
                    Schließen
                </x-ui.buttons.button-basic>
            </div>

        </div>

    @else

        {{-- Fallback --}}
        <x-ui.buttons.button-basic :mode="'secondary'"  :size="'sm'" wire:click="close">
            Schließen
        </x-ui.buttons.button-basic>

    @endif

</x-slot>


</x-modal.modal>
