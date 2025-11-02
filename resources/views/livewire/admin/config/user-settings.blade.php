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
        <div class="space-y-4">
            {{-- Aktueller Status --}}
            @if($this->masterIsActive)
                <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-800 text-sm">
                    <div class="font-semibold">Master-Passwort ist AKTIV</div>
                    <div>Gültig bis: <strong>{{ \Illuminate\Support\Carbon::parse($this->masterExpiresAt)->tz(config('app.timezone'))->format('d.m.Y H:i') }}</strong></div>
                    <div class="text-xs mt-1 opacity-75">Gilt für alle Logins als alternativer Passwort-Check.</div>
                    <div class="mt-3">
                        <x-button wire:click="revokeMasterPassword" class="!bg-red-600 hover:!bg-red-700">
                            Sofort deaktivieren
                        </x-button>
                    </div>
                </div>
            @else
                <div class="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-emerald-800 text-sm">
                    Kein aktives Master-Passwort.
                </div>
            @endif

            {{-- Neues Master-Passwort setzen --}}
            <div class="grid sm:grid-cols-2 gap-6">
                <div class="rounded-lg border p-4 bg-white">
                    <label class="block text-sm font-semibold mb-2">Neues Master-Passwort</label>
                    <input type="password" wire:model.defer="newMasterPassword" class="w-full border rounded px-3 py-2" placeholder="••••••••">
                    @error('newMasterPassword') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

                    <label class="block text-sm font-semibold mt-4 mb-2">Bestätigung</label>
                    <input type="password"        wire:model.defer="newMasterPassword_confirmation"
 class="w-full border rounded px-3 py-2" placeholder="••••••••">
                </div>

                <div class="rounded-lg border p-4 bg-white">
                    <label class="block text-sm font-semibold mb-2">Gültigkeitsdauer</label>
                    <select wire:model="validFor" class="w-full border rounded px-3 py-2 bg-white">
                        @foreach ($validForOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('validFor') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

                    <p class="text-xs text-gray-500 mt-3">
                        Das Passwort wird gehasht gespeichert. Es ist später nicht einsehbar – nur deaktivierbar.
                    </p>

                    <div class="text-right mt-4">
                        <x-button wire:click="setMasterPassword" wire:loading.attr="disabled">
                            Master-Passwort setzen
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
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
