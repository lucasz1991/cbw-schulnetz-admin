<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\User;
use App\Models\Course;
use App\Models\Person;
use App\Models\CourseDay;
use App\Models\CourseResult;
use App\Models\CourseMaterialAcknowledgement;
use Illuminate\Support\Collection;

class UserCourses extends Component
{
    use WithPagination, WithoutUrlPagination;

    public User $user;

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount(User $user)
    {
        $this->user = $user->load(['persons']); // mehrere persons laden
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                        <span class="loader"></span>
                        <span class="text-sm text-gray-700">wird geladen…</span>
                    </div>
                </div>
            </div>
        HTML;
    }

    public function render()
    {
        $courses = collect();
        $courseMeta = [];

        // Liste aller person_ids des Users (multi-person)
        $personIds = $this->user->persons->pluck('id')->toArray();

        // ------------------------------------------------------------------
        // 📌 FALL 1: User ist Dozent → Kurse über Course.primary_tutor_person_id
        // ------------------------------------------------------------------
        if ($this->user->role === 'tutor') {

            $query = Course::whereIn('primary_tutor_person_id', $personIds)
                ->with('tutor')
                ->orderBy('planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%' . $this->search . '%';

                $query->where(function ($qq) use ($s) {
                    $qq->where('courses.title', 'like', $s)
                       ->orWhere('courses.klassen_id', 'like', $s)
                       ->orWhere('courses.termin_id', 'like', $s)
                       ->orWhere('courses.vtz', 'like', $s)
                       ->orWhere('courses.room', 'like', $s);
                });
            }

            $courses = $query->paginate($this->perPage);

        }
        // ------------------------------------------------------------------
        // FALL 2: User ist Teilnehmer → Pivot-Relation über alle Persons
        // ------------------------------------------------------------------
        else {

            $pivot = 'course_participant_enrollments';

            $query = Course::query()
                ->join($pivot, "$pivot.course_id", '=', 'courses.id')
                ->whereIn("$pivot.person_id", $personIds)
                ->select([
                    'courses.*',
                    "$pivot.id as _enrollment_id",
                    "$pivot.person_id as _person_id",
                    "$pivot.klassen_id as _enrollment_klassen_id",
                    "$pivot.termin_id as _enrollment_termin_id",
                    "$pivot.kurzbez_ba as _enrollment_kurzbez_ba",
                    "$pivot.status as _enrollment_status",
                    "$pivot.results as _enrollment_results",
                    "$pivot.notes as _enrollment_notes",
                ])
                ->with('tutor')
                ->orderBy('courses.planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%' . $this->search . '%';

                $query->where(function ($qq) use ($s, $pivot) {
                    $qq->where('courses.title', 'like', $s)
                       ->orWhere('courses.klassen_id', 'like', $s)
                       ->orWhere('courses.termin_id', 'like', $s)
                       ->orWhere("$pivot.klassen_id", 'like', $s)
                       ->orWhere("$pivot.termin_id", 'like', $s)
                       ->orWhere("$pivot.kurzbez_ba", 'like', $s)
                       ->orWhere("$pivot.status", 'like', $s);
                });
            }

            $courses = $query->paginate($this->perPage);
        }

        if ($courses->count() > 0) {
            $rows = $courses->getCollection();

            $courseIds = $rows->pluck('id')->filter()->unique()->values();
            $contextPersonIds = $rows->pluck('_person_id')->filter()->unique()->values();

            $personsById = $contextPersonIds->isEmpty()
                ? collect()
                : Person::query()
                    ->whereIn('id', $contextPersonIds)
                    ->get()
                    ->keyBy('id');

            $daysByCourse = $courseIds->isEmpty()
                ? collect()
                : CourseDay::query()
                    ->whereIn('course_id', $courseIds)
                    ->get(['id', 'course_id', 'attendance_data'])
                    ->groupBy('course_id');

            $acksByKey = ($courseIds->isEmpty() || $contextPersonIds->isEmpty())
                ? collect()
                : CourseMaterialAcknowledgement::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereIn('person_id', $contextPersonIds)
                    ->whereNotNull('acknowledged_at')
                    ->orderByDesc('acknowledged_at')
                    ->get()
                    ->groupBy(fn ($ack) => $this->pairKey((int)$ack->course_id, (int)$ack->person_id));

            $resultsByKey = ($courseIds->isEmpty() || $contextPersonIds->isEmpty())
                ? collect()
                : CourseResult::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereIn('person_id', $contextPersonIds)
                    ->orderByDesc('updated_at')
                    ->get()
                    ->groupBy(fn ($result) => $this->pairKey((int)$result->course_id, (int)$result->person_id));

            foreach ($rows as $course) {
                $rowKey = $this->rowKeyForCourse($course);
                $personId = isset($course->_person_id) ? (int)$course->_person_id : null;

                $meta = [
                    'person' => null,
                    'material_ack' => null,
                    'material_ack_at' => null,
                    'course_result' => null,
                    'enrollment_results' => [],
                    'attendance' => [
                        'tracked_days' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'excused' => 0,
                        'late_count' => 0,
                        'late_minutes' => 0,
                        'left_early_count' => 0,
                        'left_early_minutes' => 0,
                    ],
                ];

                if ($personId) {
                    $meta['person'] = $personsById->get($personId);

                    $pairKey = $this->pairKey((int)$course->id, $personId);

                    $ack = optional($acksByKey->get($pairKey))->first();
                    $meta['material_ack'] = (bool)$ack;
                    $meta['material_ack_at'] = $ack?->acknowledged_at;

                    $meta['course_result'] = optional($resultsByKey->get($pairKey))->first();

                    $meta['attendance'] = $this->buildAttendanceStats(
                        $daysByCourse->get($course->id, collect()),
                        $personId
                    );
                }

                $meta['enrollment_results'] = $this->normalizeEnrollmentResults(
                    $course->_enrollment_results ?? null
                );

                $courseMeta[$rowKey] = $meta;
            }
        }

        return view('livewire.admin.user-profile.user-courses', [
            'courses' => $courses,
            'courseMeta' => $courseMeta,
        ]);
    }

