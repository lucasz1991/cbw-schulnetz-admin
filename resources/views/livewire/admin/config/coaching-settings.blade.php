<div class="space-y-5">
    <h2 class="text-lg font-semibold text-gray-700">Einzelcoaching</h2>
    <form wire:submit="save" class="space-y-4">
        <label for="coaching-enabled" class="flex items-center gap-3">
            <input id="coaching-enabled" type="checkbox" wire:model="enabled"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                aria-describedby="coaching-description">
            <span class="text-sm font-medium text-gray-700">Einzelcoaching im Schulnetz aktivieren</span>
        </label>
        <p id="coaching-description" class="text-sm text-gray-500">
            Aktiviert die Einzelcoaching-Terminplanung und den UVS-Abgleich.
            Im UVS muss das neue Verfahren zusätzlich am jeweiligen Vertrag ausgewählt sein.
            Beim Deaktivieren bleiben vorhandene Daten erhalten.
        </p>
        @error('enabled') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled" wire:target="save"
            class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 disabled:opacity-50">
            Speichern
        </button>
        @if (session()->has('coaching_settings_saved'))
            <p role="status" class="text-sm text-green-700">{{ session('coaching_settings_saved') }}</p>
        @endif
    </form>
</div>
