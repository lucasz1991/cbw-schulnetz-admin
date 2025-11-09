<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="text-center px-4 py-2 text-xl font-semibold hover:bg-gray-100 rounded-lg">
            &#x22EE;
        </button>
    </x-slot>

    <x-slot name="content">
        {{-- Details: normale Navigation --}}
        <x-dropdown-link href="{{ route('admin.courses.show', $item) }}" >
            Details
        </x-dropdown-link>
        <x-dropdown-link href="#" wire:click.prevent="exportCourse({{ $item->id }})" class="hover:bg-green-100">
            Exportieren
        </x-dropdown-link>
    </x-slot>
</x-dropdown>
