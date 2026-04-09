<?php

namespace App\Livewire\Admin\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseMaterialAcknowledgement;
use App\Models\CourseParticipantEnrollment;
use App\Models\CourseRating;
use App\Models\CourseResult;
use App\Models\File;
use App\Models\FilePool;
use App\Models\ReportBook;
use App\Models\ReportBookEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class DeletedCourseTransferModal extends Component
{
    use WithPagination;

    private const PAGE_NAME = 'deleted-courses-page';

    public bool $showModal = false;

    public ?int $targetCourseId = null;

    public ?string $targetCourseTitle = null;

    public ?string $targetCourseShortName = null;

    public string $search = '';

    public int $perPage = 8;

    public ?int $selectedSourceCourseId = null;

    protected $listeners = [
        'openDeletedCourseTransferModal' => 'open',
    ];

    public function open(int $targetCourseId): void
    {
        Gate::authorize('courses.view');

        $targetCourse = Course::query()->findOrFail($targetCourseId);

        $this->resetValidation();
        $this->resetPage(self::PAGE_NAME);

        $this->targetCourseId = $targetCourse->id;
        $this->targetCourseTitle = $targetCourse->title ?: 'Kurs #' . $targetCourse->id;
        $this->targetCourseShortName = $targetCourse->course_short_name ?: null;
        $this->search = '';
        $this->selectedSourceCourseId = null;
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->resetPage(self::PAGE_NAME);

        $this->showModal = false;
        $this->targetCourseId = null;
        $this->targetCourseTitle = null;
        $this->targetCourseShortName = null;
        $this->search = '';
        $this->selectedSourceCourseId = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    public function updatingPerPage(): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    public function selectSourceCourse(int $courseId): void
    {
        $sourceCourse = $this->deletedCoursesQuery()
            ->whereKey($courseId)
            ->first();

        if (! $sourceCourse) {
            $this->addError('selectedSourceCourseId', 'Der geloeschte Kurs konnte nicht geladen werden.');
            return;
        }

        $this->resetErrorBag('selectedSourceCourseId');
        $this->selectedSourceCourseId = $sourceCourse->id;
    }

    public function confirmSourceSelection(): void
    {
        if (! $this->selectedSourceCourseId) {
            $this->addError('selectedSourceCourseId', 'Bitte waehlen Sie zuerst einen geloeschten Kurs aus.');
            return;
        }

        $sourceCourse = Course::onlyTrashed()->find($this->selectedSourceCourseId);

        if (! $sourceCourse) {
            $this->addError('selectedSourceCourseId', 'Der ausgewaehlte geloeschte Kurs ist nicht mehr verfuegbar.');
            return;
        }

        $this->dispatch('deletedCourseTransferSourceSelected', sourceCourseId: $sourceCourse->id);
        $this->dispatch('toast', type: 'success', message: 'Der geloeschte Quellkurs wurde ausgewaehlt.');

        $this->close();
    }

    public function getSelectedSourceCourseProperty(): ?Course
    {
        if (! $this->selectedSourceCourseId) {
            return null;
        }

        $course = Course::onlyTrashed()
            ->with(['tutor:id,vorname,nachname'])
            ->withCount([
                'days as course_days_total',
                'participants as active_participants_total',
            ])
            ->find($this->selectedSourceCourseId);

        if (! $course) {
            return null;
        }

        return $this->decorateCoursesForTransferPreview(collect([$course]))->first();
    }

    protected function deletedCoursesQuery(): Builder
    {
        return Course::onlyTrashed()
            ->when($this->targetCourseId, fn (Builder $query) => $query->where('id', '!=', $this->targetCourseId))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%' . str_replace(' ', '%', trim($this->search)) . '%';

                $query->where(function (Builder $subQuery) use ($term): void {
                    $subQuery->where('title', 'like', $term)
                        ->orWhere('klassen_id', 'like', $term)
                        ->orWhere('termin_id', 'like', $term)
                        ->orWhere('source_snapshot->course->kurzbez', 'like', $term);
                });
            })
            ->with(['tutor:id,vorname,nachname'])
            ->withCount([
                'days as course_days_total',
                'participants as active_participants_total',
            ])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');
    }

    protected function decoratePaginatorForTransferPreview(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $paginator->setCollection(
            $this->decorateCoursesForTransferPreview($paginator->getCollection())
        );

        return $paginator;
    }

    protected function decorateCoursesForTransferPreview(Collection $courses): Collection
    {
        if ($courses->isEmpty()) {
            return $courses;
        }

        $courseIds = $courses
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $previewMap = $this->buildTransferPreviewMap($courseIds);

        return $courses->map(function (Course $course) use ($previewMap) {
            $course->transfer_preview = $previewMap[$course->id] ?? $this->makeTransferPreview([
                'course_days_total' => 0,
                'documented_course_days_total' => 0,
                'attendance_course_days_total' => 0,
                'day_files_total' => 0,
                'enrollments_total' => 0,
                'results_total' => 0,
                'ratings_total' => 0,
                'acknowledgements_total' => 0,
                'acknowledgement_files_total' => 0,
                'course_files_total' => 0,
                'file_pool_files_total' => 0,
                'report_books_total' => 0,
                'report_book_entries_total' => 0,
                'report_book_files_total' => 0,
            ]);

            return $course;
        });
    }

    protected function buildTransferPreviewMap(Collection $courseIds): array
    {
        $ids = $courseIds->all();

        if ($ids === []) {
            return [];
        }

        $daysByCourse = $this->pluckCounts(
            CourseDay::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $documentedDaysByCourse = $this->pluckCounts(
            CourseDay::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->where('note_status', CourseDay::NOTE_STATUS_COMPLETED)
                ->groupBy('course_id')
        );

        $attendanceDaysByCourse = $this->pluckCounts(
            CourseDay::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->whereNotNull('attendance_data')
                ->groupBy('course_id')
        );

        $dayFilesByCourse = $this->pluckCounts(
            File::query()
                ->join('course_days as cd', 'cd.id', '=', 'files.fileable_id')
                ->where('files.fileable_type', CourseDay::class)
                ->whereIn('cd.course_id', $ids)
                ->selectRaw('cd.course_id as course_id, COUNT(files.id) as aggregate')
                ->groupBy('cd.course_id')
        );

        $enrollmentsByCourse = $this->pluckCounts(
            CourseParticipantEnrollment::withTrashed()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $resultsByCourse = $this->pluckCounts(
            CourseResult::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $ratingsByCourse = $this->pluckCounts(
            CourseRating::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $acknowledgementsByCourse = $this->pluckCounts(
            CourseMaterialAcknowledgement::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $acknowledgementFilesByCourse = $this->pluckCounts(
            File::query()
                ->join('course_material_acknowledgements as cma', 'cma.id', '=', 'files.fileable_id')
                ->where('files.fileable_type', CourseMaterialAcknowledgement::class)
                ->whereIn('cma.course_id', $ids)
                ->selectRaw('cma.course_id as course_id, COUNT(files.id) as aggregate')
                ->groupBy('cma.course_id')
        );

        $courseFilesByCourse = $this->pluckCounts(
            File::query()
                ->where('fileable_type', Course::class)
                ->whereIn('fileable_id', $ids)
                ->selectRaw('fileable_id as course_id, COUNT(*) as aggregate')
                ->groupBy('fileable_id')
        );

        $filePoolFilesByCourse = $this->pluckCounts(
            File::query()
                ->join('file_pools as fp', 'fp.id', '=', 'files.fileable_id')
                ->where('files.fileable_type', FilePool::class)
                ->where('fp.filepoolable_type', Course::class)
                ->whereIn('fp.filepoolable_id', $ids)
                ->selectRaw('fp.filepoolable_id as course_id, COUNT(files.id) as aggregate')
                ->groupBy('fp.filepoolable_id')
        );

        $reportBooksByCourse = $this->pluckCounts(
            ReportBook::query()
                ->selectRaw('course_id, COUNT(*) as aggregate')
                ->whereIn('course_id', $ids)
                ->groupBy('course_id')
        );

        $reportBookEntriesByCourse = $this->pluckCounts(
            ReportBookEntry::query()
                ->join('report_books as rb', 'rb.id', '=', 'report_book_entries.report_book_id')
                ->whereIn('rb.course_id', $ids)
                ->selectRaw('rb.course_id as course_id, COUNT(report_book_entries.id) as aggregate')
                ->groupBy('rb.course_id')
        );

        $reportBookFilesByCourse = $this->pluckCounts(
            File::query()
                ->join('report_books as rb', 'rb.id', '=', 'files.fileable_id')
                ->where('files.fileable_type', ReportBook::class)
                ->whereIn('rb.course_id', $ids)
                ->selectRaw('rb.course_id as course_id, COUNT(files.id) as aggregate')
                ->groupBy('rb.course_id')
        );

        $previewMap = [];

        foreach ($ids as $courseId) {
            $previewMap[$courseId] = $this->makeTransferPreview([
                'course_days_total' => (int) ($daysByCourse[$courseId] ?? 0),
                'documented_course_days_total' => (int) ($documentedDaysByCourse[$courseId] ?? 0),
                'attendance_course_days_total' => (int) ($attendanceDaysByCourse[$courseId] ?? 0),
                'day_files_total' => (int) ($dayFilesByCourse[$courseId] ?? 0),
                'enrollments_total' => (int) ($enrollmentsByCourse[$courseId] ?? 0),
                'results_total' => (int) ($resultsByCourse[$courseId] ?? 0),
                'ratings_total' => (int) ($ratingsByCourse[$courseId] ?? 0),
                'acknowledgements_total' => (int) ($acknowledgementsByCourse[$courseId] ?? 0),
                'acknowledgement_files_total' => (int) ($acknowledgementFilesByCourse[$courseId] ?? 0),
                'course_files_total' => (int) ($courseFilesByCourse[$courseId] ?? 0),
                'file_pool_files_total' => (int) ($filePoolFilesByCourse[$courseId] ?? 0),
                'report_books_total' => (int) ($reportBooksByCourse[$courseId] ?? 0),
                'report_book_entries_total' => (int) ($reportBookEntriesByCourse[$courseId] ?? 0),
                'report_book_files_total' => (int) ($reportBookFilesByCourse[$courseId] ?? 0),
            ]);
        }

        return $previewMap;
    }

    protected function makeTransferPreview(array $stats): array
    {
        $recoverable = [
            ['label' => 'Kursstammdaten', 'count' => 1],
        ];

        $pushRecoverable = function (string $label, string $key) use (&$recoverable, $stats): void {
            $count = (int) ($stats[$key] ?? 0);

            if ($count > 0) {
                $recoverable[] = [
                    'label' => $label,
                    'count' => $count,
                ];
            }
        };

        $pushRecoverable('Kurstage', 'course_days_total');
        $pushRecoverable('Dokumentationstage', 'documented_course_days_total');
        $pushRecoverable('Anwesenheitstage', 'attendance_course_days_total');
        $pushRecoverable('Tagesdateien', 'day_files_total');
        $pushRecoverable('Teilnehmer-Zuordnungen', 'enrollments_total');
        $pushRecoverable('Pruefungsergebnisse', 'results_total');
        $pushRecoverable('Bewertungen', 'ratings_total');
        $pushRecoverable('Material-Bestaetigungen', 'acknowledgements_total');
        $pushRecoverable('Material-Signaturen', 'acknowledgement_files_total');
        $pushRecoverable('Kursdateien', 'course_files_total');
        $pushRecoverable('FilePool-Dateien', 'file_pool_files_total');
        $pushRecoverable('Berichtshefte', 'report_books_total');
        $pushRecoverable('Berichtsheft-Eintraege', 'report_book_entries_total');
        $pushRecoverable('Berichtsheft-Dateien', 'report_book_files_total');

        $missing = [];

        if ((int) ($stats['course_days_total'] ?? 0) === 0) {
            $missing[] = 'Kurstage / Tagesdoku / Anwesenheit';
        }

        if ((int) ($stats['enrollments_total'] ?? 0) === 0) {
            $missing[] = 'Teilnehmer-Zuordnungen';
        }

        if ((int) ($stats['results_total'] ?? 0) === 0) {
            $missing[] = 'Pruefungsergebnisse';
        }

        if ((int) ($stats['ratings_total'] ?? 0) === 0) {
            $missing[] = 'Bewertungen';
        }

        if ((int) ($stats['acknowledgements_total'] ?? 0) === 0
            && (int) ($stats['acknowledgement_files_total'] ?? 0) === 0) {
            $missing[] = 'Material-Bestaetigungen';
        }

        if ((int) ($stats['course_files_total'] ?? 0) === 0
            && (int) ($stats['file_pool_files_total'] ?? 0) === 0) {
            $missing[] = 'Kursdateien / FilePool';
        }

        if ((int) ($stats['report_books_total'] ?? 0) === 0
            && (int) ($stats['report_book_entries_total'] ?? 0) === 0
            && (int) ($stats['report_book_files_total'] ?? 0) === 0) {
            $missing[] = 'Berichtshefte';
        }

        $legacyCoursePayload = collect([
            'course_days_total',
            'documented_course_days_total',
            'attendance_course_days_total',
            'day_files_total',
            'enrollments_total',
            'results_total',
            'ratings_total',
            'acknowledgements_total',
            'acknowledgement_files_total',
            'course_files_total',
            'file_pool_files_total',
        ])->sum(fn (string $key) => (int) ($stats[$key] ?? 0));

        $reportBookPayload = collect([
            'report_books_total',
            'report_book_entries_total',
            'report_book_files_total',
        ])->sum(fn (string $key) => (int) ($stats[$key] ?? 0));

        $summary = match (true) {
            $legacyCoursePayload > 0 && $reportBookPayload > 0
                => 'Neben Berichtsheften sind auch noch direkte Kursinhalte in der Datenbank vorhanden.',
            $legacyCoursePayload > 0
                => 'Es sind noch direkte Kursinhalte vorhanden, die grundsaetzlich uebernommen werden koennten.',
            $reportBookPayload > 0
                => 'Es sind nur noch Berichtsheft-Daten vorhanden; die uebrigen Kursinhalte wurden offenbar bereits entfernt.',
            default
                => 'Aktuell ist ausser dem geloeschten Kursdatensatz kein weiterer uebertragbarer Inhalt mehr vorhanden.',
        };

        return [
            'stats' => $stats,
            'recoverable' => $recoverable,
            'missing' => $missing,
            'legacy_course_payload_total' => $legacyCoursePayload,
            'report_book_payload_total' => $reportBookPayload,
            'summary' => $summary,
        ];
    }

    protected function pluckCounts(Builder $query): array
    {
        return $query
            ->pluck('aggregate', 'course_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function render()
    {
        $deletedCourses = $this->decoratePaginatorForTransferPreview(
            $this->deletedCoursesQuery()->paginate($this->perPage, ['*'], self::PAGE_NAME)
        );

        return view('livewire.admin.courses.deleted-course-transfer-modal', [
            'deletedCourses' => $deletedCourses,
        ]);
    }
}
