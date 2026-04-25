<div class="">
    <div class="space-y-8">


        {{-- Kopfzeile + Filter --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Job's</h1>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-sm">
                {{-- Suchfeld --}}
                <x-tables.search-field
                    resultsCount="{{ $tasks->count() }}"
                    wire:model.live="search"
                />
                {{-- Status-Filter --}}
                <x-ui.forms.lz-select
                    model="filterStatus"
                    :selected="$filterStatus"
                    :options="[
                        ['value' => '', 'label' => 'Status: Alle'],
                        ['value' => \App\Models\AdminTask::STATUS_OPEN, 'label' => 'Offen'],
                        ['value' => \App\Models\AdminTask::STATUS_IN_PROGRESS, 'label' => 'In Bearbeitung'],
                        ['value' => \App\Models\AdminTask::STATUS_COMPLETED, 'label' => 'Erledigt'],
                    ]"
                    width="56"
                />

                {{-- Priority-Filter --}}
                <x-ui.forms.lz-select
                    model="filterPriority"
                    :selected="$filterPriority"
                    :options="[
                        ['value' => '', 'label' => 'Prio: Alle'],
                        ['value' => \App\Models\AdminTask::PRIORITY_HIGH, 'label' => 'Hoch'],
                        ['value' => \App\Models\AdminTask::PRIORITY_NORMAL, 'label' => 'Normal'],
                        ['value' => \App\Models\AdminTask::PRIORITY_LOW, 'label' => 'Niedrig'],
                    ]"
                    width="56"
                />

                <x-ui.forms.lz-select
                    model="filterInstitution"
                    :selected="$filterInstitution"
                    :options="array_merge(
                        [['value' => '', 'label' => 'Institut: Alle']],
                        $institutionOptions
                    )"
                    width="64"
                />

                {{-- Nur meine Aufgaben --}}
                <label class="inline-flex items-center gap-1">
                    <x-ui.forms.toggle-button
                        model="onlyMine"
                        label="Nur meine"
                    />
                </label>
            </div>
        </div>

        {{-- Aufgaben-Tabelle --}}
        <x-tables.table
            :columns="[
                ['label' => 'ID', 'key' => 'id', 'width' => '5%', 'sortable' => false, 'hideOn' => 'md'],
                ['label' => 'Art', 'key' => 'task_type_text', 'width' => '40%', 'sortable' => false, 'hideOn' => 'none'],
                ['label' => 'Ersteller', 'key' => 'creator_name', 'width' => '40%', 'sortable' => false, 'hideOn' => 'lg'],
                ['label' => 'Status', 'key' => 'status', 'width' => '15%', 'sortable' => false, 'hideOn' => 'none'],
            ]"
            :items="$tasks"
            :sort-by="$sortBy ?? null"
            :sort-dir="$sortDir ?? 'asc'"
            row-view="components.tables.rows.admin-tasks.task-row"
            action-view=""
        />

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    </div>
    <livewire:admin.tasks.admin-task-detail wire:key="admin-task-detail-global" />
</div>
