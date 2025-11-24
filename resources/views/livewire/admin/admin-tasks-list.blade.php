<div class="space-y-4">

    {{-- Hinweisbox --}}
    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.625 23.625" fill="currentColor" aria-hidden="true">
                    <path d="M11.812 0C5.289 0 0 5.289 0 11.812s5.289 11.813 11.812 11.813 11.813-5.29 11.813-11.813S18.335 0 11.812 0zm2.459 18.307c-.608.24-1.092.422-1.455.548a3.838 3.838 0 0 1-1.262.189c-.736 0-1.309-.18-1.717-.539s-.611-.814-.611-1.367c0-.215.015-.435.045-.659a8.23 8.23 0 0 1 .147-.759l.761-2.688c.067-.258.125-.503.171-.731.046-.23.068-.441.068-.633 0-.342-.071-.582-.212-.717-.143-.135-.412-.201-.813-.201-.196 0-.398.029-.605.09-.205.063-.383.12-.529.176l.201-.828c.498-.203.975-.377 1.43-.521a4.225 4.225 0 0 1 1.29-.218c.731 0 1.295.178 1.692.53.395.353.594.812.594 1.376 0 .117-.014.323-.041.617a4.129 4.129 0 0 1-.152.811l-.757 2.68a7.582 7.582 0 0 0-.167.736 3.892 3.892 0 0 0-.073.626c0 .356.079.599.239.728.158.129.435.194.827.194.185 0 .392-.033.626-.097.232-.064.4-.121.506-.17l-.203.827zm-.134-10.878a1.807 1.807 0 0 1-1.275.492c-.496 0-.924-.164-1.28-.492a1.57 1.57 0 0 1-.533-1.193c0-.465.18-.865.533-1.196a1.812 1.812 0 0 1 1.28-.497c.497 0 .923.165 1.275.497.353.331.53.731.53 1.196 0 .467-.177.865-.53 1.193z"/>
                </svg>
            </div>
            <div class="ml-3 text-sm">
                <h2 class="text-lg font-semibold mb-2">Hinweis zur Aufgabenverwaltung</h2>
                <p>
                    Hier findest du alle offenen, in Bearbeitung befindlichen und abgeschlossenen Aufgaben.
                    Du kannst sie übernehmen und als erledigt markieren.
                </p>
                <p class="mt-2 text-sm">
                    Offene Aufgaben: <strong>{{ $openCount }}</strong>
                </p>
            </div>
        </div>
    </div>

    {{-- Kopfzeile + Filter --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Mitarbeiter-Aufgaben</h1>
            <p class="text-gray-500 text-sm">
                Verwalte Aufgaben zu Kursen, Teilnehmern und anderen Vorgängen im Schulnetz.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">

            {{-- Status-Filter --}}
            <select wire:model="filterStatus"
                    class="border-gray-300 rounded-md text-sm">
                <option value="">Status: Alle</option>
                <option value="{{ \App\Models\AdminTask::STATUS_OPEN }}">Offen</option>
                <option value="{{ \App\Models\AdminTask::STATUS_IN_PROGRESS }}">In Bearbeitung</option>
                <option value="{{ \App\Models\AdminTask::STATUS_COMPLETED }}">Erledigt</option>
            </select>

            {{-- Priority-Filter --}}
            <select wire:model="filterPriority"
                    class="border-gray-300 rounded-md text-sm">
                <option value="">Prio: Alle</option>
                <option value="{{ \App\Models\AdminTask::PRIORITY_HIGH }}">Hoch</option>
                <option value="{{ \App\Models\AdminTask::PRIORITY_NORMAL }}">Normal</option>
                <option value="{{ \App\Models\AdminTask::PRIORITY_LOW }}">Niedrig</option>
            </select>

            {{-- Nur meine Aufgaben --}}
            <label class="inline-flex items-center gap-1">
                <input type="checkbox" wire:model="onlyMine" class="rounded border-gray-300">
                <span>Nur meine</span>
            </label>
        </div>
    </div>

    {{-- Tabellen-Header --}}
    <div class="grid grid-cols-12 bg-gray-100 p-2 font-semibold text-gray-700 border-b border-gray-300 text-xs sm:text-sm">
        <div class="col-span-1">ID</div>
        <div class="col-span-2">Typ</div>
        <div class="col-span-3">Kontext</div>
        <div class="col-span-2">Ersteller</div>
        <div class="col-span-2">Zugewiesen an</div>
        <div class="col-span-2 text-right">Status</div>
    </div>

    {{-- Aufgaben-Liste --}}
    <div class="bg-white border rounded-md divide-y">
        @forelse($tasks as $task)
            <div x-data="{ open: false }"
                 wire:key="task-{{ $task->id }}"
                 @click.away="open = false"
                 class="transition">

                {{-- Tabellenzeile --}}
                <div @click="open = !open"
                     class="grid grid-cols-12 items-center p-2 text-xs sm:text-sm cursor-pointer hover:bg-gray-50"
                     :class="{ 'bg-blue-50': open }">

                    <div class="col-span-1">
                        #{{ $task->id }}
                    </div>

                    <div class="col-span-2">
                        {{ $task->task_type }}
                    </div>

                    <div class="col-span-3">
                        @if($task->context)
                            {{-- einfache generische Anzeige je nach Kontext --}}
                            @if($task->context instanceof \App\Models\Course)
                                <span class="text-blue-600 font-medium">
                                    Kurs: {{ $task->context->title ?? 'Kurs #'.$task->context->id }}
                                </span>
                            @elseif($task->context instanceof \App\Models\User)
                                <span class="text-green-600 font-medium">
                                    User: {{ $task->context->name ?? 'User #'.$task->context->id }}
                                </span>
                            @else
                                <span class="text-gray-600">
                                    {{ class_basename($task->context) }} #{{ $task->context->id }}
                                </span>
                            @endif
                        @else
                            <span class="text-gray-400">Kein Kontext</span>
                        @endif
                    </div>

                    <div class="col-span-2">
                        {{ $task->creator?->name ?? 'Unbekannt' }}
                    </div>

                    <div class="col-span-2">
                        {{ $task->assignedAdmin?->name ?? 'Nicht zugewiesen' }}
                    </div>

                    <div class="col-span-2 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            @if($task->status === \App\Models\AdminTask::STATUS_OPEN)
                                bg-red-100 text-red-800
                            @elseif($task->status === \App\Models\AdminTask::STATUS_IN_PROGRESS)
                                bg-yellow-100 text-yellow-800
                            @else
                                bg-green-100 text-green-800
                            @endif">
                            {{ $task->status_icon }} {{ $task->status_text }}
                        </span>

                        @if($task->is_overdue)
                            <span class="ml-1 text-xs text-red-600 font-semibold">Überfällig</span>
                        @endif
                    </div>
                </div>

                {{-- Detail-Bereich --}}
                <div x-show="open" x-collapse x-cloak class="bg-blue-50 p-4 border-t text-xs sm:text-sm">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold mb-1">📝 Aufgaben-Details</h3>
                            <p><strong>Beschreibung:</strong> {{ $task->description ?: 'Keine Beschreibung angegeben.' }}</p>
                            <p><strong>Erstellt am:</strong> {{ $task->created_at->format('d.m.Y H:i') }}</p>
                            @if($task->due_at)
                                <p><strong>Fällig bis:</strong> {{ $task->due_at->format('d.m.Y H:i') }}</p>
                            @endif>
                            <p><strong>Priorität:</strong> {{ $task->priority_text }}</p>
                        </div>

                        <div class="sm:text-right space-y-1">
                            <p><strong>Ersteller:</strong> {{ $task->creator?->name ?? 'Unbekannt' }}</p>
                            <p><strong>Zugewiesen an:</strong> {{ $task->assignedAdmin?->name ?? 'Niemand' }}</p>
                            @if($task->completed_at)
                                <p><strong>Abgeschlossen am:</strong> {{ $task->completed_at->format('d.m.Y H:i') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Footer mit Buttons --}}
                    <div class="mt-4 flex justify-end gap-2 border-t pt-3">
                        @if(!$task->assigned_to || $task->assigned_to === auth()->id())
                            @if(!$task->assigned_to)
                                <button wire:click="assignToMe({{ $task->id }})"
                                        type="button"
                                        class="inline-flex items-center px-3 py-1 rounded-md border border-blue-500 text-blue-600 text-xs font-medium hover:bg-blue-50">
                                    ➕ Übernehmen
                                </button>
                            @endif

                            @if($task->status !== \App\Models\AdminTask::STATUS_COMPLETED)
                                <button wire:click="markAsCompleted({{ $task->id }})"
                                        type="button"
                                        class="inline-flex items-center px-3 py-1 rounded-md border border-green-500 text-green-600 text-xs font-medium hover:bg-green-50">
                                    ✅ Abschließen
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-4 text-sm text-gray-500">
                Es sind aktuell keine Aufgaben vorhanden.
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $tasks->links() }}
    </div>
</div>
