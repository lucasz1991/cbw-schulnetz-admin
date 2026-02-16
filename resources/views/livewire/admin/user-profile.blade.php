<div  x-data="{ selectedTab: $persist('userDetails') }">
    <div class="mb-10 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
            <x-back-button />
            <x-ui.dropdown.anchor-dropdown
                align="right"
                width="48"
                dropdownClasses="mt-1 w-52 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                contentClasses="bg-white py-1"
                :overlay="false"
                :trap="false"
                :scrollOnOpen="false"
                :offset="6"
            >
                <x-slot name="trigger">
                    <x-ui.buttons.button-basic :size="'sm'" :mode="'basic'">
                        <i class="far fa-ellipsis-v mr-2"></i>
                        Optionen
                    </x-ui.buttons.button-basic>
                </x-slot>

                <x-slot name="content">
                    @if ($user->status)
                        <x-ui.dropdown.dropdown-link wire:click="deactivateUser()" class="hover:bg-yellow-100 focus:bg-yellow-100">
                            <i class="far fa-times-circle mr-2"></i>
                            Deaktivieren
                        </x-ui.dropdown.dropdown-link>
                    @else
                        <x-ui.dropdown.dropdown-link wire:click="activateUser()" class="hover:bg-green-100 focus:bg-green-100">
                            <i class="far fa-check-circle mr-2"></i>
                            Aktivieren
                        </x-ui.dropdown.dropdown-link>
                    @endif

                    <x-ui.dropdown.dropdown-link wire:click="openMailModal({{ $user->id }})" class="hover:bg-blue-100 focus:bg-blue-100">
                        <i class="far fa-envelope mr-2"></i>
                        Nachricht senden
                    </x-ui.dropdown.dropdown-link>
                </x-slot>
            </x-ui.dropdown.anchor-dropdown>
        </div>

        <div class="relative">
            <div class="h-24 bg-gradient-to-r from-sky-100 via-blue-50 to-indigo-100"></div>
            <div class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-white"
                 :class="{ 'bg-emerald-500/90': {{ $user->isActive() ? 'true' : 'false' }}, 'bg-rose-500/90': {{ !$user->isActive() ? 'true' : 'false' }} }">
                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                <span>{{ $user->isActive() ? 'Aktiv' : 'Inaktiv' }}</span>
            </div>
            <div class="absolute right-3 top-3 rounded-full bg-black/30 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm">
                Registriert: {{ $user->created_at->format('d.m.Y') }}
            </div>

            <div class="absolute left-1/2 top-full -translate-x-1/2 -translate-y-1/2">
                <div class="h-28 w-28 overflow-hidden rounded-2xl border-4 border-white bg-white shadow-lg md:h-32 md:w-32">
                    <img
                        class="h-full w-full object-cover object-center"
                        src="{{ $user->baseProfilePhotoUrl ?? 'https://via.placeholder.com/150' }}"
                        alt="{{ $user->name }}"
                    >
                </div>
            </div>
        </div>

        <div class="px-4 pb-5 pt-16 md:px-6 md:pt-20">
            <div class="text-center">
                <h2 class="text-xl font-semibold text-slate-900">{{ $user->name }}</h2>
                <div class="mt-3 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                     x-cloak
                     :class="{ 'bg-emerald-50 text-emerald-700 ring-emerald-200': {{ $user->role == 'guest' ? 'true' : 'false' }}, 'bg-sky-50 text-sky-700 ring-sky-200': {{ $user->role == 'tutor' ? 'true' : 'false' }} }">
                    <i class="far fa-user-tag mr-2"></i>
                    {{ $user->role == 'tutor' ? 'Dozent' : 'Teilnehmer' }}
                </div>
            </div>

        </div>
    </div>




    {{-- Tabs: ersetzt dein bisheriges Tab-Menü + Content-Blöcke --}}
    <x-ui.accordion.tabs
        :tabs="[
            'userDetails' => ['label' => 'Details',   'icon' => 'fad fa-id-card'],
            'userCourses'     => ['label' => 'Bausteine', 'icon' => 'fad fa-book-open'],
            'userMessages'     => ['label' => 'Nachrichten', 'icon' => 'fad fa-envelope'],
            'userRequests'     => ['label' => 'Anträge', 'icon' => 'fad fa-paper-plane'],
        ]"
        :collapseAt="'md'"
        default="userDetails"
        persist-key="admin.user.{{ $user->id }}.tabs"
        class="mt-6"
    >
        {{-- TAB: Details --}}
        <x-ui.accordion.tab-panel for="userDetails">
            <div class="w-full">
                @php
                    $persons = $user->persons ?? collect($user->person ? [$user->person] : []);
                    $firstPerson = $persons->first();
                @endphp

                {{-- Benutzerkonto Card --}}
                <div class="">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <i class="far fa-user text-sm"></i>
                            </span>
                            <span>Benutzerdetails</span>
                        </h3>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">User-ID: {{ $user->id }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-3  md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-wide text-slate-500">Benutzername</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                            @if($firstPerson)
                                <div class="mt-1 text-sm text-slate-700">{{ $firstPerson->nachname ?? '—' }}, {{ $firstPerson->vorname ?? '—' }}</div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-wide text-slate-500">Registriert am</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $user->created_at->format('d.m.Y') }}</div>
                            <div class="mt-3 border-t border-slate-200 pt-3">
                                <div class="mb-2 text-[11px] uppercase tracking-wide text-slate-500">E-Mail & Verifizierungsstatus</div>
                                <div class="min-w-0">
                                    <div class="truncate text-sm text-slate-900" title="{{ $user->email }}">{{ $user->email }}</div>
                                </div>
                                @if($user->email_verified_at)
                                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                          title="Verifiziert am: {{ $user->email_verified_at->format('d.m.Y H:i') }}">
                                        <i class="far fa-check-circle"></i>
                                        Verifiziert
                                    </span>
                                @else
                                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700"
                                          title="E-Mail nicht verifiziert">
                                        <i class="far fa-exclamation-circle"></i>
                                        Nicht verifiziert
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if($firstPerson)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 md:col-span-2">
                                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Name</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $firstPerson->nachname ?? '—' }}, {{ $firstPerson->vorname ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Person-Nr.</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $firstPerson->person_nr ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Geburtsdatum</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">
                                            {{ !empty($firstPerson->geburt_datum) ? \Carbon\Carbon::parse($firstPerson->geburt_datum)->format('d.m.Y') : '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Anschrift</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">
                                            {{ $firstPerson->strasse ?? '—' }}<br>
                                            {{ trim(($firstPerson->plz ?? '') . ' ' . ($firstPerson->ort ?? '')) ?: '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @if(auth()->user()->isAdmin())
                @if($persons->count())
                    @foreach($persons as $person)
                        <div class="relative mt-6 rounded-xl border border-gray-200 bg-gradient-to-b from-gray-50 to-gray-100 shadow-sm">
                            {{-- Header mit Update-Button --}}
                            <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                                        <i class="fad fa-user-cog text-gray-500"></i>
                                        UVS Personendetails
                                        @if($persons->count() > 1)
                                            <span class="text-xs font-normal text-gray-500">
                                                (Person-ID: {{ $person->person_id }}, Institut: {{ $person->institut_id }})
                                            </span>
                                        @endif
                                    </h3>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                        <i class="fad fa-clock"></i>
                                        {{ $person->last_api_update?->diffForHumans() ?? ($person->last_api_update ? $person->last_api_update->diffForHumans() : '—') }}
                                    </span>
                                </div>

                                <button
                                    wire:click="uvsApiUpdate({{ $person->id }})"
                                    class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                    title="UVS-Daten dieser Person aktualisieren"
                                >
                                    <i class="fad fa-sync"></i>
                                    Aktualisieren
                                </button>
                            </div>



                            {{-- Inhalt --}}
                            <div class="px-5 py-5">
                                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                    {{-- Persönliche Informationen --}}
                                    <div class="rounded-lg border border-gray-200/70 bg-white p-4">
                                        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <i class="fad fa-id-badge text-gray-500"></i>
                                            Persönliche Informationen
                                        </h4>
                                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Person-ID</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->person_id }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Institut-ID</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->institut_id }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Person-Nr.</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->person_nr }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">email_priv</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->email_priv }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</dt>
                                                <dd class="mt-1">
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200">
                                                        <i class="fad fa-badge-check"></i> {{ $person->status }}
                                                    </span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Aktualisiert am</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->upd_date }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Titel</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->titel_kennz }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Vorname</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->vorname }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Nachname</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->nachname }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geschlecht</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->geschlecht }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Nationalität</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->nationalitaet }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Familienstand</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->familien_stand }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsdatum</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->geburt_datum }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsname</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->geburt_name }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsland</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->geburt_land }}</dd>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsort</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->geburt_ort }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {{-- Adresse --}}
                                    <div class="rounded-lg border border-gray-200/70 bg-white p-4">
                                        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <i class="fad fa-map-marker-alt text-gray-500"></i>
                                            Adresse
                                        </h4>
                                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Landeskürzel</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->lkz }}</dd>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Straße</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->strasse }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Adresszusatz 1</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->adresszusatz1 }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Adresszusatz 2</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->adresszusatz2 }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->plz }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Ort</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->ort }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (Postfach)</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->plz_pf }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Postfach</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->postfach }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (Geschäftskunde)</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->plz_gk }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (alt)</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->plz_alt }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Ort (alt)</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->ort_alt }}</dd>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Straße (alt)</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $person->strasse_alt }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                        <i class="fad fa-info-circle mr-2"></i>
                        Keine Personendetails verfügbar.
                    </div>
                @endif
            @endif

            </div>
            </x-ui.accordion.tab-panel>

        <x-ui.accordion.tab-panel for="userCourses">
            @if(auth()->user()?->role === 'admin')
                <livewire:admin.user-profile.user-courses :user="$user" :key="'user-courses-'.$user->id" />
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    <i class="fad fa-info-circle mr-2"></i>
                    Dieses Modul befindet sich noch in Entwicklung.
                </div>
            @endif
        </x-ui.accordion.tab-panel>
        <x-ui.accordion.tab-panel for="userMessages">
            @if(auth()->user()?->role === 'admin')
                <livewire:admin.user-profile.user-messages :user="$user" :key="'user-messages-'.$user->id" />
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    <i class="fad fa-info-circle mr-2"></i>
                    Dieses Modul befindet sich noch in Entwicklung.
                </div>
            @endif
        </x-ui.accordion.tab-panel>
        <x-ui.accordion.tab-panel for="userRequests">
            @if(auth()->user()?->role === 'admin')
                <livewire:admin.user-profile.user-requests :user="$user" :key="'user-requests-'.$user->id" />
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    <i class="fad fa-info-circle mr-2"></i>
                    Dieses Modul befindet sich noch in Entwicklung.
                </div>
            @endif
        </x-ui.accordion.tab-panel>
    </x-ui.accordion.tabs>

    <livewire:admin.user-profile.request-detail-modal :key="'request-detail-modal-'.$user->id" />

    <livewire:admin.users.messages.message-form :key="'message-form-'.$user->id" />

</div>
