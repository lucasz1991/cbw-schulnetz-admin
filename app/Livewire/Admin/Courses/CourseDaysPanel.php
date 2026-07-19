<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use App\Models\File;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Models\CourseDay;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class CourseDaysPanel extends Component
{
    public Course $course;

    /** 
     * Aufbereitete Tage mit Meta (Doku + Anwesenheit)
     * [
     *   [
     *     'id'           => int,
     *     'date'         => Carbon,
     *     'start_time'   => string|null,
     *     'end_time'     => string|null,
     *     'room'         => string|null,
     *     'notes_html'   => string|null,
     *     'attendance'   => [
     *         'total'     => int,
     *         'present'   => int,
     *         'absent'    => int,
     *         'excused'   => int,
     *         'left_early'=> int,
     *     ],
     *   ],
     *   ...
     * ]
     */
    public Collection $days;
    public ?int $classRepresentativeUserId = null;
    public ?Person $classRepresentativePerson = null;
    public ?int $signedByUserId = null;
    public ?Person $signedByPerson = null;
    public ?string $signedAt = null;
    public ?File $dokuSignatureFile = null;
    public bool $dokuSigned = false;
    public bool $allDaysCompleted = false;

    public function mount(Course $course): void
    {
        // Kurs & Tage laden
        $this->course = $course;
        $this->loadDays();
        $this->loadDokuSignatureMeta();
    }

    protected function loadDays(): void
    {
        $this->course->load(['days', 'participants']);

        $this->days = $this->course->days
            ->sortBy(fn ($d) => [$d->date, $d->start_time])
            ->map(function ($day) {
                // Datum als Carbon
                $date = $day->date instanceof Carbon
                    ? $day->date
                    : Carbon::parse($day->date);

                // Zeiten als H:i
                $start = $day->start_time
                    ? Carbon::parse($day->start_time)->format('H:i')
                    : null;

                $end = $day->end_time
                    ? Carbon::parse($day->end_time)->format('H:i')
                    : null;

                // Doku-Text (HTML)
                $notesHtml = $day->notes ?? null;
                $hasNotes  = filled($day->notes);
                $noteStatus = (int)($day->note_status ?? CourseDay::NOTE_STATUS_MISSING);
                $noteStatusMeta = $this->mapNoteStatus($noteStatus);

                // Anwesenheits-Statistik aus attendance_data JSON
                $attendance = $this->buildAttendanceSummary($day->attendance_data ?? []);
                $hasAttendance = ($attendance['total'] ?? 0) > 0;
                $attendanceRows = $this->buildAttendanceRows($day);

                return [
                    'id'         => $day->id,
                    'date'       => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'room'       => $day->room ?? null,
                    'notes_html' => $notesHtml,
                    'has_notes'  => $hasNotes,
                    'note_status' => $noteStatus,
                    'note_status_label' => $noteStatusMeta['label'],
                    'note_status_classes' => $noteStatusMeta['classes'],
                    'has_attendance' => $hasAttendance,
                    'attendance' => $attendance,
                    'attendance_rows' => $attendanceRows,
                    'is_today' => $date->isSameDay(Carbon::today('Europe/Berlin')),
                ];
            })
            ->values();

        $this->allDaysCompleted = $this->days->isNotEmpty()
            && $this->days->every(fn ($day) => (int) ($day['note_status'] ?? CourseDay::NOTE_STATUS_MISSING) === CourseDay::NOTE_STATUS_COMPLETED);
    }

    protected function loadDokuSignatureMeta(): void
    {
        $settings = is_array($this->course->settings) ? $this->course->settings : [];

        $this->dokuSignatureFile = $this->course->files()
            ->where('type', 'sign_course_doku_participant')
            ->latest('id')
            ->first();

        $fileUserId = $this->dokuSignatureFile?->user_id ? (int) $this->dokuSignatureFile->user_id : null;
        $fileSignedAt = $this->dokuSignatureFile?->created_at?->toIso8601String();

        // Fallback-Kette: Settings -> Signaturdatei
        $this->signedByUserId = data_get($settings, 'course_doku_acknowledged_user_id') ?: $fileUserId;
        $this->signedAt = data_get($settings, 'course_doku_acknowledged_at') ?: $fileSignedAt;
        $this->classRepresentativeUserId = data_get($settings, 'class_representative_user_id') ?: $this->signedByUserId;

        $this->classRepresentativePerson = $this->resolvePersonByUserId($this->classRepresentativeUserId);
        $this->signedByPerson = $this->resolvePersonByUserId($this->signedByUserId);

        $this->dokuSigned = (bool) $this->dokuSignatureFile;
    }

    protected function resolvePersonByUserId(?int $userId): ?Person
    {
        if (! $userId) {
            return null;
        }

        return Person::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first();
    }

    protected function mapNoteStatus(int $status): array
    {
        return match ($status) {
            CourseDay::NOTE_STATUS_DRAFT => [
                'label'   => 'Entwurf',
                'classes' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ],
            CourseDay::NOTE_STATUS_COMPLETED => [
                'label'   => 'Fertiggestellt',
                'classes' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            ],
            default => [
                'label'   => 'Fehlt',
                'classes' => 'bg-neutral-50 text-neutral-500 border border-neutral-200',
            ],
        };
    }

    /**
     * attendance_data-Struktur:
     * [
     *   'participants' => [
     *      person_id => [
     *          'present'            => bool,
     *          'excused'            => bool,
     *          'left_early_minutes' => int,
     *      ],
     *      ...
     *   ]
     * ]
     */
    protected function buildAttendanceSummary(array $attendanceData): array
    {
        $map = Arr::get($attendanceData, 'participants', []);

        $total     = 0;
        $present   = 0;
        $absent    = 0;
        $excused   = 0;
        $leftEarly = 0;

        foreach ($map as $row) {
            $total++;

            $isPresent = (bool)($row['present'] ?? false);
            $isExcused = (bool)($row['excused'] ?? false);
            $leftEarlyMinutes = (int)($row['left_early_minutes'] ?? 0);

            if ($isExcused) {
                $excused++;
                continue;
            }

            if ($isPresent) {
                $present++;
            } else {
                $absent++;
            }

            if ($leftEarlyMinutes > 0) {
                $leftEarly++;
            }
        }

        return [
            'total'      => $total,
            'present'    => $present,
            'absent'     => $absent,
            'excused'    => $excused,
            'left_early' => $leftEarly,
        ];
    }

    public function openAttendanceEditor(int $courseDayId): void
    {
        Gate::authorize('courses.attendance.edit_today');

        $day = CourseDay::query()
            ->where('course_id', $this->course->id)
            ->whereDate('date', Carbon::today('Europe/Berlin')->toDateString())
            ->findOrFail($courseDayId);

        $this->dispatch('openAdminAttendanceEditor', courseDayId: $day->id);
    }

    #[On('adminAttendanceUpdated')]
    public function refreshAttendance(int $courseDayId): void
    {
        if ($this->course->days()->whereKey($courseDayId)->exists()) {
            $this->course->unsetRelation('days');
            $this->course->unsetRelation('participants');
            $this->loadDays();
        }
    }

    protected function buildAttendanceRows(CourseDay $day): array
    {
        $map = data_get($day->attendance_data, 'participants', []);
        if (! is_array($map)) {
            $map = [];
        }

        [$plannedStart, $plannedEnd] = $this->plannedTimes($day);

        return $this->course->participants
            ->sortBy(fn ($person) => mb_strtolower(trim(($person->nachname ?? '').' '.($person->vorname ?? ''))))
            ->map(function ($person) use ($map, $plannedStart, $plannedEnd): array {
                $personId = (int) $person->id;
                $hasEntry = array_key_exists($personId, $map) || array_key_exists((string) $personId, $map);
                $row = $map[$personId] ?? $map[(string) $personId] ?? [];
                $row = is_array($row) ? $row : [];
                $lateMinutes = max(0, (int) ($row['late_minutes'] ?? 0));
                $leftEarlyMinutes = max(0, (int) ($row['left_early_minutes'] ?? 0));

                return [
                    'id' => $personId,
                    'name' => trim(trim((string) ($person->nachname ?? '')).', '.trim((string) ($person->vorname ?? '')), ', '),
                    'has_entry' => $hasEntry,
                    'present' => (bool) ($row['present'] ?? false),
                    'excused' => (bool) ($row['excused'] ?? false),
                    'late_minutes' => $lateMinutes,
                    'left_early_minutes' => $leftEarlyMinutes,
                    'arrived_at' => $this->displayAttendanceTime($row['arrived_at'] ?? null, $plannedStart, $lateMinutes, true),
                    'left_at' => $this->displayAttendanceTime($row['left_at'] ?? null, $plannedEnd, $leftEarlyMinutes, false),
                ];
            })
            ->values()
            ->all();
    }

    protected function plannedTimes(CourseDay $day): array
    {
        $start = $this->normalizeClock($day->start_time);
        $end = $this->normalizeClock($day->end_time);

        if (! $end && $start && (float) ($day->std ?? 0) > 0) {
            $end = Carbon::createFromFormat('H:i', $start, 'Europe/Berlin')
                ->addMinutes((int) round(((float) $day->std) * 60))
                ->format('H:i');
        }

        return [$start, $end];
    }

    protected function displayAttendanceTime(
        mixed $directTime,
        ?string $boundary,
        int $minutes,
        bool $arrival
    ): ?string {
        $normalized = $this->normalizeClock($directTime);
        if ($normalized) {
            return $normalized;
        }
        if (! $boundary || $minutes <= 0) {
            return null;
        }

        try {
            $time = Carbon::createFromFormat('H:i', $boundary, 'Europe/Berlin');

            return ($arrival ? $time->addMinutes($minutes) : $time->subMinutes($minutes))->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeClock(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || in_array($value, ['00:00', '00:00:00', '0:00'], true)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.admin.courses.course-days-panel');
    }
}
