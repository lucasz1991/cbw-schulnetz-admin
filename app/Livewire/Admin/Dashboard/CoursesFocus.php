<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Course;
use Livewire\Component;

class CoursesFocus extends Component
{
    public bool $autoRefresh = true;

    /** @var array<int, array<string, mixed>> */
    public array $courses = [];

    public int $limit = 20;

    public function mount(bool $autoRefresh = true): void
    {
        $this->autoRefresh = $autoRefresh;
        $this->loadCourses();
    }

    public function render()
    {
        $this->loadCourses();

        return view('livewire.admin.dashboard.courses-focus');
    }

    public function loadCourses(): void
    {
        $today = now()->toDateString();

        $this->courses = Course::query()
            ->with(['tutor:id,vorname,nachname'])
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereDate('planned_start_date', '>=', $today)
                    ->orWhere(function ($q) use ($today) {
                        $q->whereNotNull('planned_start_date')
                            ->whereDate('planned_start_date', '<=', $today)
                            ->where(function ($qq) use ($today) {
                                $qq->whereNull('planned_end_date')
                                    ->orWhereDate('planned_end_date', '>=', $today);
                            });
                    });
            })
            ->orderBy('planned_start_date')
            ->limit($this->limit)
            ->get([
                'id',
                'title',
                'klassen_id',
                'termin_id',
                'planned_start_date',
                'planned_end_date',
                'primary_tutor_person_id',
            ])
            ->map(function (Course $course) {
                $tutorName = trim((string) ($course->tutor->vorname ?? '') . ' ' . (string) ($course->tutor->nachname ?? ''));

                return [
                    'id' => $course->id,
                    'title' => $this->courseLabel($course),
                    'status_label' => $course->status_label,
                    'is_running' => $course->isRunning(),
                    'start' => optional($course->planned_start_date)?->format('d.m.Y'),
                    'end' => optional($course->planned_end_date)?->format('d.m.Y'),
                    'tutor_name' => $tutorName !== '' ? $tutorName : 'Kein Dozent zugewiesen',
                    'url' => route('admin.courses.show', $course->id),
                ];
            })
            ->values()
            ->all();
    }

    protected function courseLabel(Course $course): string
    {
        if (! empty($course->title)) {
            return $course->title;
        }

        $klassenId = $course->klassen_id ?: 'ohne Klassen-ID';
        $termin = $course->termin_id ? (' / ' . $course->termin_id) : '';

        return 'Kurs ' . $klassenId . $termin;
    }
}
