<div class="relative px-2" wire:init="load">


    {{-- Inhalt: wird erst sichtbar, wenn $ready = true --}}
    <div>
        <div class="flex justify-between mb-4">
            <x-slot name="header">
                <x-slot name="title">Kursliste</x-slot>
            </x-slot>

            <div class="flex items-center space-x-2">
                <h1 class="flex items-center text-lg font-semibold px-2 py-1">
                    <span>Kurse</span>
                    <span class="ml-2 bg-white text-sky-600 text-xs shadow border border-sky-200 font-bold px-2 py-1 flex items-center justify-center rounded-full h-7 leading-none">
                        {{ $coursesTotal }}
                    </span>
                </h1>

                <x-tables.search-field 
                    resultsCount="{{ $courses->count() }}"
                    wire:model.live.debounce.500ms="search"
                />
            </div>

            <x-link-button @click="$dispatch('open-course-create-edit')" class="btn-xs py-0 leading-[0]">+</x-link-button>
        </div>

        <div class="w-full relative max-h-[70vh] overflow-y-auto overflow-x-hidden  scroll-container"
            @class([
                'opacity-50 pointer-events-none' => false, // Standardzustand
                'hidden' => !($ready ?? false),           // verstecken, bis initial geladen
            ])
            wire:loading.class="opacity-50 pointer-events-none"
        >
            <x-tables.table
                :columns="[
                    ['label'=>'Titel',        'key'=>'title',               'width'=>'32%','sortable'=>true,  'hideOn'=>'none'],
                    ['label'=>'Kennung',      'key'=>'short',               'width'=>'14%','sortable'=>true,  'hideOn'=>'md'],
                    ['label'=>'Zeitraum',     'key'=>'start_time',          'width'=>'22%','sortable'=>true,  'hideOn'=>'xl'],
                    ['label'=>'Status',       'key'=>'status',              'width'=>'14%','sortable'=>false, 'hideOn'=>'md'],
                    ['label'=>'Aktivitäten',  'key'=>'participants_count',  'width'=>'18%','sortable'=>true,  'hideOn'=>'md'],
                ]"
                :items="$courses"
                row-view="components.tables.rows.courses.course-row"
                actions-view="components.tables.rows.courses.course-actions"
                :sort-by="$sortBy ?? null"
                :sort-dir="$sortDir ?? 'asc'"
            />
            {{-- Overlay-Spinner für alle Requests (inkl. init) --}}
            <div
                wire:loading.flex
                class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm items-center justify-center rounded"
                aria-live="polite"
                aria-busy="true"
                role="status"
            >
                <div class="animate-spin h-6 w-6 border-2 border-current border-t-transparent rounded-full"></div>
                <span class="sr-only">Laden …</span>
            </div>
        </div>

        @livewire('admin.courses.course-create-edit')
    </div>

</div>
