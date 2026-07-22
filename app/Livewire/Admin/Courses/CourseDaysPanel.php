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
                $addendumHtml = (string) ($day->documentation_addendum ?? '');
                $hasAddendum = trim(strip_tags(html_entity_decode($addendumHtml))) !== '';
                $addendumStatus = (int) (
                    $day->documentation_addendum_status ?? CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT
                );
                $addendumStatusMeta = $this->mapDocumentationAddendumStatus($addendumStatus, $hasAddendum);
                $hasPublishedAddendum = $day->hasPublishedDocumentationAddendum();

                // Anwesenheits-Statistik aus attendance_data JSON
                $attendance = $this->buildAttendanceSummary($day->attendance_data ?? []);
                $hasAttendance = ($attendance['total'] ?? 0) > 0;
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
                    'documentation_addendum_html' => $hasPublishedAddendum ? $addendumHtml : null,
                    'has_documentation_addendum' => $hasAddendum,
                    'has_published_documentation_addendum' => $hasPublishedAddendum,
                    'documentation_addendum_status' => $addendumStatus,
                    'documentation_addendum_status_label' => $addendumStatusMeta['label'],
                    'documentation_addendum_status_classes' => $addendumStatusMeta['classes'],
                    'has_attendance' => $hasAttendance,
                    'attendance' => $attendance,
                    'participants_count' => $this->course->participants->count(),
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

    protected function mapDocumentationAddendumStatus(int $status, bool $hasAddendum): array
    {
        if (! $hasAddendum) {
            return [
                'label' => 'Kein Zusatz',
                'classes' => 'bg-neutral-50 text-neutral-500 border border-neutral-200',
            ];
        }

        if ($status === CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED) {
            return [
                'label' => 'Veröffentlicht',
                'classes' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            ];
        }

        return [
            'label' => 'Entwurf',
            'classes' => 'bg-amber-50 text-amber-700 border border-amber-200',
        ];
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
            ->findOrFail($courseDayId);

        $this->dispatch('openAdminAttendanceEditor', (int) $day->id);
    }

    public function openDocumentationAddendumEditor(int $courseDayId): void
    {
        Gate::authorize('courses.documentation_addendum.edit');

        $day = CourseDay::query()
            ->where('course_id', $this->course->id)
            ->findOrFail($courseDayId);

        $this->dispatch('openAdminDocumentationAddendumEditor', (int) $day->id);
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

    #[On('adminDocumentationAddendumUpdated')]
    public function refreshDocumentationAddendum(int $courseDayId): void
    {
        if ($this->course->days()->whereKey($courseDayId)->exists()) {
            $this->course->unsetRelation('days');
            $this->loadDays();
        }
    }

    public function render()
    {
        return view('livewire.admin.courses.course-days-panel');
    }
}
