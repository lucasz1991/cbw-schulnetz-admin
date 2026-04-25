<div class="px-2" wire:loading.class="pointer-events-none cursor-wait">
    <div class="flex items-center ">
        <h1 class="text-2xl font-bold text-gray-700">{{ __('base.blocks') }}</h1>
        <span class="ml-2 bg-white text-sky-600 text-xs shadow border border-sky-200 font-bold px-2 py-1 flex items-center justify-center rounded-full h-7 leading-none">
            {{ $coursesTotal }}
        </span>
    </div>
    <div class="flex justify-between my-4 space-x-2">
        <div class="flex items-center gap-4">
            <x-ui.buttons.button-basic
                wire:click="toggleSelectAll"
                :size="'sm'"
                title="Alle Auswählen/Entfernen"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                </svg>
            </x-ui.buttons.button-basic>
            @php
                $isDisabled = count($selectedCourses) === 0;
            @endphp
            <x-dropdown align="left">
                <x-slot name="trigger">
                    <button
                        type="button"
                        @class([
                            'text-sm border px-3 py-1 rounded-lg relative flex items-center justify-center bg-gray-100',
                            'cursor-not-allowed opacity-50' => $isDisabled,
                            'cursor-pointer' => ! $isDisabled,
                        ])
                        @if($isDisabled) disabled @endif
                    >
                        <svg class="w-4 h-4 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z" />
                        </svg>

                        @if(! $isDisabled)
                            <span class="ml-2 bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ count($selectedCourses) }}
                            </span>
                        @endif
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link href="#" wire:click.prevent="removeSelectedCourses" class="hover:bg-red-100">
                        <i class="far fa-align-slash mr-2"></i>
                        Auswahl entfernen
                    </x-dropdown-link>
                    <x-dropdown-link href="#" wire:click.prevent="$dispatch('openCourseExportModal', [{{ json_encode($selectedCourses) }}])" class="hover:bg-green-100" :can="'courses.export'">
                        <i class="far fa-download mr-2"></i>
                        Exportieren
                    </x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>
        <div class="flex items-center space-x-2">
            <x-tables.search-field
                resultsCount="{{ $courses->count() }}"
                wire:model.live="search"
            />
            <div class="relative">
                <x-ui.forms.lz-select
                    model="active"
                    :selected="$active"
                    :options="[
                        ['value' => '', 'label' => 'Status auswählen'],
                        ['value' => 'active', 'label' => 'laufend'],
                        ['value' => 'planned', 'label' => 'geplante'],
                        ['value' => 'finished', 'label' => 'abgeschlossene'],
                    ]"
                    width="56"
                />
            </div>
            <div class="relative">
                @php
                    $currentTerm = $terms->firstWhere('id', $selectedTerm ?? null);
                @endphp

                <x-ui.forms.lz-select
                    model="selectedTerm"
                    :selected="$selectedTerm"
                    :selected-label="$currentTerm?->name ?? 'alle Termine'"
                    icon="fal fa-calendar-alt text-[16px]"
                    width="56"
                >
                    <x-slot name="content">
                        <button
                            type="button"
                            data-lz-select-option
                            wire:click="$set('selectedTerm', '')"
                            @class([
                                'flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-gray-50 cursor-pointer',
                                'bg-sky-50 text-sky-700' => empty($selectedTerm),
                            ])
                        >
                            <div class="flex flex-col">
                                <span class="font-medium">alle Termine</span>
                                <span class="text-xs text-gray-500">Keine Einschränkung</span>
                            </div>
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        @foreach($terms as $term)
                            <button
                                type="button"
                                data-lz-select-option
                                wire:click="$set('selectedTerm', '{{ $term->id }}')"
                                @class([
                                    'group/termselectoption flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-gray-50 cursor-pointer',
                                    'bg-sky-50 text-sky-700' => $selectedTerm === $term->id,
                                ])
                            >
                                <div class="flex flex-col w-full py-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-medium">
                                            {{ $term->name }}
                                        </span>
                                        <div class="hidden group-hover/termselectoption:inline-flex">
                                            <x-ui.badge.badge
                                                :color="'blue'"
                                                :size="'sm'"
                                            >
                                                {{ $term->cnt }} Baustein{{ $term->cnt !== 1 ? 'e' : '' }}
                                            </x-ui.badge.badge>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ $term->start }}
                                        &ndash;
                                        {{ $term->end }}
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </x-slot>
                </x-ui.forms.lz-select>
            </div>
            <div class="relative">
                <x-ui.forms.lz-select
                    model="contentFilter"
                    :selected="$contentFilter"
                    :options="[
                        ['value' => '', 'label' => 'Inhalts-Status'],
                        ['label' => 'Allgemein', 'options' => [
                            ['value' => 'all_ok', 'label' => 'vollständig'],
                            ['value' => 'all_partial', 'label' => 'teilweise'],
                            ['value' => 'all_missing', 'label' => 'fehlt'],
                        ]],
                        ['label' => 'Dokumentation', 'options' => [
                            ['value' => 'doc_ok', 'label' => 'vollständig'],
                            ['value' => 'doc_partial', 'label' => 'teilweise'],
                            ['value' => 'doc_missing', 'label' => 'fehlt'],
                        ]],
                        ['label' => 'Roter Faden', 'options' => [
                            ['value' => 'rf_ok', 'label' => 'vorhanden'],
                            ['value' => 'rf_missing', 'label' => 'fehlt'],
                        ]],
                        ['label' => 'Bestätigungen', 'options' => [
                            ['value' => 'ack_ok', 'label' => 'alle bestätigt'],
                            ['value' => 'ack_partial', 'label' => 'teilweise bestätigt'],
                            ['value' => 'ack_missing', 'label' => 'keine bestätigt'],
                        ]],
                        ['label' => 'Rechnung (optional)', 'options' => [
                            ['value' => 'inv_ok', 'label' => 'vorhanden'],
                            ['value' => 'inv_missing', 'label' => 'fehlt'],
                        ]],
                    ]"
                    width="56"
                />
            </div>
            <div class="relative">
                <x-ui.forms.lz-select
                    model="perPage"
                    :selected="$perPage"
                    :options="[
                        ['value' => 15, 'label' => '15 pro Seite'],
                        ['value' => 30, 'label' => '30 pro Seite'],
                        ['value' => 50, 'label' => '50 pro Seite'],
                        ['value' => 100, 'label' => '100 pro Seite'],
                    ]"
                    width="48"
                />
            </div>
        </div>
    </div>
    <div class="w-full">
        <x-tables.table
            :columns="[
                ['label' => 'Titel', 'key' => 'title', 'width' => '30%', 'sortable' => true, 'hideOn' => 'none'],
                ['label' => 'Termin', 'key' => 'planned_start_date', 'width' => '18%', 'sortable' => true, 'hideOn' => 'xl'],
                ['label' => 'Status', 'key' => 'is_active', 'width' => '5%', 'sortable' => false, 'hideOn' => 'md'],
                ['label' => 'Dozent', 'key' => 'tutor_name', 'width' => '20%', 'sortable' => true, 'hideOn' => 'md'],
                ['label' => 'Inhalte', 'key' => 'activity', 'width' => '27%', 'sortable' => false, 'hideOn' => 'md'],
            ]"
            :items="$courses"
            :selected-items="$selectedCourses"
            row-view="components.tables.rows.courses.course-row"
            actions-view="components.tables.rows.courses.course-actions"
            :sort-by="$sortBy ?? null"
            :sort-dir="$sortDir ?? 'asc'"
        />
        <div class="py-4">
            {{ $courses->links() }}
        </div>
    </div>
    <livewire:admin.courses.course-export-modal />
    <livewire:tools.file-pools.file-preview-modal />
</div>
