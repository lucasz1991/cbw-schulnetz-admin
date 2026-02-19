<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class Alerts extends Component
{
    public bool $autoRefresh = true;

    /** @var array<int, array<string, mixed>> */
    public array $alerts = [];

    public int $maxItems = 8;
    public int $lookbackDays = 10;

    public function mount(bool $autoRefresh = true): void
    {
        $this->autoRefresh = $autoRefresh;
        $this->loadAlerts();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.alerts');
    }

    public function loadAlerts(): void
    {
        $attendanceAlerts = $this->buildMissingAttendanceAlerts();
        $documentationAlerts = $this->buildMissingDocumentationAlerts();
        $tutorAlerts = $this->buildMissingTutorAlerts();

        $this->alerts = collect()
            ->concat($attendanceAlerts)
            ->concat($documentationAlerts)
            ->concat($tutorAlerts)
            ->sortBy([
                ['priority', 'desc'],
                ['reference_date', 'asc'],
            ])
            ->take($this->maxItems)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMissingAttendanceAlerts(): array
    {
        $fromDate = now()->subDays($this->lookbackDays)->startOfDay();
        $toDate = now()->subDay()->endOfDay();

        if ($toDate->lt($fromDate)) {
            return [];
        }

        $days = CourseDay::query()
            ->with([
                'course:id,title,klassen_id,termin_id,planned_start_date,planned_end_date,primary_tutor_person_id',
            ])
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->whereHas('course', fn (Builder $q) => $this->applyRunningActiveCourseConstraint($q))
            ->orderBy('date')
            ->get();

        $grouped = $days
            ->filter(fn (CourseDay $day) => $this->isAttendanceMissing($day))
            ->groupBy('course_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        $affectedCourses = $grouped->count();
        $missingDaysTotal = $grouped->sum(fn (Collection $items) => $items->count());
        $oldestDay = $grouped->flatten(1)->sortBy('date')->first();
        $oldestDate = optional($oldestDay?->date)?->format('d.m.Y') ?? 'unbekannt';
        $examples = $this->formatCourseExamples($grouped, 3);

        return [[
            'priority' => 100,
            'type' => 'attendance',
            'icon' => 'fa-user-clock',
            'icon_bg' => 'bg-red-50 border-red-100',
            'icon_color' => 'text-red-700',
            'badge_classes' => 'bg-red-50 text-red-700 border-red-200',
            'title' => 'Anwesenheit fehlt',
            'message' => $affectedCourses . ' laufende Kurse mit insgesamt '
                . $missingDaysTotal . ' Unterrichtstag(en) ohne Anwesenheit. '
                . 'Ältester Tag: ' . $oldestDate . '. '
                . 'Beispiele: ' . $examples . '.',
            'action_label' => 'Kurse prüfen',
            'action_url' => route('courses.index'),
            'reference_date' => optional($oldestDay?->date)?->toDateString() ?? now()->toDateString(),
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMissingDocumentationAlerts(): array
    {
        $fromDate = now()->subDays($this->lookbackDays)->startOfDay();
        $toDate = now()->subDay()->endOfDay();

        if ($toDate->lt($fromDate)) {
            return [];
        }

        $days = CourseDay::query()
            ->with([
                'course:id,title,klassen_id,termin_id,planned_start_date,planned_end_date,primary_tutor_person_id',
            ])
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->where('note_status', CourseDay::NOTE_STATUS_MISSING)
            ->whereHas('course', fn (Builder $q) => $this->applyRunningActiveCourseConstraint($q))
            ->orderBy('date')
            ->get()
            ->groupBy('course_id');

        if ($days->isEmpty()) {
            return [];
        }

        $affectedCourses = $days->count();
        $missingDaysTotal = $days->sum(fn (Collection $items) => $items->count());
        $oldestDay = $days->flatten(1)->sortBy('date')->first();
        $oldestDate = optional($oldestDay?->date)?->format('d.m.Y') ?? 'unbekannt';
        $examples = $this->formatCourseExamples($days, 3);

        return [[
            'priority' => 80,
            'type' => 'documentation',
            'icon' => 'fa-file-times',
            'icon_bg' => 'bg-amber-50 border-amber-100',
            'icon_color' => 'text-amber-700',
            'badge_classes' => 'bg-amber-50 text-amber-700 border-amber-200',
            'title' => 'Dokumentation fehlt',
            'message' => $affectedCourses . ' laufende Kurse mit insgesamt '
                . $missingDaysTotal . ' Unterrichtstag(en) ohne Doku. '
                . 'Ältester Tag: ' . $oldestDate . '. '
                . 'Beispiele: ' . $examples . '.',
            'action_label' => 'Kurse prüfen',
            'action_url' => route('courses.index'),
            'reference_date' => optional($oldestDay?->date)?->toDateString() ?? now()->toDateString(),
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMissingTutorAlerts(): array
    {
        $runningCourses = Course::query()
            ->with([
                'tutor:id,user_id,nachname,vorname',
                'participants' => function ($query) {
                    $query->select('persons.id', 'persons.user_id');
                },
            ])
            ->where(function (Builder $q) {
                $this->applyRunningActiveCourseConstraint($q);
            })
            ->get(['id', 'title', 'klassen_id', 'termin_id', 'planned_start_date', 'primary_tutor_person_id']);

        if ($runningCourses->isEmpty()) {
            return [];
        }

        $withoutTutor = $runningCourses
            ->whereNull('primary_tutor_person_id')
            ->values();

        $withoutUser = $runningCourses
            ->filter(function (Course $course) {
                if (! $course->primary_tutor_person_id) {
                    return false;
                }

                return empty(optional($course->tutor)->user_id);
            })
            ->values();

        $participantMissingPerCourse = $runningCourses
            ->mapWithKeys(function (Course $course) {
                $missingCount = $course->participants
                    ->filter(fn ($person) => empty($person->user_id))
                    ->count();

                return [$course->id => $missingCount];
            });

        $coursesWithParticipantMissing = $runningCourses
            ->filter(fn (Course $course) => (int) ($participantMissingPerCourse[$course->id] ?? 0) > 0)
            ->values();

        $missingParticipantsTotal = $participantMissingPerCourse->sum();

        if ($withoutTutor->isEmpty() && $withoutUser->isEmpty() && $coursesWithParticipantMissing->isEmpty()) {
            return [];
        }

        $exampleCourses = $withoutTutor
            ->concat($withoutUser)
            ->concat($coursesWithParticipantMissing)
            ->unique('id')
            ->values();

        $examples = $exampleCourses->isEmpty()
            ? '-'
            : $exampleCourses
                ->take(3)
                ->map(fn (Course $course) => $this->courseLabel($course))
                ->implode(', ');

        $messageParts = [];

        if ($withoutTutor->isNotEmpty()) {
            $messageParts[] = $withoutTutor->count() . ' Kurs(e) ohne zugewiesenen Dozenten';
        }

        if ($withoutUser->isNotEmpty()) {
            $messageParts[] = $withoutUser->count() . ' Kurs(e) mit Dozent ohne Schulnetz-User';
        }

        if ($coursesWithParticipantMissing->isNotEmpty()) {
            $messageParts[] = $coursesWithParticipantMissing->count() . ' Kurs(e) mit insgesamt '
                . $missingParticipantsTotal . ' Teilnehmer(n) ohne Schulnetz-User';
        }

        return [[
            'priority' => 60,
            'type' => 'tutor',
            'icon' => 'fa-user-slash',
            'icon_bg' => 'bg-orange-50 border-orange-100',
            'icon_color' => 'text-orange-700',
            'badge_classes' => 'bg-orange-50 text-orange-700 border-orange-200',
            'title' => 'Registrierung unvollständig',
            'message' => implode(' / ', $messageParts) . '. Beispiele: ' . $examples . '.',
            'action_label' => 'Kurse prüfen',
            'action_url' => route('courses.index'),
            'reference_date' => now()->toDateString(),
        ]];
    }

    protected function applyRunningActiveCourseConstraint(Builder $query): void
    {
        $today = now()->toDateString();

        $query
            ->where('is_active', true)
            ->whereNotNull('planned_start_date')
            ->whereDate('planned_start_date', '<=', $today)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('planned_end_date')
                    ->orWhereDate('planned_end_date', '>=', $today);
            });
    }

    protected function formatCourseExamples(Collection $groupedByCourseId, int $limit = 3): string
    {
        $examples = $groupedByCourseId
            ->map(function (Collection $items) {
                /** @var CourseDay|null $first */
                $first = $items->first();
                $course = $first?->course;

                if (! $course) {
                    return null;
                }

                return $this->courseLabel($course);
            })
            ->filter()
            ->take($limit)
            ->values();

        return $examples->isEmpty() ? '-' : $examples->implode(', ');
    }

    protected function isAttendanceMissing(CourseDay $day): bool
    {
        $attendance = $day->attendance_data ?? [];
        $statusStart = (int) data_get($attendance, 'status.start', 0);
        $participants = data_get($attendance, 'participants', []);

        if (! is_array($participants)) {
            $participants = [];
        }

        $hasTrackedParticipant = collect($participants)->contains(function ($row) {
            if (! is_array($row)) {
                return false;
            }

            return ! empty(data_get($row, 'updated_at'))
                || ! empty(data_get($row, 'timestamps.in'))
                || ! empty(data_get($row, 'timestamps.out'));
        });

        return $statusStart === 0 && ! $hasTrackedParticipant;
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
