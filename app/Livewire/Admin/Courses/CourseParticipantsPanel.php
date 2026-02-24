<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use App\Models\Person;
use App\Models\CourseResult;
use App\Models\CourseRating;
use Illuminate\Support\Collection;

class CourseParticipantsPanel extends Component
{
    public Course $course;
    public int $examResultsCount = 0;

    /** @var \Illuminate\Support\Collection<int, array> */
    public Collection $rows;

    public function mount(Course $course): void
    {
        $this->course = $course;
        $this->rows   = collect();

        $this->buildRows();
    }

    /**
     * Baut die Datenstruktur fuer das Teilnehmer-Panel:
     * - Materialbestaetigung
     * - Pruefungsergebnisse (alle Eintraege pro Teilnehmer)
     * - Kursbewertung (genau 1 Rating pro User & Kurs)
     */
    protected function buildRows(): void
    {
        $this->course->load([
            'participants',
            'materialAcknowledgements',
            'results',
            'ratings',
        ]);

        $this->examResultsCount = $this->course->results->count();

        // Materialbestaetigungen pro Person
        $acks = $this->course->materialAcknowledgements
            ->sortByDesc('acknowledged_at')
            ->groupBy('person_id');

        // Pruefungsresultate pro Person (alle Eintraege, neuester zuerst)
        $resultsByPerson = $this->course->results
            ->sortByDesc('updated_at')
            ->groupBy('person_id');

        // EIN Rating pro User -> direkt keyBy('user_id')
        /** @var Collection<int,CourseRating> $ratingsByUser */
        $ratingsByUser = $this->course->ratings
            ->sortByDesc('created_at')
            ->keyBy('user_id');

        $this->rows = $this->course->participants
            ->sortBy(fn ($p) => mb_strtoupper($p->nachname ?? $p->last_name ?? ''))
            ->values()
            ->map(function ($person) use ($acks, $resultsByPerson, $ratingsByUser) {

                // Materialbestaetigung
                $ackList         = $acks->get($person->id);
                $latestAck       = $ackList?->first();
                $hasConfirmation = (bool) $latestAck;
                $confirmedAt     = $latestAck?->acknowledged_at;

                // Pruefungsresultate
                /** @var Collection<int, CourseResult> $personResults */
                $personResults = ($resultsByPerson->get($person->id) ?? collect())->values();

                /** @var CourseResult|null $courseResult */
                $courseResult = $personResults->first();
                [$examLabel, $examState] = $this->examMetaFromCourseResult($courseResult);
                [$examStatusCode, $examStatusLabel, $examStatusClass, $examStatusIcon] = $this->examStatusMeta($courseResult);

                $examEntries = $personResults->map(function (CourseResult $result, int $index) {
                    $resultText = is_string($result->result) ? trim($result->result) : $result->result;
                    $statusText = is_string($result->status) ? trim($result->status) : $result->status;

                    return [
                        'id'               => $result->id,
                        'result'           => $resultText,
                        'status'           => $statusText,
                        'updated_at'       => $result->updated_at,
                        'is_latest'        => $index === 0,
                        'is_result_filled' => is_string($resultText)
                            ? $resultText !== ''
                            : ($resultText !== null),
                    ];
                });

                // Kurs-Rating ueber user_id
                $userId = $person->user_id ?? null;

                /** @var CourseRating|null $rating */
                $rating    = $userId ? $ratingsByUser->get($userId) : null;
                $ratingAvg = $rating?->average_score;
                $ratingAt  = $rating?->created_at;

                return [
                    'person'             => $person,

                    'has_confirmation'   => $hasConfirmation,
                    'confirmation_at'    => $confirmedAt,

                    'exam_label'         => $examLabel,
                    'exam_state'         => $examState,
                    'exam_status_code'   => $examStatusCode,
                    'exam_status_label'  => $examStatusLabel,
                    'exam_status_class'  => $examStatusClass,
                    'exam_status_icon'   => $examStatusIcon,
                    'exam_entries'       => $examEntries,
                    'exam_entries_count' => $examEntries->count(),

                    'rating'             => $rating,
                    'rating_avg'         => $ratingAvg,
                    'rating_at'          => $ratingAt,
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

        $label = $result->result;

        if (is_string($label)) {
            $label = trim($label);
        }

        if ($label === null || $label === '') {
            return [null, null];
        }

        if (is_numeric($label)) {
            return [$label . ' Punkte', 'neutral'];
        }

        $lower = mb_strtolower((string) $label);

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

    protected function examStatusMeta(?CourseResult $result): array
    {
        if (! $result) {
            return [null, null, null, null];
        }

        $statusCode = $this->normalizeExamStatus($result->status, $result->result);

        if ($statusCode === null) {
            return [null, null, null, null];
        }

        return match ($statusCode) {
            '+' => ['+', 'Teilgenommen', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fal fa-check-circle'],
            '-' => ['-', 'Nicht teilgenommen', 'bg-amber-50 text-amber-700 border-amber-200', 'fal fa-user-slash'],
            'V' => ['V', 'Betrugsversuch', 'bg-red-50 text-red-700 border-red-200', 'fal fa-ban'],
            default => [null, null, null, null],
        };
    }

    protected function normalizeExamStatus(mixed $status, mixed $result): ?string
    {
        $raw = is_string($status) ? trim($status) : '';
        $lower = mb_strtolower($raw);

        if ($raw === 'V' || in_array($lower, ['v', 'betrug', 'betrugsversuch'], true)) {
            return 'V';
        }

        if ($raw === '-' || in_array($lower, ['-', 'nicht_teilgenommen', 'nicht teilgenommen', 'nt', 'not_participated', '3'], true)) {
            return '-';
        }

        if ($raw === '+' || in_array($lower, ['+', 'teilgenommen', 'an prüfung teilgenommen', 'bestanden', 'passed', '1'], true)) {
            return '+';
        }

        if ($result !== null && $result !== '') {
            return '+';
        }

        return null;
    }

    public function triggerPersonApiUpdate(int $personId): void
    {
        $person = Person::find($personId);

        if (! $person) {
            $this->dispatch('showAlert', 'Person nicht gefunden.', 'error');
            return;
        }

        try {
            $person->apiupdate();
            $this->dispatch('showAlert', 'Person API Update wurde gestartet.', 'success');
        } catch (\Throwable $e) {
            \Log::error('Person API Update konnte nicht gestartet werden.', [
                'person_id' => $personId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('showAlert', 'Person API Update konnte nicht gestartet werden.', 'error');
        }
    }

    public function deleteAllCourseResults(): void
    {
        try {
            $deleted = CourseResult::query()
                ->where('course_id', $this->course->id)
                ->delete();

            $this->buildRows();

            $this->dispatch(
                'showAlert',
                "Prüfungsergebnisse gelöscht: {$deleted}",
                'success'
            );
        } catch (\Throwable $e) {
            \Log::error('Pruefungsergebnisse konnten nicht geloescht werden.', [
                'course_id' => $this->course->id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch(
                'showAlert',
                'Prüfungsergebnisse konnten nicht gelöscht werden.',
                'error'
            );
        }
    }

    public function render()
    {
        return view('livewire.admin.courses.course-participants-panel');
    }
}
