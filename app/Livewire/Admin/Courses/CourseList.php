<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;

class CourseList extends Component
{
    use WithPagination;

    // Such-/Sortier-/Paging
    public string $search  = '';
    public string $sortBy  = 'planned_start_date';
    public string $sortDir = 'desc';
    public int    $perPage = 15;

    // Filter
    public ?string $from   = '2026-01-01'; // Y-m-d
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
        Gate::authorize('courses.view');
        $this->coursesTotal = Course::where('planned_start_date', '>=', $this->from)->count();
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
              ->orWhere('courses.description', 'like', $s)
              ->orWhere('courses.source_snapshot->course->kurzbez', 'like', $s)
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

    // Inhalts-Status-Filter (an Course-Model-States angeglichen)
    if (!empty($contentFilter)) {
        $courseType = addslashes(\App\Models\Course::class);
        $docDoneStatus = (int) CourseDay::NOTE_STATUS_COMPLETED;

        $docTotalExpr = "
            (
                SELECT COUNT(*)
                FROM course_days cd
                WHERE cd.course_id = courses.id
            )
        ";

        $docCompletedExpr = "
            (
                SELECT COUNT(*)
                FROM course_days cd
                WHERE cd.course_id = courses.id
                  AND cd.note_status = {$docDoneStatus}
            )
        ";

        $docHasParticipantSignatureExpr = "
            EXISTS (
                SELECT 1
                FROM files f
                WHERE f.fileable_type = '{$courseType}'
                  AND f.fileable_id = courses.id
                  AND f.type = 'sign_course_doku_participant'
            )
        ";

        // 0 = fehlt, 1 = vollständig, 2 = teilweise/ausstehend
        $docStateExpr = "
            CASE
                WHEN {$docTotalExpr} = 0 THEN 0
                WHEN {$docCompletedExpr} = 0 THEN 0
                WHEN {$docCompletedExpr} = {$docTotalExpr}
                     AND {$docHasParticipantSignatureExpr}
                THEN 1
                ELSE 2
            END
        ";

        $rfStateExpr = "
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM files f
                    WHERE f.fileable_type = '{$courseType}'
                      AND f.fileable_id = courses.id
                      AND f.type = 'roter_faden'
                ) THEN 1
                ELSE 0
            END
        ";

        $participantsTotalExpr = "
            (
                SELECT COUNT(DISTINCT cpe.person_id)
                FROM course_participant_enrollments cpe
                WHERE cpe.course_id = courses.id
                  AND cpe.is_active = 1
                  AND cpe.deleted_at IS NULL
            )
        ";

        $ackTotalExpr = "
            (
                SELECT COUNT(DISTINCT cmaa.person_id)
                FROM course_material_acknowledgements cmaa
                WHERE cmaa.course_id = courses.id
                  AND cmaa.acknowledged_at IS NOT NULL
            )
        ";

        // 0 = fehlt, 1 = vollständig, 2 = teilweise/ausstehend
        $ackStateExpr = "
            CASE
                WHEN {$participantsTotalExpr} = 0 THEN 0
                WHEN {$ackTotalExpr} = 0 THEN 0
                WHEN {$ackTotalExpr} < {$participantsTotalExpr} THEN 2
                ELSE 1
            END
        ";

        $invStateExpr = "
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM files f
                    WHERE f.fileable_type = '{$courseType}'
                      AND f.fileable_id = courses.id
                      AND f.type = 'invoice'
                ) THEN 1
                ELSE 0
            END
        ";

        switch ($contentFilter) {
            case 'all_ok':
                $q->whereRaw("{$docStateExpr} = 1")
                  ->whereRaw("{$rfStateExpr} = 1")
                  ->whereRaw("{$ackStateExpr} = 1");
                break;

            case 'all_missing':
                $q->where(function ($sub) use ($docStateExpr, $rfStateExpr, $ackStateExpr) {
                    $sub->whereRaw("{$docStateExpr} = 0")
                        ->orWhereRaw("{$rfStateExpr} = 0")
                        ->orWhereRaw("{$ackStateExpr} = 0");
                });
                break;

            case 'all_partial':
                // Teilweise, wenn kein Bereich fehlt und mind. ein Bereich auf "teilweise" steht.
                $q->whereRaw("{$docStateExpr} IN (1,2)")
                  ->whereRaw("{$rfStateExpr} = 1")
                  ->whereRaw("{$ackStateExpr} IN (1,2)")
                  ->where(function ($sub) use ($docStateExpr, $ackStateExpr) {
                      $sub->whereRaw("{$docStateExpr} = 2")
                          ->orWhereRaw("{$ackStateExpr} = 2");
                  });
                break;

            case 'doc_ok':
                $q->whereRaw("{$docStateExpr} = 1");
                break;

            case 'doc_missing':
                $q->whereRaw("{$docStateExpr} = 0");
                break;

            case 'doc_partial':
                $q->whereRaw("{$docStateExpr} = 2");
                break;

            case 'rf_ok':
                $q->whereRaw("{$rfStateExpr} = 1");
                break;

            case 'rf_missing':
                $q->whereRaw("{$rfStateExpr} = 0");
                break;

            case 'ack_ok':
                $q->whereRaw("{$ackStateExpr} = 1");
                break;

            case 'ack_missing':
                $q->whereRaw("{$ackStateExpr} = 0");
                break;

            case 'ack_partial':
                $q->whereRaw("{$ackStateExpr} = 2");
                break;

            case 'inv_ok':
                $q->whereRaw("{$invStateExpr} = 1");
                break;

            case 'inv_missing':
                $q->whereRaw("{$invStateExpr} = 0");
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
            "CONCAT(COALESCE(tutor.vorname,''), ', ', COALESCE(tutor.nachname,'')) " .
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
            ->where('planned_start_date', '>=', $this->from)
            ->groupBy('termin_id')
            ->orderBy('planned_start_date', 'asc')
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




public function exportAttendancePdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createAttendanceListPdfForPreview(); // NEU: speichert in storage und gibt relativen Pfad zurück
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Unterrichtstage vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Klassen-Anwesenheitsliste_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportDokuPdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createDokuPdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Unterrichtstage vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Kurs-Doku_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportMaterialConfirmationsPdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createMaterialConfirmationsPdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Teilnehmer / Materialbestätigungen vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Materialbestaetigungen_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportInvoicePdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createInvoicePdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Rechnungsdaten vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Rechnung_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportRedThreadPdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createRedThreadPdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Roter Faden nicht verfügbar.', 'error');
        return;
    }

    $filename = sprintf('RoterFaden_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportExamResultsPdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createExamResultsPdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Prüfungsergebnisse vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Pruefungsergebnisse_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}

public function exportCourseRatingsPdf($courseId): void
{
    $course = Course::findOrFail($courseId);

    $relPath = $course->createCourseRatingsPdfForPreview();
    if (! $relPath) {
        $this->dispatch('showAlert', 'Keine Kursbewertungen vorhanden.', 'error');
        return;
    }

    $filename = sprintf('Kursbewertungen_%s.pdf', $course->klassen_id ?: $course->id);

    $this->dispatch('filepreview:open',
        disk: 'local',
        path: $relPath,
        name: $filename,
        deleteOnClose: true
    );
}


    public function exportCourse($courseId): ?StreamedResponse
    {
        $this->course = Course::findOrFail($courseId);
        return $this->course->exportAllDocumentsZip();
    }
}
