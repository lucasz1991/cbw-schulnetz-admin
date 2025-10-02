<x-settings-collapse>
    <x-slot name="trigger">
        GrapesJS Studio API Einstellungen
    </x-slot>
    <x-slot name="content">
        <div class="grid gap-4">
            <div>
                <label class="block text-sm font-medium">License Key</label>
                <input type="text" wire:model.defer="apiKey"
                    class="mt-1 block w-full border rounded p-2" placeholder="dein-api-key" />
            </div>
        </div>
        <div class="pt-4">
            <x-button wire:click="save" primary>Speichern</x-button>
        </div>
    </x-slot>
</x-settings-collapse>
