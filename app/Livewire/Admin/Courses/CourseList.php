<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use Illuminate\Support\Collection;
use App\Services\ApiUvs\ApiUvsService;
use App\Support\ApiCourse;

class CourseList extends Component
{
    public string $search   = '';
    public string $sortBy   = 'title';
    public string $sortDir  = 'asc';

    // Optionale Datums-Filter (passen zum Endpoint)
    public ?string $from    = null; // 'Y-m-d'
    public ?string $to      = null; // 'Y-m-d'

    // Meta
    public int $coursesTotal = 0;
    public ?string $error    = null;

    // Lazy/Init-State
    public bool $ready = false;

    protected $listeners = [
        'openCourseSettings' => 'refreshList',
        'refreshCourses'     => 'refreshList',
        'table-sort'         => 'tableSort',
    ];

    protected $queryString = ['search', 'from', 'to'];

    public function mount(): void
    {
        // Absichtlich nichts laden -> placeholder() rendert auf dem Server
        // Erst nach Client-Init (wire:init) wird $ready=true gesetzt und geladen.
        $this->ready = false;
    }

    /** Wird durch wire:init im Blade aufgerufen */
    public function load(): void
    {
        $this->ready = true;
        $this->refreshList();
    }

    public function updatedSearch()   { $this->refreshList(); }
    public function updatingFrom()    { $this->refreshList(); }
    public function updatingTo()      { $this->refreshList(); }

    public function tableSort($key, $dir): void
    {
        $this->sortBy  = $key;
        $this->sortDir = $dir;
        $this->refreshList();
    }

    public function refreshList(): void
    {
        // Kein resetPage nötig; eigentlicher Load passiert im render() wenn $ready=true
        // Hier nur Trigger/Neuberechnung anstoßen (Livewire ruft render() ohnehin auf).
    }

    public function render()
    {
        // Solange nicht "ready": nichts laden -> placeholder() bleibt sichtbar.
        if (!$this->ready) {
            $this->error        = null;
            $this->coursesTotal = 0;

            return view('livewire.admin.courses.course-list', [
                'courses' => collect(),
            ])->layout('layouts.master');
        }

        // Ab hier: tatsächlicher API-Load
        [$items, $error] = $this->fetchFromApi();

        $this->error        = $error;
        $this->coursesTotal = $items->count();

        // Sort client-seitig
        $courses = $this->applySort($items);

        return view('livewire.admin.courses.course-list', compact('courses'))
            ->layout('layouts.master');
    }

    /** API call + Grundvalidierung */
    protected function fetchFromApi(): array
    {
        /** @var ApiUvsService $api */
        $api = app(ApiUvsService::class);

        $resp = $api->getCourseClasses(
            search: $this->search ?: null,
            limit:  500, // großzügig, da wir alles holen wollen
            from:   $this->from,
            to:     $this->to,
            sort:   $this->sortBy === 'title' ? 'bezeichnung' : null,
            order:  $this->sortDir === 'desc' ? 'desc' : 'asc'
        );

        if (!($resp['ok'] ?? false)) {
            \Log::warning('API-Fehler API response: ', $resp);
            return [collect(), $resp['message'] ?? 'API-Fehler beim Laden der Kurse'];
        }

        $rows = collect(data_get($resp, 'data.data', data_get($resp, 'data', [])));

        // -> in Objekte hydrieren
        $objects = $rows->map(fn ($r) => new ApiCourse((array)$r));

        return [$objects, null];
    }

    /** Sortierlogik auf Collection */
    protected function applySort(Collection $items): Collection
    {
        $allowed = [
            'title',
            'short',
            'id',
            'start_time',
            'end_time',
            'participants_count',
            'teachers_count',
        ];

        $sortBy = in_array($this->sortBy, $allowed, true) ? $this->sortBy : 'title';
        $dir    = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $items = $items->sortBy(function ($row) use ($sortBy) {
            $val = $row->{$sortBy} ?? null;

            // Dates: Carbon bevorzugen
            if (in_array($sortBy, ['start_time', 'end_time'], true)) {
                if ($val instanceof \Illuminate\Support\Carbon) return $val->getTimestamp();
                if (is_string($val)) return strtotime($val) ?: 0;
                return 0;
            }

            // Counts numerisch
            if (in_array($sortBy, ['participants_count', 'teachers_count'], true)) {
                return (int)$val;
            }

            return mb_strtolower((string)$val);
        }, SORT_REGULAR, $dir === 'desc');

        return $items->values();
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="space-y-8 py-8 animate-pulse md:flex md:items-center md:space-x-8 w-full">
                <div class="w-full space-y-2">
                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                </div>
            </div>
        HTML;
    }
}
