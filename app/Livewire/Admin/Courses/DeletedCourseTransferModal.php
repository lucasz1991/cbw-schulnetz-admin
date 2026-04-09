<?php

namespace App\Livewire\Admin\Courses;

use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
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

        return Course::onlyTrashed()
            ->with(['tutor:id,vorname,nachname'])
            ->withCount([
                'days as course_days_total',
                'participants as active_participants_total',
            ])
            ->find($this->selectedSourceCourseId);
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

    public function render()
    {
        return view('livewire.admin.courses.deleted-course-transfer-modal', [
            'deletedCourses' => $this->deletedCoursesQuery()->paginate($this->perPage, ['*'], self::PAGE_NAME),
        ]);
    }
}
