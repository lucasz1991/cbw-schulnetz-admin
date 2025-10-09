<div class="px-2">
    <div class="flex justify-between mb-4">
        <x-slot name="header">
            <x-slot name="title">Kursliste</x-slot>
        </x-slot>

        <div class="flex items-center space-x-2">
            {{-- Titel + Gesamtzähler --}}
            <h1 class="flex items-center text-lg font-semibold px-2 py-1">
                <span>Kurse</span>
                <span class="ml-2 bg-white text-sky-600 text-xs shadow border border-sky-200 font-bold px-2 py-1 flex items-center justify-center rounded-full h-7 leading-none">
                    {{ $coursesTotal }}
                </span>
            </h1>

            {{-- Suchfeld --}}
            <x-tables.search-field 
                resultsCount="{{ $courses->count() }}"
                wire:model.live="search"
            />

            {{-- 🟢 Status-Filter --}}
            <div class="relative">
                <select 
                    wire:model.live="active"
                    class="text-base border border-gray-300 rounded-lg px-2 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                >
                    <option value="">alle</option>
                    <option value="active">aktive</option>
                    <option value="inactive">inaktive</option>
                    <option value="planned">geplante</option>
                    <option value="finished">abgeschlossene</option>
                </select>
            </div>

            {{-- (Optional: Später weitere Filter hier ergänzen) --}}
        </div>

        {{-- + Button --}}
        <x-link-button 
            @click="$dispatch('open-course-create-edit')" 
            class="btn-xs py-0 leading-[0]"
        >+</x-link-button>
    </div>

    <div class="w-full">
        <x-tables.table
            :columns="[
                ['label'=>'Titel','key'=>'title','width'=>'28%','sortable'=>true,'hideOn'=>'none'],
                ['label'=>'Tutor','key'=>'tutor_name','width'=>'20%','sortable'=>true,'hideOn'=>'md'],
                ['label'=>'Zeitraum','key'=>'planned_start_date','width'=>'22%','sortable'=>true,'hideOn'=>'xl'],
                ['label'=>'Status','key'=>'is_active','width'=>'12%','sortable'=>true,'hideOn'=>'md'],
                ['label'=>'Aktivitäten','key'=>'activity','width'=>'18%','sortable'=>false,'hideOn'=>'md'],
            ]"
            :items="$courses"
            row-view="components.tables.rows.courses.course-row"
            actions-view="components.tables.rows.courses.course-actions"
            :sort-by="$sortBy ?? null"
            :sort-dir="$sortDir ?? 'asc'"
        />

        <div class="py-4">
            {{ $courses->links() }}
        </div>

        @livewire('admin.courses.course-create-edit')
    </div>
</div>
