<x-dialog-modal wire:model="showModal" maxWidth="2xl">
    <x-slot name="title">
        {{ $userId ? 'Mitarbeiter bearbeiten' : 'Mitarbeiter anlegen' }}
    </x-slot>

    <x-slot name="content">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <x-ui.forms.label value="Name"/>
                <x-ui.forms.input type="text" wire:model.live.defer="name"/>
                <x-ui.forms.input-error for="name"/>
            </div>

            <div>
                <x-ui.forms.label value="E-Mail"/>
                <x-ui.forms.input type="email" wire:model.live.defer="email"/>
                <x-ui.forms.input-error for="email"/>
            </div>

            <div class="md:col-span-2 space-y-1">
                <x-ui.forms.label value="Rolle"/>
                <select class="w-full rounded border-gray-300" wire:model.live.defer="primary_team_id">
                    <option value="">— bitte wählen —</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <x-ui.forms.input-error for="primary_team_id"/>
                <p class="text-xs text-gray-500">
                    Das gewählte Team wird als aktuelles Team gespeichert.
                </p>
            </div>

            <div class="md:col-span-2 grid md:grid-cols-2 gap-4">
                <div>
                    <x-ui.forms.label value="{{ $userId ? 'Neues Passwort (optional)' : 'Passwort' }}"/>
                    <x-ui.forms.input type="password" wire:model.live.defer="password" autocomplete="new-password"/>
                    <x-ui.forms.input-error for="password"/>
                </div>
                <div>
                    <x-ui.forms.label value="Passwort bestätigen"/>
                    <x-ui.forms.input type="password" wire:model.live.defer="password_confirmation" autocomplete="new-password"/>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-ui.buttons.button-basic wire:click="close" class="mr-2">Abbrechen</x-ui.buttons.button-basic>
        <x-ui.buttons.button-basic wire:click="save" wire:loading.attr="disabled">
            Speichern
        </x-ui.buttons.button-basic>
    </x-slot>
</x-dialog-modal>
