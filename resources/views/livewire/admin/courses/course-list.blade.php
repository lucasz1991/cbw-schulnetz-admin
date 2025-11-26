<div class="px-2">
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 23.625 23.625" fill="currentColor" aria-hidden="true">
                    <path
                        d="M11.812 0C5.289 0 0 5.289 0 11.812s5.289 11.813 11.812 11.813 11.813-5.29 11.813-11.813S18.335 0 11.812 0zm2.459 18.307c-.608.24-1.092.422-1.455.548a3.838 3.838 0 0 1-1.262.189c-.736 0-1.309-.18-1.717-.539s-.611-.814-.611-1.367c0-.215.015-.435.045-.659a8.23 8.23 0 0 1 .147-.759l.761-2.688c.067-.258.125-.503.171-.731.046-.23.068-.441.068-.633 0-.342-.071-.582-.212-.717-.143-.135-.412-.201-.813-.201-.196 0-.398.029-.605.09-.205.063-.383.12-.529.176l.201-.828c.498-.203.975-.377 1.43-.521a4.225 4.225 0 0 1 1.29-.218c.731 0 1.295.178 1.692.53.395.353.594.812.594 1.376 0 .117-.014.323-.041.617a4.129 4.129 0 0 1-.152.811l-.757 2.68a7.582 7.582 0 0 0-.167.736 3.892 3.892 0 0 0-.073.626c0 .356.079.599.239.728.158.129.435.194.827.194.185 0 .392-.033.626-.097.232-.064.4-.121.506-.17l-.203.827zm-.134-10.878a1.807 1.807 0 0 1-1.275.492c-.496 0-.924-.164-1.28-.492a1.57 1.57 0 0 1-.533-1.193c0-.465.18-.865.533-1.196a1.812 1.812 0 0 1 1.28-.497c.497 0 .923.165 1.275.497.353.331.53.731.53 1.196 0 .467-.177.865-.53 1.193z"
                        data-original="#030104" />
                </svg>
            </div>
            <div class="ml-3">
                <div class="text-sm">
                    <p class="font-medium">Tipp:</p>
                    <p class="mt-1">Sie können die Bausteine nach ihrem Status filtern (aktiv, inaktiv, geplant, abgeschlossen) und nach Terminen gruppieren.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex items-center ">
        <h1 class="text-2xl font-bold text-gray-700">Bausteine</h1>
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
            </x-ui.buttons.button-basic >
{{-- Bulk Actions via x-dropdown --}}
@php
    $isDisabled = count($selectedCourses) === 0;
@endphp

<x-dropdown align="left" >
    {{-- Trigger --}}
    <x-slot name="trigger">
        <button
            type="button"
            @class([
                'text-sm border px-3 py-1 rounded-lg relative flex items-center justify-center bg-gray-100',
                'cursor-not-allowed opacity-50' => $isDisabled,
                'cursor-pointer' => !$isDisabled,
            ])
            @if($isDisabled) disabled @endif
        >
            <svg class="w-4 h-4 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                 width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/>
            </svg>

            @if(!$isDisabled)
                <span class="ml-2 bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                    {{ count($selectedCourses) }}
                </span>
            @endif
        </button>
    </x-slot>

    {{-- Content --}}
    <x-slot name="content">
            <x-dropdown-link href="#" wire:click.prevent="removeSelectedCourses" class="hover:bg-red-100">
                <i class="far fa-align-slash mr-2"></i>
                Auswahl entfernen
            </x-dropdown-link>
            <x-dropdown-link href="#" wire:click.prevent="$dispatch('openCourseExportModal', [{{ json_encode($selectedCourses) }}])" class="hover:bg-green-100">
                <i class="far fa-download mr-2"></i>
                Exportieren
            </x-dropdown-link>
    </x-slot>
