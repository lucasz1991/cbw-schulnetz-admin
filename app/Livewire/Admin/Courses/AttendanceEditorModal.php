<?php

namespace App\Livewire\Admin\Courses;

use App\Models\CourseDay;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class AttendanceEditorModal extends Component
{
    public bool $showModal = false;
    public ?int $courseDayId = null;
    public ?int $courseId = null;
    public string $courseTitle = '';
    public string $dayLabel = '';
    public ?string $plannedStart = null;
    public ?string $plannedEnd = null;
    public array $rows = [];
    public array $arrivalInput = [];
    public array $leaveInput = [];
    public array $noteInput = [];
    public array $stats = [
        'present' => 0,
        'late' => 0,
        'excused' => 0,
        'absent' => 0,
        'unknown' => 0,
        'total' => 0,
    ];
    public ?string $syncError = null;

    #[On('openAdminAttendanceEditor')]
    public function open($courseDayId = null): void
    {
        $courseDayId = $this->normalizeCourseDayId($courseDayId);
        abort_unless($courseDayId, 404);

        $day = $this->editableDay($courseDayId);

        $this->resetValidation();
        $this->syncError = null;
        $this->showModal = true;
        $this->courseDayId = $day->id;
        $this->courseId = $day->course_id;
        $this->courseTitle = $day->course->title ?: 'Baustein #'.$day->course_id;
        $this->dayLabel = $day->date->locale('de')->isoFormat('dddd, LL');
        [$this->plannedStart, $this->plannedEnd] = $this->plannedTimes($day);

        try {
            if (! app(CourseDayAttendanceSyncService::class)->loadFromRemote($day)) {
                $this->syncError = 'Die Anwesenheiten konnten nicht vollständig aus UVS aktualisiert werden. Der lokale Stand wird angezeigt.';
            }
        } catch (\Throwable $exception) {
            Log::error('Admin attendance modal: UVS-Load fehlgeschlagen.', [
                'course_day_id' => $day->id,
                'error' => $exception->getMessage(),
            ]);
            $this->syncError = 'UVS ist momentan nicht erreichbar. Der lokale Stand wird angezeigt.';
        }

        $this->loadRowsFromDay($day->fresh(['course.participants']));
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->courseDayId = null;
        $this->courseId = null;
        $this->courseTitle = '';
        $this->dayLabel = '';
        $this->plannedStart = null;
        $this->plannedEnd = null;
        $this->rows = [];
        $this->arrivalInput = [];
        $this->leaveInput = [];
        $this->noteInput = [];
        $this->stats = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent' => 0,
            'unknown' => 0,
            'total' => 0,
        ];
        $this->syncError = null;
        $this->resetValidation();
    }

    public function refreshFromUvs(): void
    {
        $day = $this->editableDay();
        $this->syncError = null;

        try {
            if (! app(CourseDayAttendanceSyncService::class)->loadFromRemote($day)) {
                $this->syncError = 'Die Anwesenheiten konnten nicht aus UVS aktualisiert werden.';
            }
        } catch (\Throwable $exception) {
            Log::error('Admin attendance modal: manuelles UVS-Load fehlgeschlagen.', [
                'course_day_id' => $day->id,
                'error' => $exception->getMessage(),
            ]);
            $this->syncError = 'UVS ist momentan nicht erreichbar.';
        }

        $this->loadRowsFromDay($day->fresh(['course.participants']));
    }

    public function markPresent(int $personId): void
    {
        $this->persistAndSync($personId, [
            'present' => true,
            'excused' => false,
            'late_minutes' => 0,
            'left_early_minutes' => 0,
            'arrived_at' => null,
            'left_at' => null,
            'timestamps' => ['in' => null, 'out' => null],
            'note' => null,
        ]);
    }

    public function markAbsent(int $personId): void
    {
        $this->persistAndSync($personId, [
            'present' => false,
            'excused' => false,
            'late_minutes' => 0,
            'left_early_minutes' => 0,
            'arrived_at' => null,
            'left_at' => null,
            'timestamps' => ['in' => null, 'out' => null],
        ]);
    }

    public function markExcused(int $personId): void
    {
        $this->persistAndSync($personId, [
            'present' => false,
            'excused' => true,
            'late_minutes' => 0,
            'left_early_minutes' => 0,
            'arrived_at' => null,
            'left_at' => null,
            'timestamps' => ['in' => null, 'out' => null],
        ]);
    }

    public function saveArrival(int $personId): void
    {
        $this->saveTimeForPerson($personId, true);
    }

    public function saveLeave(int $personId): void
    {
        $this->saveTimeForPerson($personId, false);
    }

    protected function saveTimeForPerson(int $personId, bool $arrival): void
    {
        $inputProperty = $arrival ? 'arrivalInput' : 'leaveInput';
        $label = $arrival ? 'Kommen-Zeit' : 'Gehen-Zeit';

        $this->validate([
            $inputProperty.'.'.$personId => ['nullable', 'date_format:H:i'],
        ], [
            $inputProperty.'.'.$personId.'.date_format' => "Die {$label} muss im Format HH:MM angegeben werden.",
        ]);

        $day = $this->editableDay();
        $this->assertParticipantBelongsToDay($day, $personId);
        $row = data_get($day->attendance_data, 'participants.'.$personId, []);
        if (! is_array($row) || ! (bool) ($row['present'] ?? false)) {
            $this->syncError = 'Zeiten können nur für anwesende Teilnehmer gespeichert werden.';
            return;
        }

        $time = $this->normalizeTime($this->{$inputProperty}[$personId] ?? null);
        [$plannedStart, $plannedEnd] = $this->plannedDateTimes($day);
        $timeAt = $this->timeOnDay($day, $time);

        if ($arrival) {
            $lateMinutes = ($plannedStart && $timeAt && $timeAt->gt($plannedStart))
                ? $plannedStart->diffInMinutes($timeAt)
                : 0;

            $this->persistAndSync($personId, [
                'arrived_at' => $time,
                'late_minutes' => $lateMinutes,
            ]);

            return;
        }

        $leftEarlyMinutes = ($plannedEnd && $timeAt && $timeAt->lt($plannedEnd))
            ? $timeAt->diffInMinutes($plannedEnd)
            : 0;

        $this->persistAndSync($personId, [
            'left_at' => $time,
            'left_early_minutes' => $leftEarlyMinutes,
        ]);
    }

    public function clearTimes(int $personId): void
    {
        $this->persistAndSync($personId, [
            'arrived_at' => null,
            'left_at' => null,
            'late_minutes' => 0,
            'left_early_minutes' => 0,
            'timestamps' => ['in' => null, 'out' => null],
        ]);
    }

    public function saveNote(int $personId): void
    {
        $this->validate([
            'noteInput.'.$personId => ['nullable', 'string', 'max:1000'],
        ]);

        $this->persistAndSync($personId, [
            'note' => trim((string) ($this->noteInput[$personId] ?? '')),
        ]);
    }

    protected function persistAndSync(int $personId, array $patch): void
    {
        $day = $this->editableDay();
        $this->assertParticipantBelongsToDay($day, $personId);
        $this->syncError = null;
        $patch['state'] = CourseDayAttendanceSyncService::STATE_DIRTY;

        $day->setAttendance($personId, $patch);
        $day->refresh();

        try {
            $synced = app(CourseDayAttendanceSyncService::class)->syncToRemote($day, [$personId]);
            if (! $synced) {
                $this->syncError = 'Die Änderung wurde lokal vorgemerkt, aber noch nicht vollständig mit UVS synchronisiert.';
            }
        } catch (\Throwable $exception) {
            Log::error('Admin attendance modal: Speichern fehlgeschlagen.', [
                'course_day_id' => $day->id,
                'person_id' => $personId,
                'error' => $exception->getMessage(),
            ]);
            $this->syncError = 'Die Änderung wurde lokal vorgemerkt. UVS ist momentan nicht erreichbar.';
        }

        $freshDay = $day->fresh(['course.participants']);
        $this->loadRowsFromDay($freshDay);
        $this->dispatch('adminAttendanceUpdated', courseDayId: $day->id);
    }

    protected function editableDay(?int $courseDayId = null): CourseDay
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        Gate::authorize('courses.attendance.edit_today');

        $id = $courseDayId ?? $this->courseDayId;
        abort_unless($id, 404);

        return CourseDay::query()
            ->with(['course.participants', 'course.tutor'])
            ->findOrFail($id);
    }

    protected function normalizeCourseDayId(mixed $payload): ?int
    {
        while (is_array($payload)) {
            $payload = $payload['courseDayId'] ?? (count($payload) === 1 ? reset($payload) : null);
        }

        if (! is_numeric($payload) || (int) $payload <= 0) {
            return null;
        }

        return (int) $payload;
    }

    protected function assertParticipantBelongsToDay(CourseDay $day, int $personId): void
    {
        abort_unless(
            $day->course->participants->contains(fn ($person) => (int) $person->id === $personId),
            404
        );
    }

    protected function loadRowsFromDay(CourseDay $day): void
    {
        $participantsData = data_get($day->attendance_data, 'participants', []);
        if (! is_array($participantsData)) {
            $participantsData = [];
        }

        $this->rows = $day->course->participants
            ->sortBy(fn ($person) => mb_strtolower(trim(($person->nachname ?? '').' '.($person->vorname ?? ''))))
            ->map(function ($person) use ($participantsData): array {
                $personId = (int) $person->id;
                $hasEntry = array_key_exists($personId, $participantsData)
                    || array_key_exists((string) $personId, $participantsData);
                $row = $participantsData[$personId] ?? $participantsData[(string) $personId] ?? [];
                $row = is_array($row) ? $row : [];

                $this->arrivalInput[$personId] = $this->normalizeTime($row['arrived_at'] ?? null);
                $this->leaveInput[$personId] = $this->normalizeTime($row['left_at'] ?? null);
                $this->noteInput[$personId] = (string) ($row['note'] ?? '');

                return [
                    'id' => $personId,
                    'name' => trim(trim((string) ($person->nachname ?? '')).', '.trim((string) ($person->vorname ?? '')), ', '),
                    'teilnehmer_id' => $person->teilnehmer_id,
                    'has_entry' => $hasEntry,
                    'present' => (bool) ($row['present'] ?? false),
                    'excused' => (bool) ($row['excused'] ?? false),
                    'late_minutes' => max(0, (int) ($row['late_minutes'] ?? 0)),
                    'left_early_minutes' => max(0, (int) ($row['left_early_minutes'] ?? 0)),
                    'arrived_at' => $this->displayTime($row['arrived_at'] ?? null, true, $row),
                    'left_at' => $this->displayTime($row['left_at'] ?? null, false, $row),
                    'note' => (string) ($row['note'] ?? ''),
                    'state' => $row['state'] ?? null,
                ];
            })
            ->values()
            ->all();

        $this->stats = $this->calculateStats($this->rows);
    }

    protected function calculateStats(array $rows): array
    {
        $stats = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent' => 0,
            'unknown' => 0,
            'total' => count($rows),
        ];

        foreach ($rows as $row) {
            if (! ($row['has_entry'] ?? false)) {
                $stats['unknown']++;
            } elseif ($row['excused'] ?? false) {
                $stats['excused']++;
            } elseif (($row['present'] ?? false) && (int) ($row['late_minutes'] ?? 0) > 0) {
                $stats['late']++;
            } elseif ($row['present'] ?? false) {
                $stats['present']++;
            } else {
                $stats['absent']++;
            }
        }

        return $stats;
    }

    protected function displayTime(mixed $directTime, bool $arrival, array $row): ?string
    {
        $normalized = $this->normalizeTime($directTime);
        if ($normalized) {
            return $normalized;
        }

        $minutes = max(0, (int) ($row[$arrival ? 'late_minutes' : 'left_early_minutes'] ?? 0));
        $boundary = $arrival ? $this->plannedStart : $this->plannedEnd;
        if ($minutes <= 0 || ! $boundary) {
            return null;
        }

        try {
            $time = Carbon::createFromFormat('H:i', $boundary, 'Europe/Berlin');
            return ($arrival ? $time->addMinutes($minutes) : $time->subMinutes($minutes))->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function plannedTimes(CourseDay $day): array
    {
        [$start, $end] = $this->plannedDateTimes($day);

        return [$start?->format('H:i'), $end?->format('H:i')];
    }

    protected function plannedDateTimes(CourseDay $day): array
    {
        $date = $day->date->toDateString();
        $start = $this->boundaryOnDay($date, $day->start_time);
        $end = $this->boundaryOnDay($date, $day->end_time);

        if (! $end && $start && (float) ($day->std ?? 0) > 0) {
            $end = $start->copy()->addMinutes((int) round(((float) $day->std) * 60));
        }

        return [$start, $end];
    }

    protected function boundaryOnDay(string $date, mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $time = $value instanceof Carbon
                ? $value->format('H:i')
                : Carbon::parse((string) $value)->format('H:i');

            return Carbon::parse($date.' '.$time, 'Europe/Berlin');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function timeOnDay(CourseDay $day, ?string $time): ?Carbon
    {
        return $time ? Carbon::parse($day->date->toDateString().' '.$time, 'Europe/Berlin') : null;
    }

    protected function normalizeTime(mixed $value): ?string
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
        return view('livewire.admin.courses.attendance-editor-modal');
    }
}