    protected function rowKeyForCourse(Course $course): string
    {
        return implode('-', [
            (int)$course->id,
            (int)($course->_person_id ?? 0),
            (int)($course->_enrollment_id ?? 0),
        ]);
    }

    protected function pairKey(int $courseId, int $personId): string
    {
        return $courseId . ':' . $personId;
    }

    protected function normalizeEnrollmentResults(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function buildAttendanceStats(Collection $days, int $personId): array
    {
        $stats = [
            'tracked_days' => 0,
            'present' => 0,
            'absent' => 0,
            'excused' => 0,
            'late_count' => 0,
            'late_minutes' => 0,
            'left_early_count' => 0,
            'left_early_minutes' => 0,
        ];

        foreach ($days as $day) {
            $row = data_get($day->attendance_data, 'participants.' . $personId);

            if (!is_array($row)) {
                continue;
            }

            $stats['tracked_days']++;

            $present = (bool)($row['present'] ?? false);
            $excused = (bool)($row['excused'] ?? false);

            if ($present) {
                $stats['present']++;
            } elseif ($excused) {
                $stats['excused']++;
            } else {
                $stats['absent']++;
            }

            $lateMinutes = max(0, (int)($row['late_minutes'] ?? 0));
            if ($lateMinutes > 0) {
                $stats['late_count']++;
                $stats['late_minutes'] += $lateMinutes;
            }

            $leftEarlyMinutes = max(0, (int)($row['left_early_minutes'] ?? 0));
            if ($leftEarlyMinutes > 0) {
                $stats['left_early_count']++;
                $stats['left_early_minutes'] += $leftEarlyMinutes;
            }
        }

        return $stats;
    }
}
