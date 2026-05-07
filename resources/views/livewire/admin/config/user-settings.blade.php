<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="font-semibold">Einstellungen gespeichert</div>
                    <div class="mt-0.5 text-emerald-600/80">{{ session('success') }}</div>
                </div>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">Aktualisiert</span>
            </div>
        </div>
    @endif

    <x-settings-collapse>
        <x-slot name="trigger">
            Registrierungsregeln
        </x-slot>
        <x-slot name="content">
            <div class="grid gap-6 xl:grid-cols-[1.05fr,1.35fr]">
                <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-sky-50 p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Kursfenster</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">Anmeldung und Nachlauf zentral steuern</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Diese Werte definieren, wie weit vor Kursstart Anmeldungen zulässig sind und wie lange nach Kursende Aktionen noch offen bleiben.
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white/90 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Vor Kursstart</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $openBeforeDays }}</div>
                            <div class="mt-1 text-xs text-slate-500">Tage aktuell freigegeben</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white/90 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Nach Kursende</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $closeAfterDays }}</div>
                            <div class="mt-1 text-xs text-slate-500">Tage aktuell freigegeben</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700">1</span>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Freigabe vor Start</div>
                                <div class="text-xs text-slate-500">Zeitfenster für neue Anmeldungen</div>
                            </div>
                        </div>

                        <label class="mt-5 block text-sm font-medium text-slate-700">Erlaubte Tage VOR Kursstart</label>
                        <select wire:model="openBeforeDays" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm shadow-sm transition focus:border-sky-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100">
                            @foreach ($dayOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} vor Kursstart</option>
                            @endforeach
                        </select>
                        @error('openBeforeDays')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">2</span>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Nachlauf nach Ende</div>
                                <div class="text-xs text-slate-500">Steuert Restzugriffe nach Kursende</div>
                            </div>
                        </div>

                        <label class="mt-5 block text-sm font-medium text-slate-700">Erlaubte Tage NACH Kursende</label>
                        <select wire:model="closeAfterDays" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            @foreach ($dayOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} nach Kursende</option>
                            @endforeach
                        </select>
                        @error('closeAfterDays')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
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
                @php
                    $roles = ['tutor', 'guest'];
                    $configuredSlots = collect($roles)->filter(function ($role) use ($testUsers, $storedTestUserPasswords) {
                        $selectedUserId = data_get($testUsers, $role . '.user_id');
                        $hasPassword = $storedTestUserPasswords[$role] || filled(data_get($testUsers, $role . '.password'));

                        return filled($selectedUserId) && $hasPassword;
                    })->count();
                    $passwordReadySlots = collect($roles)->filter(function ($role) use ($testUsers, $storedTestUserPasswords) {
                        return $storedTestUserPasswords[$role] || filled(data_get($testUsers, $role . '.password'));
                    })->count();
                    $anonymizedSlots = collect($roles)->filter(fn ($role) => (bool) data_get($testUsers, $role . '.anonymize_output', false))->count();
                @endphp

                <div class="space-y-6">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konfiguriert</div>
                            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $configuredSlots }}/{{ count($roles) }}</div>
                            <div class="mt-1 text-sm text-slate-500">Testzugänge vollständig hinterlegt</div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Passwörter</div>
                            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $passwordReadySlots }}/{{ count($roles) }}</div>
                            <div class="mt-1 text-sm text-slate-500">Slots mit vorhandenem Zugangspasswort</div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Anonymisierung</div>
                            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $anonymizedSlots }}</div>
                            <div class="mt-1 text-sm text-slate-500">Ausgaben aktuell anonymisiert</div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-gradient-to-r from-sky-50 via-white to-emerald-50 p-5 shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Base-Zugänge</div>
                                <h3 class="mt-2 text-lg font-semibold text-slate-900">Dozenten und Teilnehmer separat verwalten</h3>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                    Jeder Slot speichert Auswahl, Passwort und Darstellungsmodus separat. So lässt sich pro Testrolle sauber steuern, welcher Benutzer verwendet wird und ob personenbezogene Daten in der Oberfläche anonymisiert erscheinen sollen.
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-xs text-slate-500 shadow-sm">
                                Änderungen gelten erst nach dem Speichern.
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 2xl:grid-cols-2">
                        @foreach ($roles as $role)
                            @php
                                $selectedUserId = data_get($testUsers, $role . '.user_id');
                                $selectedUser = $selectedUserId ? ($selectedTestUsers[$selectedUserId] ?? null) : null;
                                $roleLabel = $this->getTestUserRoleLabel($role);
                                $roleSingleLabel = $this->getTestUserRoleSingularLabel($role);
                                $anonymized = (bool) data_get($testUsers, $role . '.anonymize_output', false);
                                $hasPassword = $storedTestUserPasswords[$role] || filled(data_get($testUsers, $role . '.password'));
                                $isConfigured = filled($selectedUserId) && $hasPassword;
                                $isTutor = $role === 'tutor';
                            @endphp

                            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                                <div class="h-1.5 bg-gradient-to-r {{ $isTutor ? 'from-sky-500 via-cyan-400 to-sky-300' : 'from-emerald-500 via-teal-400 to-emerald-300' }}"></div>

                                <div class="p-5">
                                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isTutor ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                    {{ $roleLabel }}
                                                </span>
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isConfigured ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $isConfigured ? 'Vollständig konfiguriert' : 'Noch unvollständig' }}
                                                </span>
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $anonymized ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $anonymized ? 'Anonymisierte Ausgabe' : 'Klardaten-Ausgabe' }}
                                                </span>
                                            </div>

                                            <h3 class="mt-3 text-lg font-semibold text-slate-900">{{ $roleSingleLabel }}-Testzugang</h3>
                                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                                Wähle den Benutzer für diesen Bereich aus und definiere direkt darunter Darstellung und Zugangsdaten.
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <x-button wire:click="openTestUserModal('{{ $role }}')" class="text-xs">
                                                Benutzer auswählen
                                            </x-button>

                                            @if ($selectedUserId)
                                                <x-button wire:click="clearTestUser('{{ $role }}')" class="!bg-red-600 text-xs hover:!bg-red-700">
                                                    Slot leeren
                                                </x-button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-5 grid gap-4 xl:grid-cols-[1.15fr,0.85fr]">
                                        <div class="rounded-3xl border {{ $isTutor ? 'border-sky-100 bg-sky-50/60' : 'border-emerald-100 bg-emerald-50/60' }} p-4">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aktuelle Auswahl</div>

                                            @if ($selectedUser)
                                                <div class="mt-4 space-y-4">
                                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                        <x-user.public-info :user="$selectedUser" />

                                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                                            <span class="rounded-full px-2.5 py-1 {{ $selectedUser->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                                {{ $selectedUser->status ? 'Aktiv' : 'Inaktiv' }}
                                                            </span>
                                                            <span class="rounded-full px-2.5 py-1 {{ $hasPassword ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                                {{ $hasPassword ? 'Passwort hinterlegt' : 'Passwort fehlt' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-3 sm:grid-cols-3">
                                                        <div class="rounded-2xl border border-white/80 bg-white/90 p-3">
                                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Benutzer-ID</div>
                                                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedUser->id }}</div>
                                                        </div>
                                                        <div class="rounded-2xl border border-white/80 bg-white/90 p-3">
                                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">E-Mail</div>
                                                            <div class="mt-1 truncate text-sm font-medium text-slate-700">{{ $selectedUser->email }}</div>
                                                        </div>
                                                        <div class="rounded-2xl border border-white/80 bg-white/90 p-3">
                                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Darstellung</div>
                                                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $anonymized ? 'Anonymisiert' : 'Klardaten' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif ($selectedUserId)
                                                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                                    Der gespeicherte Benutzer mit ID {{ $selectedUserId }} wurde nicht gefunden.
                                                </div>
                                            @else
                                                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white/80 p-5 text-sm text-slate-500">
                                                    Für diesen Slot ist noch kein Testbenutzer ausgewählt.
                                                </div>
                                            @endif
                                        </div>

                                        <div class="space-y-4">
                                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konfigurationsstatus</div>
                                                <div class="mt-3 space-y-3">
                                                    <div class="flex items-center justify-between rounded-2xl bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-600">Benutzer</span>
                                                        <span class="font-medium {{ $selectedUserId ? 'text-emerald-700' : 'text-amber-700' }}">
                                                            {{ $selectedUserId ? 'Ausgewählt' : 'Fehlt' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-2xl bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-600">Passwort</span>
                                                        <span class="font-medium {{ $hasPassword ? 'text-emerald-700' : 'text-amber-700' }}">
                                                            {{ $hasPassword ? 'Vorhanden' : 'Offen' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between rounded-2xl bg-white px-3 py-2 text-sm">
                                                        <span class="text-slate-600">Ausgabe</span>
                                                        <span class="font-medium text-slate-700">
                                                            {{ $anonymized ? 'Anonymisiert' : 'Klardaten' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Workflow</div>
                                                <div class="mt-3 text-sm leading-6 text-slate-600">
                                                    1. Benutzer wählen
                                                    <br>
                                                    2. Darstellung festlegen
                                                    <br>
                                                    3. Passwort speichern
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">Datenausgabe</div>
                                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                                        Bestimmt, ob die Oberfläche für diesen Testzugang personenbezogene Daten anonymisiert anzeigen soll.
                                                    </p>
                                                </div>

                                                <x-ui.forms.checkbox
                                                    id="anonymize-{{ $role }}"
                                                    wire:model.live="testUsers.{{ $role }}.anonymize_output"
                                                    :toggle="true"
                                                    :disabled="! $selectedUserId"
                                                    label="Anonymisieren"
                                                />
                                            </div>

                                            <div class="mt-4 rounded-2xl border border-white bg-white px-3 py-2 text-xs {{ $selectedUserId ? 'text-slate-500' : 'text-amber-700' }}">
                                                {{ $selectedUserId ? 'Die Einstellung wird zusammen mit dem Testzugang gespeichert.' : 'Wähle zuerst einen Benutzer aus, damit die Darstellung für diesen Slot gesetzt werden kann.' }}
                                            </div>

                                            @error('testUsers.' . $role . '.anonymize_output')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <label class="block text-sm font-semibold text-slate-900">
                                                Passwort für {{ $roleSingleLabel }}-Testzugang
                                            </label>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                                Dieses Passwort wird verschlüsselt gespeichert und beim erneuten Laden nicht offen angezeigt.
                                            </p>

                                            <input
                                                type="password"
                                                wire:model.defer="testUsers.{{ $role }}.password"
                                                @disabled(! $selectedUserId)
                                                class="mt-4 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                                placeholder="{{ $storedTestUserPasswords[$role] ? 'Nur ausfüllen, um das Passwort zu ändern' : 'Passwort eingeben' }}"
                                            >

                                            <div class="mt-3 rounded-2xl border border-white bg-white px-3 py-2 text-xs {{ $hasPassword ? 'text-slate-500' : 'text-amber-700' }}">
                                                @if (! $selectedUserId)
                                                    Wähle zuerst einen Benutzer aus, bevor du Zugangsdaten hinterlegst.
                                                @elseif ($storedTestUserPasswords[$role])
                                                    Ein Passwort ist bereits gespeichert. Leer lassen, um es unverändert zu übernehmen.
                                                @else
                                                    Für neue oder geänderte Testbenutzer muss ein Passwort hinterlegt werden.
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
                            </article>
                        @endforeach
                    </div>
                </div>
            </x-slot>
        </x-settings-collapse>

        <x-settings-collapse>
            <x-slot name="trigger">
                Master-Passwort
            </x-slot>
            <x-slot name="content">
                <div class="grid gap-6 xl:grid-cols-[0.95fr,1.25fr]">
                    <div class="rounded-3xl border {{ $this->masterIsActive ? 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50' : 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50' }} p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $this->masterIsActive ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $this->masterIsActive ? 'Aktiv' : 'Inaktiv' }}
                            </span>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm">Globaler Login-Fallback</span>
                        </div>

                        <h3 class="mt-3 text-lg font-semibold text-slate-900">Status des Master-Passworts</h3>

                        @if($this->masterIsActive)
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                Das Master-Passwort ist aktiv und kann als alternativer Passwort-Check für Benutzer-Logins verwendet werden.
                            </p>
                            <div class="mt-5 rounded-2xl border border-white bg-white/90 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gültig bis</div>
                                <div class="mt-2 text-lg font-semibold text-slate-900">
                                    {{ \Illuminate\Support\Carbon::parse($this->masterExpiresAt)->tz(config('app.timezone'))->format('d.m.Y H:i') }}
                                </div>
                            </div>
                            <div class="mt-5">
                                <x-button wire:click="revokeMasterPassword" class="!bg-red-600 hover:!bg-red-700">
                                    Sofort deaktivieren
                                </x-button>
                            </div>
                        @else
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                Aktuell ist kein globales Master-Passwort aktiv. Neue Zugangsdaten können rechts direkt angelegt werden.
                            </p>
                            <div class="mt-5 rounded-2xl border border-white bg-white/90 p-4 text-sm text-slate-500">
                                Kein aktives Master-Passwort vorhanden.
                            </div>
                        @endif
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">Neu</span>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Master-Passwort setzen</div>
                                <div class="text-xs text-slate-500">Gültigkeit und Passwort direkt gemeinsam definieren</div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Neues Master-Passwort</label>
                                <input type="password" wire:model.defer="newMasterPassword" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100" placeholder="••••••••">
                                @error('newMasterPassword')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <label class="mb-2 mt-4 block text-sm font-medium text-slate-700">Bestätigung</label>
                                <input type="password" wire:model.defer="newMasterPassword_confirmation" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100" placeholder="••••••••">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Gültigkeitsdauer</label>
                                <select wire:model="validFor" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-100">
                                    @foreach ($validForOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('validFor')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs leading-6 text-slate-500">
                                    Das Passwort wird gehasht gespeichert. Es ist später nicht einsehbar, sondern nur deaktivierbar.
                                </div>

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
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 shadow-sm">
            <div class="mb-2 font-semibold">Bitte prüfen</div>
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-slate-500">
            Änderungen an Registrierungsregeln, Testbenutzern und Master-Passwort werden zentral über diesen Bereich gespeichert.
        </div>
        <x-button wire:click="save" wire:loading.attr="disabled" class="sm:ml-3">
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
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Benutzerliste</div>
                            <h3 class="mt-2 text-base font-semibold text-slate-900">Passenden {{ strtolower($this->getTestUserRoleSingularLabel($testUserModalRole)) }} auswählen</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Die Liste zeigt aktive Benutzer zuerst. Gesucht wird nach Name oder E-Mail-Adresse.
                            </p>
                        </div>

                        @if ($currentSelectedUser)
                            <div class="rounded-2xl border border-white bg-white px-4 py-3 text-xs text-slate-500 shadow-sm">
                                <span class="font-medium text-slate-700">Aktuell:</span>
                                {{ trim(($currentSelectedUser->person->nachname ?? '') . ', ' . ($currentSelectedUser->person->vorname ?? ''), ' ,') ?: ($currentSelectedUser->name ?? $currentSelectedUser->email) }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-4">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="testUserSearch"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                            placeholder="Nach Name oder E-Mail suchen"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-between px-1 text-xs text-slate-500">
                    <span>{{ $modalUsers->count() }} Benutzer geladen</span>
                    <span>Aktive Konten werden bevorzugt angezeigt</span>
                </div>

                <div class="grid max-h-[30rem] gap-3 overflow-y-auto pr-1">
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
                            class="rounded-3xl border px-4 py-4 text-left shadow-sm transition {{ $isSelected ? 'border-sky-300 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' }}"
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

                                    <div class="mt-3 flex flex-wrap items-center gap-2 pl-14 text-xs">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">ID {{ $user->id }}</span>
                                        <span class="rounded-full px-2.5 py-1 {{ $user->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $user->status ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                                            {{ $this->getTestUserRoleSingularLabel($user->role) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    @if ($isSelected)
                                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Aktuell ausgewählt</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Übernehmen</span>
                                    @endif
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                            Keine passenden Benutzer gefunden.
                        </div>
                    @endforelse
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between gap-3">
                <div class="text-xs text-slate-500">
                    Auswahl wird direkt in den Slot übernommen, aber erst mit dem Speichern dauerhaft gesichert.
                </div>
                <x-button wire:click="closeTestUserModal" class="!bg-gray-600 hover:!bg-gray-700">
                    Schließen
                </x-button>
            </div>
        </x-slot>
    </x-modal.modal>
</div>
