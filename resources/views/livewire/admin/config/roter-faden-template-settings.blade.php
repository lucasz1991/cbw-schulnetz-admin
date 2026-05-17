<div class="space-y-6">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Vorlagen</h2>
            <p class="mt-1 text-sm text-gray-600">
                Hier wird die Vorlage gespeichert, die Dozenten im Roter-Faden-Bereich herunterladen können.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <x-settings-collapse>
        <x-slot name="trigger">
            Roter-Faden-Vorlage
        </x-slot>

        <x-slot name="content">
            <div class="space-y-5">
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                    Lade hier eine PDF- oder Word-Datei hoch. Die Datei wird als zentrale Vorlage gespeichert und im Dozentenbereich beim Roter-Faden-Upload angeboten.
                </div>

                @if($template)
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    {{ $template['name'] ?? basename($template['path']) }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    @if($this->templateSizeFormatted)
                                        {{ $this->templateSizeFormatted }}
                                    @endif
                                    @if(!empty($template['mime']))
                                        <span class="mx-1">-</span>{{ $template['mime'] }}
                                    @endif
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="remove"
                                wire:confirm="Roter-Faden-Vorlage wirklich entfernen?"
                                class="inline-flex items-center justify-center rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                Entfernen
                            </button>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">
                        Aktuell ist keine Roter-Faden-Vorlage hinterlegt.
                    </div>
                @endif

                <div>
                    <label for="roter_faden_template_upload" class="block text-sm font-medium text-gray-700">
                        Vorlage hochladen oder ersetzen
                    </label>
                    <input
                        id="roter_faden_template_upload"
                        type="file"
                        wire:model="templateUpload"
                        accept=".pdf,.doc,.docx,.odt"
                        class="mt-2 block w-full rounded-md border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p class="mt-1 text-xs text-gray-500">Erlaubt: PDF, DOC, DOCX, ODT. Maximal 30 MB.</p>
                    @error('templateUpload')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <x-button
                        type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save,templateUpload"
                    >
                        Vorlage speichern
                    </x-button>

                    <span wire:loading wire:target="templateUpload" class="text-sm text-gray-500">
                        Datei wird vorbereitet...
                    </span>
                    <span wire:loading wire:target="save" class="text-sm text-gray-500">
                        Vorlage wird gespeichert...
                    </span>
                </div>
            </div>
        </x-slot>
    </x-settings-collapse>
</div>
