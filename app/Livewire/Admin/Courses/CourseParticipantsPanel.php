<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\CourseRating;
use Illuminate\Support\Collection;

class CourseParticipantsPanel extends Component
{
    public Course $course;

    /** @var \Illuminate\Support\Collection<int, array> */
    public Collection $rows;

    public function mount(Course $course): void
    {
        $this->course = $course;
        $this->rows   = collect();

        $this->buildRows();
    }

    /**
     * Baut die Datenstruktur für das Teilnehmer-Panel:
     * - Materialbestätigung
     * - Prüfungsergebnis
     * - Kursbewertung (genau 1 Rating pro User & Kurs)
     */
    protected function buildRows(): void
    {
        $this->course->loadMissing([
            'participants',
            'materialAcknowledgements',
            'results',
            'ratings',
        ]);

        // Materialbestätigungen pro Person
        $acks = $this->course->materialAcknowledgements
            ->sortByDesc('acknowledged_at')
            ->groupBy('person_id');

        // Prüfungsresultate pro Person
        $resultsByPerson = $this->course->results
            ->sortByDesc('updated_at')
            ->keyBy('person_id');

        // EIN Rating pro User -> direkt keyBy('user_id')
        /** @var Collection<int,CourseRating> $ratingsByUser */
        $ratingsByUser = $this->course->ratings
            ->sortByDesc('created_at')
            ->keyBy('user_id');

        $this->rows = $this->course->participants
            ->sortBy(fn ($p) => mb_strtoupper($p->nachname ?? $p->last_name ?? ''))
            ->values()
            ->map(function ($person) use ($acks, $resultsByPerson, $ratingsByUser) {

                // Materialbestätigung
                $ackList         = $acks->get($person->id);
                $latestAck       = $ackList?->first();
                $hasConfirmation = (bool) $latestAck;
                $confirmedAt     = $latestAck?->acknowledged_at;

                // Prüfungsresultat (CourseResult)
                /** @var CourseResult|null $courseResult */
                $courseResult = $resultsByPerson->get($person->id);
                [$examLabel, $examState] = $this->examMetaFromCourseResult($courseResult);

                // Kurs-Rating über user_id
                $userId = $person->user_id ?? null;

                /** @var CourseRating|null $rating */
                $rating    = $userId ? $ratingsByUser->get($userId) : null;
                $ratingAvg = $rating?->average_score;
                $ratingAt  = $rating?->created_at;

                return [
                    'person'            => $person,

                    'has_confirmation'  => $hasConfirmation,
                    'confirmation_at'   => $confirmedAt,

                    'exam_label'        => $examLabel,
                    'exam_state'        => $examState,

                    'rating'            => $rating,
                    'rating_avg'        => $ratingAvg,
                    'rating_at'         => $ratingAt,
                ];
            });
    }

    /**
     * Leitet aus einem CourseResult Label + Status ab.
     */
    protected function examMetaFromCourseResult(?CourseResult $result): array
    {
        if (!$result) {
            return [null, null];
        }

        $label = $result->result ?? null;

        if (!$label) {
            return [null, null];
        }

        // Status-Text mit in die Heuristik packen
        $lower = mb_strtolower(trim(
            ($result->result ?? '') . ' ' . ($result->status ?? '')
        ));

        $state = match (true) {
            str_contains($lower, 'bestanden'),
            str_contains($lower, 'passed')          => 'passed',

            str_contains($lower, 'nicht bestanden'),
            str_contains($lower, 'nicht'),
            str_contains($lower, 'fail'),
            str_contains($lower, 'failed')          => 'failed',

            default                                 => 'neutral',
        };

        return [$label, $state];
    }

    public function render()
    {
        return view('livewire.admin.courses.course-participants-panel');
    }
}
