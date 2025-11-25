<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Carbon\Carbon;

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

                // Anwesenheits-Statistik aus attendance_data JSON
                $attendance = $this->buildAttendanceSummary($day->attendance_data ?? []);

                return [
                    'id'         => $day->id,
                    'date'       => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'room'       => $day->room ?? null,
                    'notes_html' => $notesHtml,
                    'attendance' => $attendance,
                ];
            })
            ->values();
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
