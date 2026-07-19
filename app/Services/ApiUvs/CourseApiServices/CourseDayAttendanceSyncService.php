<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\CourseDay;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CourseDayAttendanceSyncService
{
    protected const ENDPOINT_SYNC = '/api/course/courseday/syncattendancedata';

    public const STATE_DIRTY = 'dirty';
    public const STATE_LOCAL = 'local';
    public const STATE_SYNCED = 'synced';

    public function __construct(
        protected CourseUvsDirectLoadService $directLoadService,
        protected ApiUvsService $apiUvsService
    ) {
    }

    public function syncToRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        $day->loadMissing(['course.participants', 'course.tutor']);

        if (! $day->course || ! $day->course->termin_id || ! $day->date) {
            Log::warning('Admin attendance sync: Unterrichtseinheit ist nicht synchronisierbar.', [
                'day_id' => $day->id,
                'course_id' => $day->course_id,
            ]);

            return false;
        }

        [$changes, $pushedLocalIds, $fullyPresentLocalIds] = $this->mapChanges(
            $day,
            $onlyLocalPersonIds
        );
        $teilnehmerIds = $this->collectTeilnehmerIds($day, $onlyLocalPersonIds);

        if (empty($teilnehmerIds)) {
            Log::warning('Admin attendance sync: Keine UVS-Teilnehmer-ID für den Zeilen-Sync vorhanden.', [
                'day_id' => $day->id,
                'person_ids' => $onlyLocalPersonIds,
            ]);

            return false;
        }

        $response = $this->apiUvsService->request('POST', self::ENDPOINT_SYNC, [
            'termin_id' => (string) $day->course->termin_id,
            'date' => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
            'changes' => $changes,
        ]);

        if (empty($response['ok'])) {
            Log::error('Admin attendance sync: UVS-Response nicht erfolgreich.', [
                'day_id' => $day->id,
                'response' => $response,
            ]);

            return false;
        }

        $this->markRowsAfterPush($day, $pushedLocalIds, $fullyPresentLocalIds);

        // Der bestehende Admin-Loader ersetzt attendance_data vollständig. Nach
        // einem Row-Push deshalb immer den kompletten Tag laden, damit andere
        // Teilnehmer nicht durch einen leeren Teil-Response verloren gehen.
        if (! $this->directLoadService->loadAttendanceForDay($day)) {
            Log::warning('Admin attendance sync: Push erfolgreich, anschliessender UVS-Load fehlgeschlagen.', [
                'day_id' => $day->id,
                'person_ids' => $onlyLocalPersonIds,
            ]);

            // Der Push wurde von UVS bereits bestätigt. Der lokale, als
            // synchronisiert markierte Zeilenstand bleibt deshalb erhalten.
            return true;
        }

        return true;
    }

    public function loadFromRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        return $this->directLoadService->loadAttendanceForDay($day, $onlyLocalPersonIds);
    }

    protected function mapChanges(CourseDay $day, ?array $onlyLocalPersonIds): array
    {
        $participants = data_get($day->attendance_data, 'participants', []);
        if (! is_array($participants)) {
            return [[], [], []];
        }

        $only = is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)
            ? array_map('intval', $onlyLocalPersonIds)
            : null;
        $localIds = array_map('intval', array_keys($participants));
        if ($only !== null) {
            $localIds = array_values(array_intersect($localIds, $only));
        }

        $persons = $day->course->participants
            ->filter(fn ($person) => in_array((int) $person->id, $localIds, true))
            ->keyBy('id');
        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day);
        $terminId = (string) $day->course->termin_id;
        $date = $day->date->toDateString();
        $tutorName = $day->course->tutor
            ? trim(($day->course->tutor->vorname ?? '').' '.($day->course->tutor->nachname ?? '').' (Schulnetz)')
            : 'Schulnetz Admin';

        $changes = [];
        $pushedLocalIds = [];
        $fullyPresentLocalIds = [];

        foreach ($participants as $localPersonId => $row) {
            $localPersonId = (int) $localPersonId;
            if ($only !== null && ! in_array($localPersonId, $only, true)) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }

            $person = $persons->get($localPersonId);
            if (! $person || ! $person->teilnehmer_id) {
                continue;
            }

            $teilnehmerId = (string) $person->teilnehmer_id;
            $institutId = (int) ($person->institut_id ?? ($day->course->institut_id ?? 0));
            $present = (bool) ($row['present'] ?? false);
            $excused = (bool) ($row['excused'] ?? false);
            $lateMinutes = max(0, (int) ($row['late_minutes'] ?? 0));
            $leftEarlyMinutes = max(0, (int) ($row['left_early_minutes'] ?? 0));
            $isFullyPresent = $present && ! $excused && $lateMinutes === 0 && $leftEarlyMinutes === 0;

            if ($isFullyPresent) {
                $fullyPresentLocalIds[] = $localPersonId;

                if (empty($row['src_api_id'])) {
                    continue;
                }

                $changes[] = $this->baseChange($teilnehmerId, $institutId, $terminId, $date, $tutorName) + [
                    'fehl_grund' => '',
                    'fehl_bem' => '',
                    'gekommen' => '00:00',
                    'gegangen' => '00:00',
                    'fehl_std' => 0.0,
                    'action' => 'delete',
                ];
                $pushedLocalIds[] = $localPersonId;
                continue;
            }

            if ($present) {
                $gekommen = $this->resolveTimeForPush($row, 'arrived_at', 'in', $courseStart);
                $gegangen = $this->resolveTimeForPush($row, 'left_at', 'out', $courseEnd);
                $fehlStd = $totalMinutes > 0
                    ? round(($lateMinutes + $leftEarlyMinutes) / 60, 2)
                    : 0.0;
            } else {
                $gekommen = '00:00';
                $gegangen = '00:00';
                $fehlStd = (float) ($day->std ?? ($totalMinutes / 60));
            }

            $changes[] = $this->baseChange($teilnehmerId, $institutId, $terminId, $date, $tutorName) + [
                'fehl_grund' => $this->mapReasonCode($present, $excused, $lateMinutes, $leftEarlyMinutes),
                'fehl_bem' => $this->normalizeNote($row['note'] ?? null),
                'gekommen' => $gekommen,
                'gegangen' => $gegangen,
                'fehl_std' => (float) $fehlStd,
                'action' => 'update',
            ];
            $pushedLocalIds[] = $localPersonId;
        }

        return [
            $changes,
            array_values(array_unique($pushedLocalIds)),
            array_values(array_unique($fullyPresentLocalIds)),
        ];
    }

    protected function baseChange(
        string $teilnehmerId,
        int $institutId,
        string $terminId,
        string $date,
        string $tutorName
    ): array {
        return [
            'tn_fehltage_id' => $teilnehmerId.'-'.$terminId,
            'teilnehmer_id' => $teilnehmerId,
            'institut_id' => $institutId,
            'termin_id' => $terminId,
            'date' => $date,
            'status' => 1,
            'upd_user' => $tutorName,
        ];
    }

    protected function markRowsAfterPush(
        CourseDay $day,
        array $pushedLocalIds,
        array $fullyPresentLocalIds
    ): void {
        $attendance = $day->attendance_data ?? [];
        $participants = data_get($attendance, 'participants', []);
        if (! is_array($participants)) {
            return;
        }

        $now = Carbon::now();
        foreach ($pushedLocalIds as $personId) {
            if (isset($participants[$personId]) && is_array($participants[$personId])) {
                $participants[$personId]['state'] = self::STATE_SYNCED;
                $participants[$personId]['updated_at'] = $now->toDateTimeString();
            }
        }
        foreach ($fullyPresentLocalIds as $personId) {
            if (isset($participants[$personId]) && is_array($participants[$personId])) {
                $participants[$personId]['state'] = self::STATE_SYNCED;
                $participants[$personId]['src_api_id'] = null;
                $participants[$personId]['updated_at'] = $now->toDateTimeString();
            }
        }

        $attendance['participants'] = $participants;
        $day->attendance_data = $attendance;
        $day->attendance_updated_at = $now;
        $day->attendance_last_synced_at = $now;
        $day->saveQuietly();
    }

    protected function collectTeilnehmerIds(CourseDay $day, ?array $onlyLocalPersonIds): array
    {
        $participants = $day->course->participants;
        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
            $participants = $participants->filter(fn ($person) => in_array((int) $person->id, $only, true));
        }

        return $participants
            ->pluck('teilnehmer_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function computeCourseTimes(CourseDay $day): array
    {
        if (! $day->date) {
            return [null, null, 0];
        }

        $date = $day->date->toDateString();
        $start = $this->parseBoundary($date, $day->start_time);
        $end = $this->parseBoundary($date, $day->end_time);

        if ($start && $end && $end->gt($start)) {
            return [$start, $end, $start->diffInMinutes($end)];
        }

        $minutes = (int) round(((float) ($day->std ?? 0)) * 60);
        if (! $start || $minutes <= 0) {
            return [$start, null, 0];
        }

        return [$start, $start->copy()->addMinutes($minutes), $minutes];
    }

    protected function parseBoundary(string $date, mixed $value): ?Carbon
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

    protected function resolveTimeForPush(
        array $row,
        string $directKey,
        string $timestampKey,
        ?Carbon $fallback
    ): string {
        foreach ([$row[$directKey] ?? null, data_get($row, 'timestamps.'.$timestampKey)] as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value === '' || in_array($value, ['00:00', '00:00:00', '0:00'], true)) {
                continue;
            }

            try {
                return Carbon::parse($value)->format('H:i');
            } catch (\Throwable) {
                // try fallback
            }
        }

        return $fallback?->format('H:i') ?? '00:00';
    }

    protected function mapReasonCode(
        bool $present,
        bool $excused,
        int $lateMinutes,
        int $leftEarlyMinutes
    ): string {
        if (! $present) {
            return $excused ? 'E' : 'UE';
        }

        return ($lateMinutes > 0 || $leftEarlyMinutes > 0) ? 'TA' : 'E';
    }

    protected function normalizeNote(mixed $note): string
    {
        if (is_array($note)) {
            return trim(implode(' | ', array_map(
                static fn ($value) => is_scalar($value) ? (string) $value : '',
                $note
            )));
        }

        return is_scalar($note) ? trim((string) $note) : '';
    }
}
