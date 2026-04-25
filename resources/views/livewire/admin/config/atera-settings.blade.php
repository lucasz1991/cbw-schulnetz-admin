<x-settings-collapse>
    <x-slot name="trigger">
        Atera API Einstellungen
    </x-slot>
    <x-slot name="content">
        <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            Diese Werte werden direkt f&uuml;r die Ticket-Erstellung aus dem Teilnehmerbereich verwendet.
        </div>

        <div class="mt-4 grid gap-4">
            <div>
                <label class="block text-sm font-medium">Basis-URL</label>
                <input
                    type="url"
                    wire:model.defer="baseUrl"
                    class="mt-1 block w-full rounded border p-2"
                    placeholder="https://app.atera.com"
                />
                @error('baseUrl')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">API Key</label>
                <input
                    type="text"
                    wire:model.defer="apiKey"
                    class="mt-1 block w-full rounded border p-2"
                    placeholder="atera-api-key"
                />
                @error('apiKey')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Techniker / Support E-Mail</label>
                <input
                    type="email"
                    wire:model.defer="technicianEmail"
                    class="mt-1 block w-full rounded border p-2"
                    placeholder="support@cbw-weiterbildung.de"
                />
                @error('technicianEmail')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <x-button wire:click="save" primary>Speichern</x-button>
        </div>
    </x-slot>
</x-settings-collapse>
