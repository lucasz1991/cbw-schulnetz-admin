<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Models\CourseDay;

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

    public function mount(Course $course): void
    {
        // Kurs & Tage laden
        $this->course = $course;
        $this->loadDays();
    }

    protected function loadDays(): void
    {
        $this->course->load(['days']);

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
                ];
            })
            ->values();
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

    public function render()
    {
        return view('livewire.admin.courses.course-days-panel');
    }
}
