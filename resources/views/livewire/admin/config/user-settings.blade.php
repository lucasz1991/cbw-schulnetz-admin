<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-md bg-green-50 p-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif
    <x-settings-collapse>
        <x-slot name="trigger">
            Registrierung Regeln
        </x-slot>
        <x-slot name="content">
            <div class="grid sm:grid-cols-2 gap-6">
                {{-- Tage VOR Kursstart --}}
                <div class="rounded-lg border p-4 bg-white">
                    <label class="block text-sm font-semibold mb-2">Erlaubte Tage VOR Kursstart</label>
                    <select wire:model="openBeforeDays" class="w-full border rounded px-3 py-2 bg-white">
                        @foreach ($dayOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }} vor Kursstart</option>
                        @endforeach
                    </select>
                    @error('openBeforeDays') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                {{-- Tage NACH Kursende --}}
                <div class="rounded-lg border p-4 bg-white">
                    <label class="block text-sm font-semibold mb-2">Erlaubte Tage NACH Kursende</label>
                    <select wire:model="closeAfterDays" class="w-full border rounded px-3 py-2 bg-white">
                        @foreach ($dayOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }} nach Kursende</option>
                        @endforeach
                    </select>
                    @error('closeAfterDays') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-slot>
    </x-settings-collapse>
        <x-settings-collapse>
        <x-slot name="trigger">
            Benutzer General Passwort
        </x-slot>
        <x-slot name="content">

        </x-slot>
    </x-settings-collapse>
    @if ($errors->any())
        <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <div class="font-semibold mb-2">Bitte prüfen:</div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
        <div class="text-right">
            <x-button wire:click="save" wire:loading.attr="disabled" class="ml-3">
                Speichern
            </x-button>
        </div>
</div>
