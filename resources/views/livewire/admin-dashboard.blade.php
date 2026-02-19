<div class="space-y-5" wire:loading.class="cursor-wait"
     @if($autoRefresh) wire:poll.60s="$refresh" @endif>

    {{-- Header (funktional, ohne Titel/Text) --}}
    <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        {{-- Soft background --}}
        <div class="absolute inset-0 bg-gradient-to-r from-slate-50 via-white to-indigo-50"></div>
        <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-indigo-100/60 blur-2xl"></div>
        <div class="absolute -bottom-20 -left-16 w-64 h-64 rounded-full bg-sky-100/70 blur-2xl"></div>

        <div class="relative p-3 md:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

                {{-- LEFT: KPI-Chips (Platzhalter bis Widget fertig) --}}
                <div class="min-w-0">
                    {{-- <livewire:admin.dashboard.header-kpis :autoRefresh="$autoRefresh" /> --}}
                    <div class="flex flex-wrap items-center gap-2">

                        {{-- Chip --}} 
                        <div class="group inline-flex items-center gap-2.5 px-3 py-2 rounded-full border border-gray-200 bg-white/80 shadow-sm hover:bg-white transition">
                            <span class="w-9 h-9 rounded-full bg-teal-50 border border-teal-100 flex items-center justify-center">
                                <i class="fal fa-user-plus text-teal-600"></i>
                            </span>
                            <div class="leading-tight">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Neue (Monat)</div>
                                <div class="text-sm font-semibold text-gray-900">—</div>
                            </div>
                        </div>

                        {{-- Chip --}}
                        <div class="group inline-flex items-center gap-2.5 px-3 py-2 rounded-full border border-gray-200 bg-white/80 shadow-sm hover:bg-white transition">
                            <span class="w-9 h-9 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center">
                                <i class="fal fa-user-clock text-rose-600"></i>
                            </span>
                            <div class="leading-tight">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Fehlzeiten heute</div>
                                <div class="text-sm font-semibold text-gray-900">—</div>
                            </div>
                        </div>

                        {{-- (wenn du später 2 weitere brauchst: Offene Anfragen / Kurse heute) --}}
                    </div>
                </div>

                {{-- RIGHT: Actions --}}
                <div class="flex flex-wrap items-center gap-2 md:justify-end">

                    <a href="{{ route('courses.index') ?? '#' }}"
                       class="group inline-flex items-center gap-2.5 px-3 py-2 text-sm rounded-full border border-gray-200 bg-white/80 hover:bg-white shadow-sm transition">
                        <span class="w-9 h-9 rounded-full bg-sky-50 border border-sky-100 flex items-center justify-center">
                            <i class="fal fa-chalkboard-teacher text-sky-600"></i>
                        </span>
                        <span class="font-medium text-gray-800">Bausteine</span>
                    </a>

                    {{-- Auto refresh toggle --}}
                    <button wire:click="toggleAutoRefresh"
                            class="group inline-flex items-center gap-2.5 px-3 py-2 text-sm rounded-full border border-gray-200 bg-white/80 hover:bg-white shadow-sm transition">
                        <span class="w-9 h-9 rounded-full {{ $autoRefresh ? 'bg-emerald-50 border-emerald-100' : 'bg-gray-50 border-gray-100' }} border flex items-center justify-center">
                            <i class="fal {{ $autoRefresh ? 'fa-sync fa-spin text-emerald-600' : 'fa-sync text-gray-500' }}"></i>
                        </span>
                        <span class="font-medium text-gray-800">Refresh</span>
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border
                            {{ $autoRefresh ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-gray-600 border-gray-200' }}">
                            {{ $autoRefresh ? 'an' : 'aus' }}
                        </span>
                    </button>

                    {{-- Settings (/config) --}}
                    <a href="{{ url('/config') }}"
                       class="group inline-flex items-center gap-2.5 px-3 py-2 text-sm rounded-full border border-gray-200 bg-white/80 hover:bg-white shadow-sm transition">
                        <span class="w-9 h-9 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center">
                            <i class="fal fa-cog text-gray-600"></i>
                        </span>
                        <span class="font-medium text-gray-800">Einstellungen</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- LEFT: Hauptarbeit (2 Spalten) --}}
        <div class="xl:col-span-2 space-y-4">

            {{-- Kurse-Fokus --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 md:p-5 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center">
                                <i class="fal fa-calendar-alt text-sky-700"></i>
                            </span>
                            <p class="font-semibold text-gray-900">Kurse</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-sky-50 text-sky-700 border border-sky-100">
                                laufend & kommende
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-4 md:px-5 pb-4 md:pb-5">
                    <livewire:admin.dashboard.courses-focus :autoRefresh="$autoRefresh" wire:key="dashboard-courses-focus" />
                </div>
            </div>

            {{-- Offene Jobs --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 md:p-5 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                                <i class="fal fa-list-check text-emerald-700"></i>
                            </span>
                            <p class="font-semibold text-gray-900">Offene Jobs</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700 border border-emerald-100">
                                Arbeits-Inbox
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-4 md:px-5 pb-4 md:pb-5">
                    <livewire:admin.dashboard.open-jobs :autoRefresh="$autoRefresh" wire:key="dashboard-open-jobs" />
                </div>
            </div>

        </div>

        {{-- RIGHT: Nebeninfos / Analytics --}}
        <div class="space-y-4">

            {{-- Warnungen / Hinweise --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-4 md:p-5 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-orange-50 border border-orange-100 flex items-center justify-center">
                                <i class="fal fa-exclamation-triangle text-orange-700"></i>
                            </span>
                            <p class="font-semibold text-gray-900">Hinweise & Warnungen</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-orange-50 text-orange-700 border border-orange-100">
                                handlungsrelevant
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-4 md:px-5 pb-4 md:pb-5">
                    <livewire:admin.dashboard.alerts :autoRefresh="$autoRefresh" wire:key="dashboard-alerts" />
                </div>
            </div>

            {{-- Aktive Nutzer --}}
            @if($canSeeAnalytics)
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="p-4 md:p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                                    <i class="fal fa-chart-line text-indigo-700"></i>
                                </span>
                                <p class="font-semibold text-gray-900">Aktive Nutzer</p>
                            </div>
                            <span class="text-[11px] px-2 py-0.5 rounded-full border border-indigo-100 bg-indigo-50 text-indigo-700">
                                letzte Stunden
                            </span>
                        </div>

                        <div class="mt-4">
                            <livewire:admin.charts.active-users :height="220" />
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
