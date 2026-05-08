<div class="space-y-5">
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <x-settings-collapse>
        <x-slot name="trigger">
            Registrierungsregeln
        </x-slot>
        <x-slot name="content">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Erlaubte Tage VOR Kursstart</label>
                    <select wire:model="openBeforeDays" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-sky-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100">
                        @foreach ($dayOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }} vor Kursstart</option>
                        @endforeach
                    </select>
                    @error('openBeforeDays')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Erlaubte Tage NACH Kursende</label>
                    <select wire:model="closeAfterDays" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        @foreach ($dayOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }} nach Kursende</option>
                        @endforeach
                    </select>
                    @error('closeAfterDays')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-slot>
    </x-settings-collapse>

    @if (Auth::user()->role == 'admin' || Auth::user()->current_team_id === 2)
        <x-settings-collapse>
            <x-slot name="trigger">
                Testbenutzer für Base
            </x-slot>
            <x-slot name="content">
                <div class="grid gap-5 xl:grid-cols-2">
                    @foreach (['tutor', 'guest'] as $role)
                        @php
                            $selectedUserId = data_get($testUsers, $role . '.user_id');
                            $selectedUser = $selectedUserId ? ($selectedTestUsers[$selectedUserId] ?? null) : null;
                            $roleLabel = $this->getTestUserRoleLabel($role);
                            $roleSingleLabel = $this->getTestUserRoleSingularLabel($role);
                            $anonymized = (bool) data_get($testUsers, $role . '.anonymize_output', false);
                            $hasPassword = $storedTestUserPasswords[$role] || filled(data_get($testUsers, $role . '.password'));
                            $isTutor = $role === 'tutor';
                        @endphp

                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="h-1 {{ $isTutor ? 'bg-sky-500' : 'bg-emerald-500' }}"></div>

                            <div class="p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-slate-900">{{ $roleSingleLabel }}-Testzugang</h3>
                                            @if ($selectedUserId && $hasPassword)
                                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Bereit</span>
                                            @elseif ($selectedUserId)
                                                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Passwort fehlt</span>
                                            @endif
                                            @if ($selectedUserId && $anonymized)
                                                <span class="rounded-full bg-slate-900 px-2 py-1 text-xs font-medium text-white">Anonymisiert</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $roleLabel }}</div>
                                    </div>

                                    <div class="flex gap-2">
                                        <x-button wire:click="openTestUserModal('{{ $role }}')" class="text-xs">
                                            Benutzer auswählen
                                        </x-button>
                                        @if ($selectedUserId)
                                            <x-button wire:click="clearTestUser('{{ $role }}')" class="!bg-red-600 text-xs hover:!bg-red-700">
                                                Entfernen
                                            </x-button>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    @if ($selectedUser)
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <x-user.public-info :user="$selectedUser" />
                                                <div class="mt-2 text-xs text-slate-500">{{ $selectedUser->email }}</div>
                                            </div>

                                            <div class="flex flex-wrap gap-2 text-xs">
                                                <span class="rounded-full px-2.5 py-1 {{ $selectedUser->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $selectedUser->status ? 'Aktiv' : 'Inaktiv' }}
                                                </span>
                                                <span class="rounded-full px-2.5 py-1 {{ $hasPassword ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $hasPassword ? 'Passwort hinterlegt' : 'Passwort offen' }}
                                                </span>
                                            </div>
                                        </div>
                                    @elseif ($selectedUserId)
                                        <div class="text-sm text-amber-700">
                                            Der gespeicherte Benutzer mit ID {{ $selectedUserId }} wurde nicht gefunden.
                                        </div>
                                    @else
                                        <div class="text-sm text-slate-500">
                                            Kein Testbenutzer ausgewählt.
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-[0.9fr,1.1fr]">
                                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-900">Datenausgabe</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $anonymized ? 'Anonymisiert' : 'Klardaten' }}</div>
                                            </div>

                                            <x-ui.forms.checkbox
                                                id="anonymize-{{ $role }}"
                                                wire:model.live="testUsers.{{ $role }}.anonymize_output"
                                                :toggle="true"
                                                :disabled="! $selectedUserId"
                                                label="Anonymisieren"
                                            />
                                        </div>
                                        @error('testUsers.' . $role . '.anonymize_output')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                                        <label class="mb-2 block text-sm font-semibold text-slate-900">
                                            Passwort für {{ $roleSingleLabel }}
                                        </label>
                                        <input
                                            type="password"
                                            wire:model.defer="testUsers.{{ $role }}.password"
                                            @disabled(! $selectedUserId)
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            placeholder="{{ $storedTestUserPasswords[$role] ? 'Nur ausfüllen, um das Passwort zu ändern' : 'Passwort eingeben' }}"
                                        >
                                        <div class="mt-2 text-xs text-slate-500">
                                            @if (! $selectedUserId)
                                                Zuerst einen Benutzer auswählen.
                                            @elseif ($storedTestUserPasswords[$role])
                                                Leer lassen, um das gespeicherte Passwort zu behalten.
                                            @else
                                                Passwort für diesen Testzugang hinterlegen.
                                            @endif
                                        </div>
                                        @error('testUsers.' . $role . '.user_id')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                        @error('testUsers.' . $role . '.password')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-settings-collapse>

        <x-settings-collapse>
            <x-slot name="trigger">
                Master-Passwort
            </x-slot>
            <x-slot name="content">
                <div class="grid gap-4 lg:grid-cols-[0.95fr,1.05fr]">
                    <div class="rounded-2xl border {{ $this->masterIsActive ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4 shadow-sm">
                        @if($this->masterIsActive)
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">Master-Passwort aktiv</div>
                                    <div class="mt-1 text-xs text-slate-600">
                                        Gültig bis {{ \Illuminate\Support\Carbon::parse($this->masterExpiresAt)->tz(config('app.timezone'))->format('d.m.Y H:i') }}
                                    </div>
                                </div>

                                <x-button wire:click="revokeMasterPassword" class="!bg-red-600 text-xs hover:!bg-red-700">
                                    Deaktivieren
                                </x-button>
                            </div>
                        @else
                            <div class="text-sm font-semibold text-slate-900">Kein aktives Master-Passwort</div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Neues Master-Passwort</label>
                                <input type="password" wire:model.defer="newMasterPassword" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100" placeholder="••••••••">
                                @error('newMasterPassword')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <label class="mb-2 mt-4 block text-sm font-semibold text-slate-800">Bestätigung</label>
                                <input type="password" wire:model.defer="newMasterPassword_confirmation" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100" placeholder="••••••••">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Gültigkeitsdauer</label>
                                <select wire:model="validFor" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100">
                                    @foreach ($validForOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('validFor')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-4 text-right">
                                    <x-button wire:click="setMasterPassword" wire:loading.attr="disabled">
                                        Master-Passwort setzen
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot>
        </x-settings-collapse>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <div class="mb-2 font-semibold">Bitte prüfen</div>
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex justify-end">
        <x-button wire:click="save" wire:loading.attr="disabled">
            Einstellungen speichern
        </x-button>
    </div>

    <x-modal.modal wire:model="showTestUserModal" :maxWidth="'4xl'">
        <x-slot name="title">
            {{ $this->getTestUserRoleLabel($testUserModalRole) }}-Testbenutzer auswählen
        </x-slot>

        <x-slot name="content">
            @php
                $currentSelectedUserId = data_get($testUsers, $testUserModalRole . '.user_id');
                $currentSelectedUser = $currentSelectedUserId ? ($selectedTestUsers[$currentSelectedUserId] ?? null) : null;
            @endphp

            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="testUserSearch"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                        placeholder="Nach Name oder E-Mail suchen"
                    >

                    @if ($currentSelectedUser)
                        <div class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                            Aktuell: {{ trim(($currentSelectedUser->person->nachname ?? '') . ', ' . ($currentSelectedUser->person->vorname ?? ''), ' ,') ?: ($currentSelectedUser->name ?? $currentSelectedUser->email) }}
                        </div>
                    @endif
                </div>

                <div class="max-h-[30rem] space-y-3 overflow-y-auto pr-1">
                    @forelse ($modalUsers as $user)
                        @php
                            $isSelected = (int) $user->id === (int) $currentSelectedUserId;
                            $personDisplayName = trim(($user->person->nachname ?? '') . ', ' . ($user->person->vorname ?? ''), ' ,');
                            $displayName = $personDisplayName !== '' ? $personDisplayName : ($user->name ?? '-');
                        @endphp

                        <button
                            type="button"
                            wire:key="test-user-option-{{ $testUserModalRole }}-{{ $user->id }}"
                            wire:click="selectTestUser({{ $user->id }})"
                            class="w-full rounded-2xl border px-4 py-4 text-left transition {{ $isSelected ? 'border-sky-300 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' }}"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <img
                                            src="{{ $user->baseProfilePhotoUrl }}"
                                            alt="{{ $displayName }}"
                                            class="h-11 w-11 rounded-full object-cover"
                                        >
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-slate-900">{{ $displayName }}</div>
                                            <div class="truncate text-xs text-slate-500">{{ $user->email }}</div>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2 pl-14 text-xs">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">ID {{ $user->id }}</span>
                                        <span class="rounded-full px-2.5 py-1 {{ $user->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $user->status ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-xs font-medium {{ $isSelected ? 'text-sky-700' : 'text-slate-500' }}">
                                    {{ $isSelected ? 'Ausgewählt' : 'Übernehmen' }}
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                            Keine passenden Benutzer gefunden.
                        </div>
                    @endforelse
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-button wire:click="closeTestUserModal" class="!bg-gray-600 hover:!bg-gray-700">
                Schließen
            </x-button>
        </x-slot>
    </x-modal.modal>
</div>
