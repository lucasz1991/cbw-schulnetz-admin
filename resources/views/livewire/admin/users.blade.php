<div x-data="{ selectedUsers: @entangle('selectedUsers'), search: @entangle('search'), hasUsers: @entangle('hasUsers') }"  wire:loading.class="cursor-wait">
    @persist('scrollbar')
    <div class="mb-4 flex flex-wrap  justify-between gap-4">
        <div class="mb-6 max-w-md">
            <h1 class="text-2xl font-bold text-gray-800">Benutzer</h1>
            <p class="text-gray-500">Es gibt insgesamt {{ $users->total() }} Benutzer.</p>
        </div>
        <div class="">
            <livewire:admin.charts.active-users :height="150"/>
        </div>
    </div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            {{-- Suchfeld --}}
            <x-tables.search-field 
                resultsCount="{{ $users->count() }}"
                wire:model.live="search"
            />

            {{-- 🟢 Status-Filter --}}
            <div class="relative">
                <select 
                    wire:model.live="userTypeFilter"
                    class="text-base border border-gray-300 rounded-lg px-2 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                >
                    <option value="">alle</option>
                    <option value="tutor">Dozenten</option>
                    <option value="guest">Teilnehmer</option> 
                </select>
            </div>
        </div>
        <div class="mt-4 relative">
            @php
                $isDisabled = count($selectedUsers) === 0;
                $buttonClass = $isDisabled
                    ? 'cursor-not-allowed opacity-50 bg-gray-100 text-sm border px-3 py-1 rounded relative flex items-center justify-center'
                    : 'cursor-pointer bg-gray-100 text-sm border px-3 py-1 rounded relative flex items-center justify-center';
            @endphp

            @if($isDisabled)
                <x-actionbutton class="{{ $buttonClass }}">
                    <svg class="w-4 h-4 text-gray-600 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/>
                    </svg>
                </x-actionbutton>
            @else
                <x-ui.dropdown.anchor-dropdown
                    align="right"
                    width="48"
                    dropdownClasses="mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                    contentClasses="bg-white py-1"
                    :overlay="false"
                    :trap="false"
                    :scrollOnOpen="false"
                    :offset="6"
                >
                    <x-slot name="trigger">
                        <x-actionbutton class="{{ $buttonClass }}">
                            <svg class="w-4 h-4 text-gray-600 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.005 11.19V12l6.998 4.042L19 12v-.81M5 16.15v.81L11.997 21l6.998-4.042v-.81M12.003 3 5.005 7.042l6.998 4.042L19 7.042 12.003 3Z"/>
                            </svg>
                            <span class="ml-2 bg-yellow-400 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ count($selectedUsers) }}
                            </span>
                        </x-actionbutton>
                    </x-slot>

                    <x-slot name="content">
                        <x-ui.dropdown.dropdown-link wire:click="activateUsers" class="hover:bg-green-100 focus:bg-green-100">
                            <i class="far fa-check-circle mr-2"></i>
                            Aktivieren
                        </x-ui.dropdown.dropdown-link>
                        <x-ui.dropdown.dropdown-link wire:click="deactivateUsers" class="hover:bg-yellow-100 focus:bg-yellow-100">
                            <i class="far fa-times-circle mr-2"></i>
                            Deaktivieren
                        </x-ui.dropdown.dropdown-link>
                        <x-ui.dropdown.dropdown-link wire:click="openMailModal" class="hover:bg-blue-100 focus:bg-blue-100">
                            <i class="far fa-envelope mr-2"></i>
                            Nachricht senden
                        </x-ui.dropdown.dropdown-link>
                        @if(auth()->user()->isAdmin())
                            <x-ui.dropdown.dropdown-link wire:click="apiUpdateUsers" class="hover:bg-red-100 focus:bg-red-100">
                                <i class="far fa-sync-alt mr-2"></i>
                                UVS Api Update
                            </x-ui.dropdown.dropdown-link>
                        @endif
                    </x-slot>
                </x-ui.dropdown.anchor-dropdown>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-12 bg-gray-100 p-2 font-semibold text-gray-700 border-b border-gray-300">
      
        <div class="col-span-5 flex items-center">
            <x-button 
                wire:click="toggleSelectAll" 
                class="btn-xs mr-3"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
            </svg>
            </x-button>
            <button wire:click="sortByField('name')" class="text-left flex items-center">
                Name
                @if ($sortBy === 'name')
                    <span class="ml-2 text-xl"
                        style="display: inline-block;">
                        <svg class="w-4 h-4 ml-2  transition-transform transform" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }}); transition: transform 0.3s ease;" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                        </svg>
                    </span>
                @endif
            </button>
        </div>
        <div class="col-span-4 flex items-center">
            <button wire:click="sortByField('email')" class="text-left flex items-center">
                E-Mail
                @if ($sortBy === 'email')
                    <span class="ml-2 text-xl"
                        style="display: inline-block;">
                        <svg class="w-4 h-4 ml-2  transition-transform transform" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }}); transition: transform 0.3s ease;" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                        </svg>
                    </span>
                @endif
            </button>
        </div>
        <div class="col-span-3 flex items-center">
            <button wire:click="sortByField('created_at')" class="text-left flex items-center">
                Registriert am
                @if ($sortBy === 'created_at')
                    <span class="ml-2 text-xl"
                        style="display: inline-block;">
                        <svg class="w-4 h-4 ml-2  transition-transform transform" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }}); transition: transform 0.3s ease;" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                        </svg>
                    </span>
                @endif
            </button>
        </div>
    </div>
    <div>
        @foreach ($users as $user)
            @php
                $lastName = $user->person->nachname ?? '';
                $firstName = $user->person->vorname ?? '';
                $personDisplayName = trim($lastName . ', ' . $firstName, ' ,');
                $displayName = $personDisplayName !== '' ? $personDisplayName : ($user->name ?? '-');
                $legacyUserName = $user->name ?? '';
                $lastActivityDateTime = $user->lastActivityDateTime();
            @endphp
            <div class="grid grid-cols-12 items-center p-2 border text-left hover:bg-blue-100 text-sm">
                <div class="col-span-5 font-bold pl-1 cursor-pointer flex items-center justify-between"  wire:click="toggleUserSelection({{ $user->id }})" x-on:dblclick="window.location='{{ route('admin.user-profile', ['userId' => $user->id]) }}'">
                    <div class="flex items-center space-x-4">
                        <img class="h-10 w-10 rounded-full object-cover transition-all duration-300 {{ in_array($user->id, $selectedUsers) ? 'ring-4 ring-green-300' : '' }}" 
                        src="{{ $user->baseProfilePhotoUrl }}" alt="{{ $displayName }}" />
                        <div>
                            <div class="text-sm font-medium">
                                {{ $displayName }}
                            </div>
                            @if($legacyUserName !== '' && $legacyUserName !== $displayName)
                                <div class="text-xs text-gray-400">
                                    {{ $legacyUserName }}
                                </div>
                            @endif
                        </div>
                        @if($user->isOnline())
                            <span class="h-2 w-2 rounded-full bg-green-300" title="Online"></span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-red-300" title="Offline"></span>
                        @endif
                        <div class="text-xs font-medium text-gray-500">{{ $lastActivityDateTime ? $lastActivityDateTime->diffForHumans() : 'Vor langer Zeit' }}
                </div>
                    </div>
                    <div class="mx-5  text-gray-600 text-xs px-2 py-0.5 rounded-full {{ ucfirst($user->role) == 'Tutor' ? 'bg-blue-100' : 'bg-green-100' }}">
                        <span class="text-xs font-normal {{ ucfirst($user->role) == 'Tutor' ? 'text-blue-600' : 'text-green-600' }}">{{ ucfirst($user->role) == 'Tutor' ? 'Dozent' : 'Teilnehmer' }}</span>
                    </div>
                </div>
                <div class="col-span-4 cursor-pointer flex items-center space-x-2" wire:click="toggleUserSelection({{ $user->id }})">
                    <span>{{ $user->email }}</span>
                    @if($user->email_verified_at)
                        <span 
                            class="h-2 w-2 rounded-full bg-green-300" 
                            title="Verifiziert am: {{ $user->email_verified_at->format('d.m.Y H:i') }}">
                        </span>
                    @else
                        <span 
                            class="h-2 w-2 rounded-full bg-red-300" 
                            title="E-Mail nicht verifiziert">
                        </span>
                    @endif
                </div>

                <div 
                    class="col-span-2 text-gray-600 cursor-pointer" 
                    wire:click="toggleUserSelection({{ $user->id }})" 
                    title="{{ $user->created_at->format('d.m.Y H:i') }}">
                    {{ $user->created_at->format('d.m.Y') }}
                </div>
                <div class="col-span-1 text-gray-600 relative">
                    <!-- Status-Punkt -->
                    <div class="flex items-center space-x-2 justify-between">
                        <span title="{{ $user->status ? 'Aktiv' : 'Inaktiv' }}" class="h-4 w-4 rounded-full flex items-center justify-center {{ $user->status ? 'bg-green-400' : 'bg-red-400' }}" >    
                            @if ($user->status)
                                <!-- SVG für Aktiv (Haken) -->
                                <svg 
                                    xmlns="http://www.w3.org/2000/svg" 
                                    class="h-3 w-3 text-white" 
                                    fill="none" 
                                    viewBox="0 0 24 24" 
                                    stroke-width="4" 
                                    stroke="currentColor"
                                >
                                    <path 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        d="M5 13l4 4L19 7" 
                                    />
                                </svg>
                            @else
                                <!-- SVG für Inaktiv (X) -->
                                <svg 
                                    xmlns="http://www.w3.org/2000/svg" 
                                    class="h-3 w-3 text-white" 
                                    fill="none" 
                                    viewBox="0 0 24 24" 
                                    stroke-width="4" 
                                    stroke="currentColor"
                                >
                                    <path 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        d="M6 18L18 6M6 6l12 12" 
                                    />
                                </svg>
                            @endif

                        </span>
                        <!-- Dropdown -->
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
                                <button type="button" class="text-gray-500 hover:text-gray-800 transition duration-200 scale-100 hover:scale-120 hover:bg-gray-100 focus:bg-gray-100 p-2 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-ui.dropdown.dropdown-link as="a" href="{{ route('admin.user-profile', ['userId' => $user->id]) }}">
                                    <i class="far fa-user mr-2"></i>
                                    Profil
                                </x-ui.dropdown.dropdown-link>

                                @if ($user->status)
                                    <x-ui.dropdown.dropdown-link wire:click="deactivateUser({{ $user->id }})" class="hover:bg-yellow-100 focus:bg-yellow-100">
                                        <i class="far fa-times-circle mr-2"></i>
                                        Deaktivieren
                                    </x-ui.dropdown.dropdown-link>
                                @else
                                    <x-ui.dropdown.dropdown-link wire:click="activateUser({{ $user->id }})" class="hover:bg-green-100 focus:bg-green-100">
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
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-4">
    {{ $users->links() }}
    </div>
    @endpersist
    <livewire:admin.users.messages.message-form />
</div>

