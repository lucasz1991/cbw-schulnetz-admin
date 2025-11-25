<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Collection;

class CourseParticipantsPanel extends Component
{
    public Course $course;
    public Collection $rows;

    public function mount(Course $course): void
    {
        $this->course = $course;
        $this->buildRows();
    }

    protected function buildRows(): void
    {
        $this->course->loadMissing([
            'participants',
            'materialAcknowledgements',
        ]);

        $acks = $this->course->materialAcknowledgements
            ->sortByDesc('acknowledged_at')
            ->groupBy('person_id');

        $this->rows = $this->course->participants
            ->sortBy(fn($p) => mb_strtoupper($p->nachname ?? $p->last_name ?? ''))
            ->values()
            ->map(function ($person) use ($acks) {

                // Materialbestätigung
                $ackList = $acks->get($person->id);
                $latestAck = $ackList?->first();
                $hasConfirmation = (bool) $latestAck;
                $confirmedAt = $latestAck?->acknowledged_at;

                // Prüfungsdaten aus enrollment->results
                $enrollment = $person->enrollment ?? null;
                $results = $enrollment?->results ?? null;

                [$examLabel, $examState] = $this->examMeta($results);

                return [
                    'person' => $person,
                    'has_confirmation' => $hasConfirmation,
                    'confirmation_at'  => $confirmedAt,
                    'exam_label'       => $examLabel,
                    'exam_state'       => $examState,
                ];
            });
    }

    protected function examMeta($results): array
    {
        if (!is_array($results) && !($results instanceof \ArrayAccess)) {
            return [null, null];
        }

        $result = data_get($results, 'exam.result')
            ?? data_get($results, 'result')
            ?? data_get($results, 'exam_result');

        $grade = data_get($results, 'exam.grade')
            ?? data_get($results, 'grade');

        if (!$result && !$grade) return [null, null];

        $label = trim(($result ?: '') . ($grade ? ' · Note '.$grade : ''));

        $lower = mb_strtolower($result ?? '');

        $state = match(true) {
            str_contains($lower, 'bestanden'), str_contains($lower, 'passed') => 'passed',
            str_contains($lower, 'nicht'), str_contains($lower, 'fail')       => 'failed',
            default => 'neutral',
        };

        return [$label, $state];
    }

    public function render()
    {
        return view('livewire.admin.courses.course-participants-panel');
    }
}
