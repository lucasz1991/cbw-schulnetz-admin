<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class CourseList extends Component
{
    use WithPagination;

    // Such-/Sortier-/Paging
    public string $search  = '';
    public string $sortBy  = 'planned_start_date';
    public string $sortDir = 'desc';
    public int    $perPage = 15;

    // Filter
    public ?string $from   = null; // Y-m-d
    public ?string $to     = null; // Y-m-d
    public ?string $vtz    = null; // 'VZ', 'TZ', …
    /**
     * Status-Filterwerte:
     * '', 'active', 'inactive', 'planned', 'finished'
     */
    public ?string $active = null;

    // Termin-Filter (STRING!)
    public ?string $selectedTerm = null;

    public int $coursesTotal = 0;

    // Für das Select in der View
    public $terms = [];

    protected $listeners = [
        'openCourseSettings' => 'refreshList',
        'refreshCourses'     => 'refreshList',
        'table-sort'         => 'tableSort',
    ];

    protected $queryString = [
        'search'       => ['except' => ''],
        'sortBy'       => ['except' => 'planned_start_date'],
        'sortDir'      => ['except' => 'desc'],
        'perPage'      => ['except' => 15],
        'from'         => ['except' => null],
        'to'          => ['except' => null],
        'vtz'         => ['except' => null],
        'active'      => ['except' => null],
        'selectedTerm'=> ['except' => null],
    ];

    // protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->coursesTotal = Course::count();
        $this->terms = $this->loadTermOptionsFromCourses();
    }

    public function updated($prop): void
    {
        if (in_array($prop, [
            'search','from','to','vtz','active','sortBy','sortDir','perPage','selectedTerm'
        ], true)) {
            $this->resetPage();
        }
    }

    public function tableSort($key, $dir): void
    {
        $this->sortBy  = $key;
        $this->sortDir = $dir === 'desc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    protected function baseQuery()
    {
        $now = Carbon::now();

        $q = Course::query()
            ->leftJoin('persons as tutor', 'tutor.id', '=', 'courses.primary_tutor_person_id')
            ->select('courses.*')
            ->with(['tutor' => function ($q) {
                $q->select('id', 'vorname', 'nachname');
            }]);

        // Suche
        if ($this->search !== '') {
            $s = '%' . str_replace(' ', '%', $this->search) . '%';
            $q->where(function ($w) use ($s) {
                $w->where('courses.title', 'like', $s)
                  ->orWhere('courses.klassen_id', 'like', $s)
                  ->orWhere('courses.termin_id', 'like', $s)
                  ->orWhere('courses.room', 'like', $s)
                  ->orWhereRaw("CONCAT(COALESCE(tutor.vorname,''),' ',COALESCE(tutor.nachname,'')) LIKE ?", [$s]);
            });
        }

        // Zeitraumfilter (geplant)
        if ($this->from) {
            $q->whereDate('courses.planned_start_date', '>=', $this->from);
        }
        if ($this->to) {
            $q->whereDate('courses.planned_end_date', '<=', $this->to);
        }

        // VTZ-Filter
        if ($this->vtz) {
            $q->where('courses.vtz', $this->vtz);
        }

        // Termin-Filter (STRING-Vergleich)
if (!empty($this->selectedTerm)) {
    $q->where('courses.termin_id', '=', $this->selectedTerm);
}

        // Status-Filter
        switch ($this->active) {
            case 'active':
                $q->where(function ($w) use ($now) {
                    $w->whereDate('courses.planned_start_date', '<=', $now->toDateString())
                      ->where(function ($x) use ($now) {
                          $x->whereNull('courses.planned_end_date')
                            ->orWhereDate('courses.planned_end_date', '>=', $now->toDateString());
                      });
                });
                break;
            case 'planned':
                $q->whereDate('courses.planned_start_date', '>', $now->toDateString());
                break;
            case 'finished':
                $q->whereNotNull('courses.planned_end_date')
                  ->whereDate('courses.planned_end_date', '<', $now->toDateString());
                break;
            case 'inactive':
                $q->where('courses.is_active', false);
                break;
        }

        // Sortier-Whitelist
        $allowed = [
            'title'              => 'courses.title',
            'created_at'         => 'courses.created_at',
            'updated_at'         => 'courses.updated_at',
            'planned_start_date' => 'courses.planned_start_date',
            'planned_end_date'   => 'courses.planned_end_date',
            'is_active'          => 'courses.is_active',
            'tutor_name'         => null, // special case
        ];

        $key = $allowed[$this->sortBy] ?? 'courses.planned_start_date';

        if ($this->sortBy === 'tutor_name') {
            $q->orderByRaw(
                "CONCAT(COALESCE(tutor.nachname,''), ', ', COALESCE(tutor.vorname,'')) " .
                ($this->sortDir === 'desc' ? 'DESC' : 'ASC')
            );
        } else {
            $q->orderBy($key, $this->sortDir === 'desc' ? 'desc' : 'asc');
        }

        return $q;
    }

    public function render()
    {
        $courses = $this->baseQuery()->paginate($this->perPage);

        return view('livewire.admin.courses.course-list', [
            'courses' => $courses,
            'terms'   => $this->terms,
        ])->layout('layouts.master');
    }

    /**
     * Lädt Optionen für das Termin-Select ausschließlich aus Course->termin_id (STRING).
     * Rückgabe: Collection/Array von Objekten: { id: string, name: string }
     * Label inkl. Count, Sortierung lexikografisch nach termin_id.
     */
protected function loadTermOptionsFromCourses()
{
    return \App\Models\Course::query()
        ->whereNotNull('termin_id')
        ->groupBy('termin_id')
        ->orderBy('termin_id', 'asc')
        ->selectRaw('termin_id, COUNT(*) AS cnt')
        ->get()
        ->map(fn($row) => (object)[
            'id'   => (string) $row->termin_id,
            'name' => (string) $row->termin_id.' ('.$row->cnt.')',
        ]);
}

}
