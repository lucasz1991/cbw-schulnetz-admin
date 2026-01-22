<div x-data="{ isExporting: @entangle('isExporting').live }" :class="{ 'opacity-50 pointer-events-none cursor-wait': isExporting }">

    <x-dialog-modal wire:model="showModal" >
    
        {{-- TITLE --}}
        <x-slot name="title">
            Baustein{{ count($courseIds) > 1 ? 'e' : '' }} exportieren
        </x-slot>
    
        <x-slot name="content">
            {{-- Kurzinfo --}}
            <div class="mb-4 text-sm text-gray-600 space-y-1">
                <p>
                    Es {{ count($courseIds) > 1 ? 'werden' : 'wird' }} <strong>{{ count($courseIds) }}</strong> Baustein{{ count($courseIds) > 1 ? 'e' : '' }} exportiert.
                </p>
                @if($this->selectedCourses->count())
                    <p class="text-xs text-gray-500">
                        Beispiele:
                        {{ $this->selectedCourses->take(3)->pluck('title')->join(' · ') }}
                        @if($this->selectedCourses->count() > 3)
                            &nbsp;… (+{{ $this->selectedCourses->count() - 3 }} weitere)
                        @endif
                    </p>
                @endif
            </div>
    
            {{-- Exportname + ZIP-Toggle oben rechts --}}
            <div class="mb-12">
                <div class="flex items-center justify-between mb-1 gap-4">
                    <label class="block text-xs font-medium text-gray-700">
                        Exportname
                        <input
                            type="text"
                            wire:model.defer="exportName"
                            class="border rounded px-4 py-2 w-full"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Empfohlen bei vielen Dateien oder größeren Kursen.
                        </p>
                    </label>
                    {{-- ZIP-Archiv Toggle
                    <div class="flex items-center gap-2">
                        <x-ui.forms.toggle-button 
                            model="asZip"
                            label="ZIP-Archiv"
                        />
                    </div>
                    --}}
                </div>
    
            </div>
    
            {{-- Optionen als Toggle-Buttons --}}
            <div class="space-y-3 mb-4">
                {{-- Dokumentation / Inhalte --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeDocumentation"
                        label="Dozenten Dokumentationen"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Enthält alle Dozenten Dokumentationen.
                    </div>
                </div>
    
                {{-- Roter Faden --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeRedThread"
                        label="Roter Faden"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Enthält den Roten Faden vom Dozenten.
                    </div>
                </div>
    
                {{-- Teilnehmerlisten --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeParticipants"
                        label="Teilnehmer Bildungsmittel Bestätigungen"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Enthält alle Teilnehmer Bildungsmittel Bestätigungen.
                    </div>
                </div>
    
                {{-- Anwesenheitslisten --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeAttendance"
                        label="Anwesenheitslisten"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Anwesenheitsübersichten pro Unterrichtstag.
                    </div>
                </div>
    
                {{-- Prüfungs Ergebnisse --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeExamResults"
                        label="Prüfungs Ergebnisse"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Enthält die Prüfungs Ergebnisse.
                    </div>
                </div>
    
                {{-- Dozenten-Rechnung  --}}
                <div class="text-sm">
                    <x-ui.forms.toggle-button 
                        model="includeTutorData"
                        label="Dozenten-Rechnung"
                    />
                    <div class="ml-12 text-xs text-gray-500 mt-1">
                        Enthält die Dozenten-Rechnung.
                    </div>
                </div>
    
            </div>
    
            @error('courseIds')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </x-slot>
    
        <x-slot name="footer">
            <div class="flex justify-end gap-2 @if($isExporting) opacity-50 pointer-events-none cursor-wait @endif">
                <x-secondary-button wire:click="close">
                    Abbrechen
                </x-secondary-button>
    
                <x-button
                    wire:click="export"
                    wire:target="export"
                    wire:loading.attr="disabled"
                >
                    <i class="fal fa-download mr-1 text-xs"></i>
                    Download
                </x-button>
                <span x-show="isExporting">Wird exportiert...></span>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
