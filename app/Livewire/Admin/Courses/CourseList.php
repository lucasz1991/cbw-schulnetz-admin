<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseList extends Component
{
    use WithPagination;

    // Such-/Sortier-/Paging
    public string $search  = '';
    public string $sortBy  = 'planned_start_date';
    public string $sortDir = 'desc';
    public int    $perPage = 15;

    // Filter
    public ?string $from   = '2020-01-01'; // Y-m-d
    public ?string $to     = null; // Y-m-d
    public ?string $vtz    = null; // 'VZ', 'TZ', …
    /**
     * Status-Filterwerte:
     * '', 'active', 'inactive', 'planned', 'finished'
     */
    public ?string $active = null;

    // Termin-Filter (STRING!)
    public ?string $selectedTerm = null;

    public ?string $contentFilter = null;


    public int $coursesTotal = 0;

    // Für das Select in der View
    public $terms = [];

    public $selectedCourses = [];

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
    'to'           => ['except' => null],
    'vtz'          => ['except' => null],
    'active'       => ['except' => null],
    'selectedTerm' => ['except' => null],
    'contentFilter'=> ['except' => null], // ← neu
];

    // protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->coursesTotal = Course::count();
        $this->terms = $this->loadTermOptionsFromCourses();
    }

// updated()-Liste erweitern, damit Paginator resettet
public function updated($prop): void
{
    if (in_array($prop, [
        'search','from','to','vtz','active','sortBy','sortDir','perPage','selectedTerm','contentFilter'
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
    $now   = Carbon::now();
    $today = $now->toDateString();

    // defensiv: nur filtern, wenn Property existiert
    $contentFilter = property_exists($this, 'contentFilter') ? ($this->contentFilter ?? null) : null;

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

    
    $q->whereDate('courses.planned_start_date', '>=', $this->from);
    
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

    // Status-Filter (Zeitstatus)
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

    // Inhalts-Status-Filter (Allgemein / Dokumentation / Roter Faden / Bestätigungen / Rechnung)
    if (!empty($contentFilter)) {
        switch ($contentFilter) {

            // ============================================
            // 🔹 Allgemein (Kombination aller Inhalts-Bereiche)
            // ============================================
            case 'all_ok':
                // Dokumentation vollständig + roter Faden vorhanden + alle bestätigt
                $q
                // Doku: KEIN vergangener Tag ohne Notes
                ->whereRaw("
                    NOT EXISTS (
                        SELECT 1
                        FROM course_days
                        WHERE course_days.course_id = courses.id
                          AND course_days.date <= ?
                          AND (course_days.notes IS NULL OR course_days.notes = '')
                    )
                ", [$today])
                // Roter Faden: vorhanden
                ->whereExists(function ($s) {
                    $s->select(DB::raw(1))
                      ->from('files')
                      ->where('files.fileable_type', \App\Models\Course::class)
                      ->whereColumn('files.fileable_id', 'courses.id')
                      ->where('files.type', 'roter_faden');
                })
                // Bestätigungen: count(acks) == count(teilnehmer)
                ->whereRaw("
                    (
                        SELECT COUNT(DISTINCT cpe.person_id)
                        FROM course_participant_enrollments cpe
                        WHERE cpe.course_id = courses.id
                          AND cpe.is_active = 1
                          AND cpe.deleted_at IS NULL
                    ) = (
                        SELECT COUNT(DISTINCT cmaa.person_id)
                        FROM course_material_acknowledgements cmaa
                        WHERE cmaa.course_id = courses.id
                          AND cmaa.acknowledged_at IS NOT NULL
                    )
                ");
                break;

            case 'all_missing':
                // Mindestens EIN Bereich fehlt (Doku fehlt ODER roter Faden fehlt ODER 0 Acks)
                $q->where(function ($sub) use ($today) {
                    $sub
                        // Doku fehlt: es gibt mind. einen vergangenen Tag ohne Notes
                        ->whereExists(function ($s) use ($today) {
                            $s->select(DB::raw(1))
                              ->from('course_days')
                              ->whereColumn('course_days.course_id', 'courses.id')
                              ->whereDate('course_days.date', '<=', $today)
                              ->where(function ($w) {
                                  $w->whereNull('course_days.notes')
                                    ->orWhere('course_days.notes', '=' , '');
                              });
                        })
                        // ODER roter Faden fehlt
                        ->orWhereNotExists(function ($s) {
                            $s->select(DB::raw(1))
                              ->from('files')
                              ->where('files.fileable_type', \App\Models\Course::class)
                              ->whereColumn('files.fileable_id', 'courses.id')
                              ->where('files.type', 'roter_faden');
                        })
                        // ODER 0 Bestätigungen vorhanden
                        ->orWhereRaw("
                            (
                                SELECT COUNT(DISTINCT cmaa.person_id)
                                FROM course_material_acknowledgements cmaa
                                WHERE cmaa.course_id = courses.id
                                  AND cmaa.acknowledged_at IS NOT NULL
                            ) = 0
                        ");
                });
                break;

            case 'all_partial':
                // Teilweise vorhanden/fehlend:
                // (a) Dokumentation teilweise ODER
                // (b) roter Faden vorhanden, aber Acks nicht vollständig (zwischen 1 und n-1)
                $q->where(function ($sub) use ($today) {
                    $sub
                        // (a) Doku teilweise: es gibt vergangene Tage MIT und OHNE Notes
                        ->where(function ($s) use ($today) {
                            $s->whereExists(function ($q1) use ($today) {
                                $q1->select(DB::raw(1))
                                   ->from('course_days')
                                   ->whereColumn('course_days.course_id', 'courses.id')
                                   ->whereDate('course_days.date', '<=', $today)
                                   ->whereNotNull('course_days.notes')
                                   ->where('course_days.notes', '!=', '');
                            })
                            ->whereExists(function ($q2) use ($today) {
                                $q2->select(DB::raw(1))
                                   ->from('course_days')
                                   ->whereColumn('course_days.course_id', 'courses.id')
                                   ->whereDate('course_days.date', '<=', $today)
                                   ->where(function ($w) {
                                       $w->whereNull('course_days.notes')
                                         ->orWhere('course_days.notes', '=' , '');
                                   });
                            });
                        })
                        // (b) roter Faden vorhanden, aber Acks nur teilweise
                        ->orWhere(function ($s) {
                            $s->whereExists(function ($r) {
                                $r->select(DB::raw(1))
                                  ->from('files')
                                  ->where('files.fileable_type', \App\Models\Course::class)
                                  ->whereColumn('files.fileable_id', 'courses.id')
                                  ->where('files.type', 'roter_faden');
                            })
                            ->whereRaw("
                                (
                                  SELECT COUNT(DISTINCT cpe.person_id)
                                  FROM course_participant_enrollments cpe
                                  WHERE cpe.course_id = courses.id
                                    AND cpe.is_active = 1
                                    AND cpe.deleted_at IS NULL
                                ) > 0
                            ")
                            ->whereRaw("
                                (
                                  SELECT COUNT(DISTINCT cmaa.person_id)
                                  FROM course_material_acknowledgements cmaa
                                  WHERE cmaa.course_id = courses.id
                                    AND cmaa.acknowledged_at IS NOT NULL
                                ) BETWEEN 1 AND (
                                  SELECT COUNT(DISTINCT cpe2.person_id) - 1
                                  FROM course_participant_enrollments cpe2
                                  WHERE cpe2.course_id = courses.id
                                    AND cpe2.is_active = 1
                                    AND cpe2.deleted_at IS NULL
                                )
                            ");
                        });
                });
                break;

            // ==========================
            // Einzelbereiche: Dokumentation
            // ==========================
            case 'doc_ok':
                $q->whereExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today);
                })->whereNotExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today)
                      ->where(function ($w) {
                          $w->whereNull('course_days.notes')
                            ->orWhere('course_days.notes', '=', '');
                      });
                });
                break;

            case 'doc_missing':
                $q->whereExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today);
                })->whereNotExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today)
                      ->whereNotNull('course_days.notes')
                      ->where('course_days.notes', '!=', '');
                });
                break;

            case 'doc_partial':
                $q->whereExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today)
                      ->whereNotNull('course_days.notes')
                      ->where('course_days.notes', '!=', '');
                })->whereExists(function ($s) use ($today) {
                    $s->select(DB::raw(1))
                      ->from('course_days')
                      ->whereColumn('course_days.course_id', 'courses.id')
                      ->whereDate('course_days.date', '<=', $today)
                      ->where(function ($w) {
                          $w->whereNull('course_days.notes')
                            ->orWhere('course_days.notes', '=', '');
                      });
                });
                break;

            // ==========================
            // Einzelbereiche: Roter Faden
            // ==========================
            case 'rf_ok':
                $q->whereExists(function ($s) {
                    $s->select(DB::raw(1))
                      ->from('files')
                      ->where('files.fileable_type', \App\Models\Course::class)
                      ->whereColumn('files.fileable_id', 'courses.id')
                      ->where('files.type', 'roter_faden');
                });
                break;

            case 'rf_missing':
                $q->whereNotExists(function ($s) {
                    $s->select(DB::raw(1))
                      ->from('files')
                      ->where('files.fileable_type', \App\Models\Course::class)
                      ->whereColumn('files.fileable_id', 'courses.id')
                      ->where('files.type', 'roter_faden');
                });
                break;

            // ==========================
            // Einzelbereiche: Bestätigungen
            // ==========================
            case 'ack_ok':
                $q->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cpe.person_id)
                      FROM course_participant_enrollments cpe
                      WHERE cpe.course_id = courses.id
                        AND cpe.is_active = 1
                        AND cpe.deleted_at IS NULL
                    ) > 0
                ")->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cmaa.person_id)
                      FROM course_material_acknowledgements cmaa
                      WHERE cmaa.course_id = courses.id
                        AND cmaa.acknowledged_at IS NOT NULL
                    ) = (
                      SELECT COUNT(DISTINCT cpe2.person_id)
                      FROM course_participant_enrollments cpe2
                      WHERE cpe2.course_id = courses.id
                        AND cpe2.is_active = 1
                        AND cpe2.deleted_at IS NULL
                    )
                ");
                break;

            case 'ack_missing':
                $q->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cpe.person_id)
                      FROM course_participant_enrollments cpe
                      WHERE cpe.course_id = courses.id
                        AND cpe.is_active = 1
                        AND cpe.deleted_at IS NULL
                    ) > 0
                ")->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cmaa.person_id)
                      FROM course_material_acknowledgements cmaa
                      WHERE cmaa.course_id = courses.id
                        AND cmaa.acknowledged_at IS NOT NULL
                    ) = 0
                ");
                break;

            case 'ack_partial':
                $q->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cpe.person_id)
                      FROM course_participant_enrollments cpe
                      WHERE cpe.course_id = courses.id
                        AND cpe.is_active = 1
                        AND cpe.deleted_at IS NULL
                    ) > 0
                ")->whereRaw("
                    (
                      SELECT COUNT(DISTINCT cmaa.person_id)
                      FROM course_material_acknowledgements cmaa
                      WHERE cmaa.course_id = courses.id
                        AND cmaa.acknowledged_at IS NOT NULL
                    ) BETWEEN 1 AND (
                      SELECT COUNT(DISTINCT cpe2.person_id) - 1
                      FROM course_participant_enrollments cpe2
                      WHERE cpe2.course_id = courses.id
                        AND cpe2.is_active = 1
                        AND cpe2.deleted_at IS NULL
                    )
                ");
                break;

            // ==========================
            // Optional: Rechnung
            // ==========================
            case 'inv_ok':
                $q->whereExists(function ($s) {
                    $s->select(DB::raw(1))
                      ->from('files')
                      ->where('files.fileable_type', \App\Models\Course::class)
                      ->whereColumn('files.fileable_id', 'courses.id')
                      ->where('files.type', 'invoice');
                });
                break;

            case 'inv_missing':
                $q->whereNotExists(function ($s) {
                    $s->select(DB::raw(1))
                      ->from('files')
                      ->where('files.fileable_type', \App\Models\Course::class)
                      ->whereColumn('files.fileable_id', 'courses.id')
                      ->where('files.type', 'invoice');
                });
                break;
        }
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
            'selectedCourses' => $this->selectedCourses,
        ])->layout('layouts.master');
    }

    /**
     * Lädt Optionen für das Termin-Select ausschließlich aus Course->termin_id (STRING).
     * Rückgabe: Collection/Array von Objekten: { id: string, name: string }
     * Label inkl. Count, Sortierung lexikografisch nach termin_id.
     */
    protected function loadTermOptionsFromCourses()
    {
        return Course::query()
            ->whereNotNull('termin_id')
            ->groupBy('termin_id')
            ->orderBy('termin_id', 'asc')
            ->selectRaw('termin_id, COUNT(*) AS cnt, MIN(planned_start_date) AS planned_start_date, MAX(planned_end_date) AS planned_end_date')
            ->get()
            ->map(fn($row) => (object)[
                'id'   => (string) $row->termin_id,
                'name' => (string) $row->termin_id,
                'cnt'  => (int) $row->cnt,
                'start' => (string) Carbon::parse($row->planned_start_date)->format('d.m.Y'),
                'end'   => (string) Carbon::parse($row->planned_end_date)->format('d.m.Y'),
            ]);
    }

    public function removeSelectedCourses()
    {
        $this->selectedCourses = [];
    }

    public function exportCourses()
    {
        $this->dispatch('showAlert', 'Export erfolgreich!', 'success');

    }

    public function toggleSelectAll()
    {
        if (count($this->selectedCourses) === 0) {
            // Alle Kurse der aktuellen Seite auswählen
            $coursesOnPage = $this->baseQuery()
                ->pluck('id')
                ->toArray();
            $this->selectedCourses = $coursesOnPage;
        } else {
            // Auswahl aufheben
            $this->selectedCourses = [];
        }
    }


    #[On('toggleCourseSelection')]
    public function toggleCourseSelection($courseId)
    {
        if (in_array($courseId, $this->selectedCourses)) {
            $this->selectedCourses = array_filter($this->selectedCourses, fn($id) => $id != $courseId);
        } else {
            $this->selectedCourses[] = $courseId;
        }
    }


    public function exportAttendancePdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportAttendanceListPdf();
    }

    public function exportDokuPdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportDokuPdf();
    }

    public function exportMaterialConfirmationsPdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportMaterialConfirmationsPdf();
    }


    public function exportInvoicePdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportInvoicePdf();
    }

    public function exportRedThreadPdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportRedThreadPdf();
    }

    public function exportExamResultsPdf($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportExamResultsPdf();
    }

    public function exportCourse($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportAllDocumentsZip();
    }
}
