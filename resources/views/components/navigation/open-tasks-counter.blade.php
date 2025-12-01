@php
    $count = \App\Models\AdminTask::open()->count();
@endphp

@if($count > 0)
    <span class="ml-2 inline-flex items-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
        {{ $count }}
    </span>
@endif