</x-dropdown>
            </div>

        <div class="flex items-center space-x-2">


            {{-- Suchfeld --}}
            <x-tables.search-field 
                resultsCount="{{ $courses->count() }}"
                wire:model.live="search"
            />

            {{-- 🟢 Status-Filter --}}
            <div class="relative">
                <select 
                    wire:model.live="active"
                    class="text-base border border-gray-300 rounded-lg px-2 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                >
                    <option value="">Status auswählen</option>
                    <option value="active">laufend</option>
                    <option value="planned">geplante</option>
                    <option value="finished">abgeschlossene</option>
                </select>
            </div>

            {{-- Hier ein Select feld für die Termin_id s der Course  --}}
            <div class="relative">
            <x-ui.dropdown.anchor-dropdown
                align="right"
                width="56"
                dropdownClasses="mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                contentClasses="bg-white"
                :overlay="false"
                :trap="false"
                :scrollOnOpen="false"
                :offset="6"
            >
                {{-- Trigger --}}
                <x-slot name="trigger">
                    <x-ui.buttons.button-basic
                        type="button"
                        :size="'sm'"
                        class="px-2"
                    >
                        <i class="fal fa-calendar-alt text-[16px]"></i>

                        <span class="hidden md:inline-block ml-2">
                            @php
                                $currentTerm = $terms->firstWhere('id', $selectedTerm ?? null);
                            @endphp

                            @if($currentTerm)
                                {{ $currentTerm->name }}
                            @else
                                alle Termine
                            @endif
                        </span>

                        <i class="fal fa-angle-down ml-1 text-xs"></i>
                    </x-ui.buttons.button-basic>
                </x-slot>

                {{-- Inhalt --}}
                <x-slot name="content">
                    <div class="py-1 text-sm text-gray-700 max-h-80 overflow-y-auto">

                        {{-- Option: alle Termine --}}
                        <button
                            type="button"
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
                        
                        {{-- Termine-Loop --}}
                        @foreach($terms as $term)
                            <button
                                type="button"
                                wire:click="$set('selectedTerm', '{{ $term->id }}')"
                                @class([
                                    'group/termselectoption flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-gray-50 cursor-pointer',
                                    'bg-sky-50 text-sky-700' =>  $selectedTerm === $term->id,
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
                                                :size="'sm'">
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
                    </div>
                </x-slot>
            </x-ui.dropdown.anchor-dropdown>

            </div>
            
            {{-- Inhalts-Status Filter --}}
            <div class="relative">
              <select
                wire:model.live="contentFilter"
                class="text-base border border-gray-300 rounded-lg px-2 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                title="Inhalts-Status filtern"
              >
                <option value="">Inhalts-Status</option>
            
                <optgroup label="Allgemein">
                  <option value="all_ok">vollständig</option>
                  <option value="all_partial">teilweise</option>
                  <option value="all_missing">fehlt</option>
                </optgroup>
            
                <optgroup label="Dokumentation">
                  <option value="doc_ok">vollständig</option>
                  <option value="doc_partial">teilweise</option>
                  <option value="doc_missing">fehlt</option>
                </optgroup>
            
                <optgroup label="Roter Faden">
                  <option value="rf_ok">vorhanden</option>
                  <option value="rf_missing">fehlt</option>
                </optgroup>
            
                <optgroup label="Bestätigungen">
                  <option value="ack_ok">alle bestätigt</option>
                  <option value="ack_partial">teilweise bestätigt</option>
                  <option value="ack_missing">keine bestätigt</option>
                </optgroup>
            
                <optgroup label="Rechnung (optional)">
                  <option value="inv_ok">vorhanden</option>
                  <option value="inv_missing">fehlt</option>
                </optgroup>
              </select>
            </div>
            {{-- PPer page --}}
            <div class="relative">
                <select 
                    wire:model.live="perPage"
                    class="text-base border border-gray-300 rounded-lg px-2 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                >
                    <option value="15">15 pro Seite</option>
                    <option value="30">30 pro Seite</option>
                    <option value="50">50 pro Seite</option>
                    <option value="100">100 pro Seite</option>

                </select> 
            </div>



        </div>


    </div>

    <div class="w-full">
        <x-tables.table
            :columns="[
                ['label'=>'Titel','key'=>'title','width'=>'30%','sortable'=>true,'hideOn'=>'none'],
                ['label'=>'Termin','key'=>'planned_start_date','width'=>'18%','sortable'=>true,'hideOn'=>'xl'],
                ['label'=>'Status','key'=>'is_active','width'=>'5%','sortable'=>false,'hideOn'=>'md'],
                ['label'=>'Dozent','key'=>'tutor_name','width'=>'20%','sortable'=>true,'hideOn'=>'md'],
                ['label'=>'Inhalte','key'=>'activity','width'=>'27%','sortable'=>false,'hideOn'=>'md'],
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
</div>
