<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Course;
use Illuminate\Support\Carbon;

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
    public ?string $vtz    = null; // z.B. 'VZ', 'TZ' etc.

    /**
     * Status-Filterwerte aus dem Select:
     * '', 'active', 'inactive', 'planned', 'finished'
     */
    public ?string $active = null;

    public int $coursesTotal = 0;

    protected $listeners = [
        'openCourseSettings' => 'refreshList',
        'refreshCourses'     => 'refreshList',
        'table-sort'         => 'tableSort',
    ];

    protected $queryString = [
        'search'  => ['except' => ''],
        'sortBy'  => ['except' => 'planned_start_date'],
        'sortDir' => ['except' => 'desc'],
        'perPage' => ['except' => 15],
        'from'    => ['except' => null],
        'to'      => ['except' => null],
        'vtz'     => ['except' => null],
        'active'  => ['except' => null],
    ];

    // protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->coursesTotal = Course::count();
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['search','from','to','vtz','active','sortBy','sortDir','perPage'], true)) {
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

        // ⚠️ Wenn deine Personen-Tabelle "people" heißt, hier anpassen.
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

        /**
         * Status-Filter laut Select:
         * - active:     Start <= jetzt AND (Ende >= jetzt OR Ende IS NULL)
         * - planned:    Start > jetzt
         * - finished:   Ende < jetzt (nur wenn Ende vorhanden)
         * - inactive:   is_active = false
         * - '' | null:  kein Filter
         */
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

            default:
                // kein Statusfilter
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
            $q->orderByRaw("CONCAT(COALESCE(tutor.nachname,''), ', ', COALESCE(tutor.vorname,'')) " . ($this->sortDir === 'desc' ? 'DESC' : 'ASC'));
        } else {
            $q->orderBy($key, $this->sortDir === 'desc' ? 'desc' : 'asc');
        }

        return $q;
    }

    public function render()
    {
        $courses = $this->baseQuery()->paginate($this->perPage);

        return view('livewire.admin.courses.course-list', compact('courses'))
            ->layout('layouts.master');
    }
}
