<?php

namespace App\Livewire\Admin\Courses;

use App\Actions\Courses\TransferDeletedCourseReportBooks;
use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseShow extends Component
{
    public Course $course;

    public ?int $deletedTransferSourceCourseId = null;
    
    public string $search  = '';
    public string $sortBy  = 'name';
    public string $sortDir = 'asc';
    public int $perPage    = 10;

    protected $listeners = [
        'deletedCourseTransferSourceSelected' => 'setDeletedTransferSourceCourse',
    ];

    public function mount(Course $course): void
    {
        // Nur minimal setzen – Details werden in render() nachgeladen
        $this->course = $course;
    }

    /** Status-Badge fürs Blade */
    public function getStatusProperty(): string
    {
        $now = Carbon::now();
        $s   = $this->course->planned_start_date;
        $e   = $this->course->planned_end_date;

        if ($s && $e) {
            return $now->lt($s)
                ? 'planned'
                : ($now->between($s, $e) ? 'active' : 'finished');
        }

        if ($s && ! $e) {
            return $now->lt($s) ? 'planned' : 'active';
        }

        return 'unknown';
    }

    /**
     * Export-Feasibility Properties
     */

    public function getCanExportAttendanceProperty(): bool
    {
        return $this->course->canExportAttendancePdf();
    }

    public function getCanExportDokuProperty(): bool
    {
        return $this->course->canExportDokuPdf();
    }

    public function getCanExportMaterialConfirmationsProperty(): bool
    {
        return $this->course->canExportMaterialConfirmationsPdf();
    }

    public function getCanExportInvoiceProperty(): bool
    {
        return $this->course->canExportInvoicePdf();
    }

    public function getCanExportRedThreadProperty(): bool
    {
        return $this->course->canExportRedThreadPdf();
    }

    public function getCanExportExamResultsProperty(): bool
    {
        return $this->course->canExportExamResultsPdf();
    }

    public function getCanExportCourseRatingsProperty(): bool
    {
        return $this->course->canExportCourseRatingsPdf();
    }

    /**
     * Export Actions
     */

    public function exportAttendancePdf(): ?StreamedResponse
    {
        return $this->course->exportAttendanceListPdf();
    }

    public function exportDokuPdf(): ?StreamedResponse
    {
        return $this->course->exportDokuPdf();
    }
    
    public function exportMaterialConfirmationsPdf(): ?StreamedResponse
    {
        return $this->course->exportMaterialConfirmationsPdf();
    }

    public function exportInvoicePdf(): ?StreamedResponse
    {
        return $this->course->exportInvoicePdf();
    }

    public function exportRedThreadPdf(): ?StreamedResponse
    {
        return $this->course->exportRedThreadPdf();
    }

    public function exportExamResultsPdf(): ?StreamedResponse
    {
        return $this->course->exportExamResultsPdf();
    }

    public function exportCourseRatingsPdf(): ?StreamedResponse
    {
        return $this->course->exportCourseRatingsPdf();
    }

    public function setDeletedTransferSourceCourse(int $sourceCourseId): void
    {
        $sourceCourse = Course::onlyTrashed()->findOrFail($sourceCourseId);

        $this->deletedTransferSourceCourseId = $sourceCourse->id;
    }

    public function clearDeletedTransferSourceCourse(): void
    {
        $this->deletedTransferSourceCourseId = null;
    }

    public function transferDeletedCourseReportBooks(TransferDeletedCourseReportBooks $transferAction): void
    {
        abort_unless(auth()->user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        if (! $this->deletedTransferSourceCourseId) {
            $this->dispatch('toast', type: 'warning', message: 'Bitte waehlen Sie zuerst einen geloeschten Quellkurs aus.');
            return;
        }

        $sourceCourse = Course::onlyTrashed()->find($this->deletedTransferSourceCourseId);

        if (! $sourceCourse) {
            $this->deletedTransferSourceCourseId = null;
            $this->dispatch('toast', type: 'warning', message: 'Der ausgewaehlte Quellkurs ist nicht mehr verfuegbar.');
            return;
        }

        $summary = $transferAction->handle($sourceCourse, $this->course->fresh());

        $toast = $this->buildDeletedReportBookTransferToast($summary);

        $this->course = $this->course->fresh();

        $this->dispatch('toast', type: $toast['type'], message: $toast['message']);
    }

    public function getDeletedTransferSourceCourseProperty(): ?Course
    {
        if (! $this->deletedTransferSourceCourseId) {
            return null;
        }

        return Course::onlyTrashed()->find($this->deletedTransferSourceCourseId);
    }

    protected function buildDeletedReportBookTransferToast(array $summary): array
    {
        $sourceBooksFound = (int) ($summary['source_books_found'] ?? 0);
        $participantsProcessed = (int) ($summary['participants_processed'] ?? 0);
        $participantsSkipped = (int) ($summary['participants_skipped'] ?? 0);

        if ($sourceBooksFound === 0) {
            return [
                'type' => 'info',
                'message' => 'Im ausgewaehlten geloeschten Kurs wurden keine Berichtshefte gefunden.',
            ];
        }

        if ($participantsProcessed === 0) {
            $message = 'Es konnten keine Berichtshefte in den aktuellen Kurs uebernommen werden.';

            if ($participantsSkipped > 0) {
                $message .= ' Die betroffenen Teilnehmer sind im Zielkurs derzeit nicht aktiv zugeordnet.';
            }

            if ((int) ($summary['participants_without_matching_days'] ?? 0) > 0) {
                $message .= ' Fuer die uebrigen Berichtsheft-Eintraege gibt es im Zielkurs keine passenden Kurstage.';
            }

            return [
                'type' => 'warning',
                'message' => $message,
            ];
        }

        $parts = [
            $participantsProcessed . ' Teilnehmer',
            ((int) ($summary['entries_kept'] ?? 0)) . ' Eintraege',
        ];

        if ((int) ($summary['duplicate_dates_resolved'] ?? 0) > 0) {
            $parts[] = ((int) $summary['duplicate_dates_resolved']) . ' Datums-Konflikte bereinigt';
        }

        if ((int) ($summary['entries_deleted'] ?? 0) > 0) {
            $parts[] = ((int) $summary['entries_deleted']) . ' alte Dubletten geloescht';
        }

        if ((int) ($summary['entries_ignored_without_matching_day'] ?? 0) > 0) {
            $parts[] = ((int) $summary['entries_ignored_without_matching_day']) . ' Tage ohne Ziel-Kurstag ignoriert';
        }

        if ($participantsSkipped > 0) {
            $parts[] = $participantsSkipped . ' Teilnehmer uebersprungen';
        }

        return [
            'type' => 'success',
            'message' => 'Berichtshefte uebernommen: ' . implode(', ', $parts) . '.',
        ];
    }

    public function render()
    {
        $this->course
            ->loadCount([
                'days as dates_count',
                'participants as participants_count',
            ]);

        return view('livewire.admin.courses.course-show')
            ->layout('layouts.master');
    }
}
