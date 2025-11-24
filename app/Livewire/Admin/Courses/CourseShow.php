<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CourseShow extends Component
{
    public Course $course;
    
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';
    public int $perPage = 10;

    public function mount(Course $course): void
    {
        // Nur minimal setzen – Details werden in render() nachgeladen
        $this->course = $course;
    }

    /** Status-Badge fürs Blade */
    public function getStatusProperty(): string
    {
        $now = Carbon::now();
        $s = $this->course->planned_start_date;
        $e = $this->course->planned_end_date;

        if ($s && $e) {
            return $now->lt($s) ? 'planned' : ($now->between($s, $e) ? 'active' : 'finished');
        }
        if ($s && !$e) {
            return $now->lt($s) ? 'planned' : 'active';
        }
        return 'unknown';
    }

    /**
     * Export Functions
     */
    public function exportAttendancePdf(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->course->exportAttendanceListPdf();
    }

    public function exportDokuPdf(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->course->exportDokuPdf();
    }
    
    public function exportMaterialConfirmationsPdf(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
                $result = $this->course->exportMaterialConfirmationsPdf();

        if ($result === false) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'title' => 'Keine Bestätigungen',
                'message' => 'Es liegen noch keine Materialbestätigungen vor.',
            ]);
        }else {
            return $result;
        }
    }

    public function exportInvoicePdf(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->course->exportInvoicePdf();
    }

    public function exportExamResultsPdf(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->course->exportExamResultsPdf();
    }

    public function render()
    {
        $this->course
            ->loadCount([
                'days as dates_count',
                'participants as participants_count',
            ]);

        return view('livewire.admin.courses.course-show')->layout('layouts.master');
    }

}
