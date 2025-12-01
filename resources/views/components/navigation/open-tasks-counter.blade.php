@php
    $count = \App\Models\AdminTask::where('status', \App\Models\AdminTask::STATUS_OPEN)->count();
@endphp

@if($count > 0)
    <span class="absolute -right-2 -top-1 rounded-full aspect-square bg-red-400 px-1.5 py-0.2 flex justify-center items-center text-xs text-white" title="Offene Aufgaben">
        {{ $count }}
    </span>
@endif
