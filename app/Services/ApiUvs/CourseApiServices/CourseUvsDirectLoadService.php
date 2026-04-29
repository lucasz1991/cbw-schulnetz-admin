<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseResult;
use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CourseUvsDirectLoadService
{
    private const RESULTS_ENDPOINT = '/api/course/courseresults/loaddata';
    private const ATTENDANCE_ENDPOINT = '/api/course/courseday/loadattendancedata';

    private const SUPPORTED_PRUEF_KENNZ = ['V', '+', 'XO', 'B', 'D', 'X', 'N', 'K', '-', 'I', 'E'];
    private const LOCAL_STATUS_CODES_FORCE_ZERO_RESULT = ['V', '-', 'X'];
    private const LOCAL_STATUS_CODES_WITHOUT_RESULT = ['XO', 'B', 'D', 'I', 'E'];

    public const ATTENDANCE_STATE_REMOTE = 'remote';

    public function __construct(
        protected ApiUvsService $apiUvsService
    ) {
    }

    public function loadAll(Course $course): array
    {
        return [
            'results' => $this->loadResults($course),
            'attendances' => $this->loadAttendances($course),
        ];
    }

    public function loadResults(Course $course): bool
    {
        if (! $course->termin_id || ! $course->klassen_id) {
            Log::warning('CourseUvsDirectLoadService.loadResults: fehlende termin_id/klassen_id.', [
                'course_id' => $course->id,
                'termin_id' => $course->termin_id,
                'klassen_id' => $course->klassen_id,
            ]);

            return false;
        }

        $course->loadMissing('participants');

        $teilnehmerIds = $this->collectCourseTeilnehmerIds($course);

        if (empty($teilnehmerIds)) {
            return true;
        }

        $payload = [
            'termin_id' => (string) $course->termin_id,
            'klassen_id' => (string) $course->klassen_id,
            'teilnehmer_ids' => $teilnehmerIds,
        ];

        $response = $this->apiUvsService->request(
            'POST',
            self::RESULTS_ENDPOINT,
            $payload,
            []
        );

        if (empty($response['ok'])) {
            Log::error('CourseUvsDirectLoadService.loadResults: UVS-Response nicht ok.', [
                'course_id' => $course->id,
                'response' => $response,
            ]);

            return false;
        }

        $this->applyResultsLoadResponse($course, $response);

        Log::info('CourseUvsDirectLoadService.loadResults: Load OK.', [
            'course_id' => $course->id,
        ]);

        return true;
    }

    public function loadAttendances(Course $course): bool
    {
        $course->loadMissing(['days', 'participants']);

        if ($course->days->isEmpty()) {
            return true;
        }

        $allOk = true;

        foreach ($course->days as $day) {
            if (! $day instanceof CourseDay) {
                continue;
            }

            if (! $this->loadAttendanceForDay($day)) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    public function loadAttendanceForDay(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        $day->loadMissing('course.participants');

        if (! $this->isAttendanceLoadable($day)) {
            Log::warning('CourseUvsDirectLoadService.loadAttendanceForDay: day nicht loadbar.', [
                'day_id' => $day->id,
                'course_id' => $day->course_id,
            ]);

            return false;
        }

        $teilnehmerIds = $this->collectDayTeilnehmerIds($day, $onlyLocalPersonIds);

        if (empty($teilnehmerIds)) {
            $this->resetAttendanceData($day);

            return true;
        }

        $payload = [
            'termin_id' => (string) $day->course->termin_id,
            'date' => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
        ];

        $response = $this->apiUvsService->request(
            'POST',
            self::ATTENDANCE_ENDPOINT,
            $payload,
            []
        );

        if (empty($response['ok'])) {
            Log::error('CourseUvsDirectLoadService.loadAttendanceForDay: UVS-Response nicht ok.', [
                'day_id' => $day->id,
                'response' => $response,
            ]);

            return false;
        }

        $this->applyAttendanceLoadResponse($day, $response, $onlyLocalPersonIds);
        Log::info('CourseUvsDirectLoadService.loadAttendanceForDay: Load OK.', [
            'day_id' => $day->id,
            'course_id' => $day->course_id,
        ]);
        return true;
    }

    protected function collectCourseTeilnehmerIds(Course $course): array
    {
        $participants = $course->participants ?? collect();

        if ($participants->isEmpty()) {
            return [];
        }

        return $participants
            ->map(fn ($person) => (string) $person->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    protected function applyResultsLoadResponse(Course $course, array $response): void
    {
        $innerData = $this->extractInnerData($response);
        $pulled = $innerData['pulled'] ?? null;
        $rawItems = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];
        $items = $this->deduplicatePulledItemsByParticipant($rawItems);

        $targetTeilnehmerIds = collect($innerData['teilnehmer_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($targetTeilnehmerIds)) {
            $targetTeilnehmerIds = collect($items)
                ->pluck('teilnehmer_id')
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (empty($targetTeilnehmerIds)) {
            return;
        }

        $persons = Person::whereIn('teilnehmer_id', $targetTeilnehmerIds)
            ->get()
            ->groupBy('teilnehmer_id');

        if ($persons->isEmpty()) {
            return;
        }

        $targetPersonIds = $persons
            ->flatten(1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($targetPersonIds)) {
            return;
        }

        $deletedCount = CourseResult::query()
            ->where('course_id', $course->id)
            ->whereIn('person_id', $targetPersonIds)
            ->delete();

        $createdCount = 0;
        $missingPersonMapping = 0;
        $noRemoteDataCount = 0;

        foreach ($items as $item) {
            $teilnehmerId = (string) ($item['teilnehmer_id'] ?? '');

            if ($teilnehmerId === '' || empty($persons[$teilnehmerId])) {
                $missingPersonMapping++;
                continue;
            }

            $remoteStatus = $this->parseNullableInt($item['status'] ?? null);
            $remotePunkte = $this->parseNullableInt($item['pruef_punkte'] ?? null);
            $remotePruefKennz = $item['pruef_kennz'] ?? null;

            if (! $this->hasMeaningfulRemoteExamData($remoteStatus, $remotePunkte, $remotePruefKennz)) {
                $noRemoteDataCount++;
                continue;
            }

            $localStatus = $this->mapRemoteStatusToLocalStatus($remoteStatus, $remotePruefKennz);
            $localResult = $this->normalizeLocalResult(
                $this->mapRemotePunkteToLocalResult($remotePunkte),
                $localStatus
            );

            foreach ($persons[$teilnehmerId] as $person) {
                $courseResult = new CourseResult();
                $courseResult->course_id = $course->id;
                $courseResult->person_id = $person->id;
                $courseResult->result = $localResult;
                $courseResult->status = $localStatus;
                $courseResult->saveQuietly();

                $createdCount++;
            }
        }

        Log::info('CourseUvsDirectLoadService.applyResultsLoadResponse: CourseResults aus UVS uebernommen.', [
            'course_id' => $course->id,
            'deleted_local' => $deletedCount,
            'created_local' => $createdCount,
            'items_total' => count($items),
            'targets_total' => count($targetTeilnehmerIds),
            'no_remote_data' => $noRemoteDataCount,
            'missing_person_mapping' => $missingPersonMapping,
        ]);
    }

    protected function extractInnerData(array $response): array
    {
        $outerData = $response['data'] ?? [];

        if (! is_array($outerData)) {
            return [];
        }

        $innerData = $outerData['data'] ?? $outerData;

        return is_array($innerData) ? $innerData : [];
    }

    protected function parseNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function hasMeaningfulRemoteExamData(?int $status, ?int $punkte, ?string $pruefKennz): bool
    {
        $kennz = is_string($pruefKennz) ? trim($pruefKennz) : '';

        if ($kennz !== '') {
            return true;
        }

        if ($punkte !== null && $punkte !== 0) {
            return true;
        }

        return in_array($status, [2, 3], true);
    }

    protected function normalizeLocalStatus(mixed $status, mixed $result = null): ?string
    {
        $raw = is_string($status) || is_numeric($status)
            ? trim((string) $status)
            : '';

        if ($raw !== '') {
            $upper = mb_strtoupper($raw);
            if (in_array($upper, self::SUPPORTED_PRUEF_KENNZ, true)) {
                return $upper;
            }
        }

        $normalized = str_replace([' ', '-'], '_', mb_strtolower($raw));

        if (in_array($normalized, ['v', 'betrug', 'betrugsversuch'], true)) {
            return 'V';
        }

        if (in_array($normalized, ['nicht_teilgenommen', 'nt', 'not_participated', '3'], true)) {
            return '-';
        }

        if (in_array($normalized, ['an_pruefung_teilgenommen', 'teilgenommen', 'bestanden', 'passed', '1'], true)) {
            return '+';
        }

        if (in_array($normalized, ['ausstehend', 'pending'], true)) {
            return 'XO';
        }

        if (in_array($normalized, ['durchgefallen', 'failed', 'nicht_bestanden', '2'], true)) {
            return 'D';
        }

        if (in_array($normalized, ['nachklausur', 'retake'], true)) {
            return 'N';
        }

        if (in_array($normalized, ['nachkorrektur', 'recheck'], true)) {
            return 'K';
        }

        if (in_array($normalized, ['pruefung_ignorieren', 'ignorieren', 'ignore'], true)) {
            return 'I';
        }

        if ($result !== null && $result !== '') {
            return '+';
        }

        return null;
    }

    protected function localStatusForcesZeroResult(?string $status): bool
    {
        $normalizedStatus = $this->normalizeLocalStatus($status);

        return in_array((string) $normalizedStatus, self::LOCAL_STATUS_CODES_FORCE_ZERO_RESULT, true);
    }

    protected function localStatusHasNoResult(?string $status): bool
    {
        $normalizedStatus = $this->normalizeLocalStatus($status);

        return in_array((string) $normalizedStatus, self::LOCAL_STATUS_CODES_WITHOUT_RESULT, true);
    }

    protected function normalizeLocalResult(mixed $result, ?string $status = null): ?int
    {
        $normalizedStatus = $this->normalizeLocalStatus($status, $result);

        if ($this->localStatusForcesZeroResult($normalizedStatus)) {
            return 0;
        }

        if ($this->localStatusHasNoResult($normalizedStatus)) {
            return null;
        }

        return $this->mapLocalResultToPruefPunkte($result);
    }

    protected function mapLocalResultToPruefPunkte(mixed $result): ?int
    {
        if ($result === null || $result === '') {
            return null;
        }

        if (! is_numeric($result)) {
            return null;
        }

        $value = (int) round($result);

        return max(0, min(255, $value));
    }

    protected function deduplicatePulledItemsByParticipant(array $items): array
    {
        return collect($items)
            ->filter(fn ($row) => is_array($row))
            ->groupBy(fn (array $row) => (string) ($row['teilnehmer_id'] ?? ''))
            ->reject(fn ($group, string $teilnehmerId) => $teilnehmerId === '')
            ->map(function ($group) {
                return $group
                    ->sortByDesc(fn (array $row) => (int) ($row['uid'] ?? 0))
                    ->first();
            })
            ->values()
            ->all();
    }

    protected function normalizeStatusToPruefKennz(?string $status): string
    {
        $raw = is_string($status) ? trim($status) : '';

        if ($raw === '') {
            return '';
        }

        $upper = strtoupper($raw);
        if (in_array($upper, self::SUPPORTED_PRUEF_KENNZ, true)) {
            return $upper;
        }

        $normalized = str_replace([' ', '-'], '_', strtolower($raw));

        return match ($normalized) {
            'passed', 'bestanden', 'teilgenommen', 'an_pruefung_teilgenommen' => '+',
            'failed', 'durchgefallen', 'nicht_bestanden' => 'D',
            'not_participated', 'nt', 'nicht_teilgenommen' => '-',
            'betrug', 'betrugsversuch' => 'V',
            'ausstehend', 'pending' => 'XO',
            'nachklausur', 'retake' => 'N',
            'nachkorrektur', 'recheck' => 'K',
            'pruefung_ignorieren', 'ignorieren', 'ignore' => 'I',
            default => '',
        };
    }

    protected function mapRemoteStatusToLocalStatus(?int $status, ?string $pruefKennz): ?string
    {
        $kennz = $this->normalizeStatusToPruefKennz($pruefKennz);

        if ($kennz !== '') {
            return $kennz;
        }

        if ($status === null || $status === 0) {
            return null;
        }

        return match ($status) {
            1 => '+',
            2 => 'D',
            3 => '-',
            default => (string) $status,
        };
    }

    protected function mapRemotePunkteToLocalResult(?int $punkte): ?int
    {
        return $punkte;
    }

    protected function isAttendanceLoadable(CourseDay $day): bool
    {
        return (bool) ($day->course && $day->course->termin_id && $day->date);
    }

    protected function collectDayTeilnehmerIds(CourseDay $day, ?array $onlyLocalPersonIds = null): array
    {
        $participants = $day->course->participants ?? collect();

        if ($participants->isEmpty()) {
            return [];
        }

        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
            $participants = $participants->filter(fn ($person) => in_array((int) $person->id, $only, true));
        }

        return $participants
            ->map(fn ($person) => (string) $person->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    protected function applyAttendanceLoadResponse(CourseDay $day, array $response, ?array $onlyLocalPersonIds = null): void
    {
        $innerData = $this->extractInnerData($response);
        $pulled = $innerData['pulled'] ?? null;
        $items = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];

        if (empty($items)) {
            $this->resetAttendanceData($day);

            return;
        }

        $only = null;
        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
        }

        $participantsRel = $day->course->participants ?? collect();

        if ($participantsRel->isEmpty()) {
            $this->resetAttendanceData($day);

            return;
        }

        if ($only !== null) {
            $participantsRel = $participantsRel->filter(fn ($person) => in_array((int) $person->id, $only, true));
        }

        $localIds = $participantsRel
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($localIds)) {
            $this->resetAttendanceData($day);

            return;
        }

        $persons = Person::whereIn('id', $localIds)->get()->keyBy('id');

        $tnToLocal = [];
        foreach ($localIds as $localId) {
            $person = $persons->get($localId);
            if ($person && ! empty($person->teilnehmer_id)) {
                $tnToLocal[(string) $person->teilnehmer_id][] = $localId;
            }
        }

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day);

        $now = Carbon::now();
        $nowStr = $now->toDateTimeString();
        $dayIso = $day->date?->toDateString();

        $newParticipants = [];

        foreach ($items as $item) {
            $uid = $item['uid'] ?? null;
            $teilnehmerId = $item['teilnehmer_id'] ?? null;
            $fehlDatumIso = $item['fehl_datum_iso'] ?? null;

            if (! $teilnehmerId || empty($tnToLocal[(string) $teilnehmerId])) {
                continue;
            }

            if ($dayIso && $fehlDatumIso && $fehlDatumIso !== $dayIso) {
                continue;
            }

            $fehlStdRemote = (float) ($item['fehl_std'] ?? 0.0);
            $fehlGrundRemote = (string) ($item['fehl_grund'] ?? '');
            $fehlBemRemote = trim((string) ($item['fehl_bem'] ?? ''));

            $gekommenRemote = $this->normalizeRemoteTime($item['gekommen'] ?? null);
            $gegangenRemote = $this->normalizeRemoteTime($item['gegangen'] ?? null);

            $gekommenCarbon = $this->parseTimeOnDay($day->date, $gekommenRemote);
            $gegangenCarbon = $this->parseTimeOnDay($day->date, $gegangenRemote);

            foreach ($tnToLocal[(string) $teilnehmerId] as $localPersonId) {
                $row = [
                    'present' => null,
                    'excused' => false,
                    'late_minutes' => null,
                    'left_early_minutes' => null,
                    'note' => $fehlBemRemote !== '' ? $fehlBemRemote : '',
                    'timestamps' => ['in' => null, 'out' => null],
                    'arrived_at' => $gekommenRemote,
                    'left_at' => $gegangenRemote,
                    'src_api_id' => $uid,
                    'state' => self::ATTENDANCE_STATE_REMOTE,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];

                $this->hydrateLateEarlyMinutes($row, $courseStart, $courseEnd, $gekommenCarbon, $gegangenCarbon);

                $hasAnyTime = (bool) ($gekommenCarbon || $gegangenCarbon);

                if ($hasAnyTime) {
                    $row['present'] = true;
                } else {
                    if ($totalMinutes > 0) {
                        $totalHours = $totalMinutes / 60.0;
                        $row['present'] = $fehlStdRemote < ($totalHours - 0.01);
                    } else {
                        $row['present'] = false;
                    }
                }

                $reverse = $this->reverseMapReasonCode($fehlGrundRemote);

                if ($reverse['excused'] !== null) {
                    $row['excused'] = $reverse['excused'];
                }

                if (! $hasAnyTime && $reverse['present'] !== null) {
                    $row['present'] = $reverse['present'];
                }

                $newParticipants[(int) $localPersonId] = $row;
            }
        }

        $attendance = $this->freshAttendanceDataSkeleton($day);
        $existingParticipants = data_get($day->attendance_data, 'participants', []);
        $attendance['participants'] = $this->mergeAttendanceParticipants(
            is_array($existingParticipants) ? $existingParticipants : [],
            $newParticipants
        );

        $day->attendance_data = $attendance;
        $day->attendance_updated_at = $now;
        $day->attendance_last_synced_at = $now;
        $day->saveQuietly();
    }

    protected function resetAttendanceData(CourseDay $day): void
    {
        $now = Carbon::now();
        $attendance = $this->freshAttendanceDataSkeleton($day);
        $existingParticipants = data_get($day->attendance_data, 'participants', []);
        $attendance['participants'] = $this->keepLocalPresentOnly(
            is_array($existingParticipants) ? $existingParticipants : []
        );

        $day->attendance_data = $attendance;
        $day->attendance_updated_at = $now;
        $day->attendance_last_synced_at = $now;
        $day->saveQuietly();
    }

    protected function freshAttendanceDataSkeleton(CourseDay $day): array
    {
        $nowStr = Carbon::now()->toDateTimeString();

        return [
            'status' => [
                'start' => 0,
                'end' => 0,
                'state' => null,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ],
            'meta' => [
                'termin_id' => (string) ($day->course->termin_id ?? ''),
                'date' => $day->date?->toDateString(),
                'source' => 'remote-load',
            ],
            'participants' => [],
        ];
    }

    protected function mergeAttendanceParticipants(array $localParticipants, array $remoteParticipants): array
    {
        $merged = $this->keepLocalPresentOnly($localParticipants);

        foreach ($remoteParticipants as $personId => $row) {
            $merged[(int) $personId] = $row;
        }

        return $merged;
    }

    protected function keepLocalPresentOnly(array $participants): array
    {
        $kept = [];

        foreach ($participants as $personId => $row) {
            if (! is_array($row)) {
                continue;
            }

            $present = array_key_exists('present', $row) ? (bool) $row['present'] : false;
            $srcApiId = $row['src_api_id'] ?? null;

            if ($present && empty($srcApiId)) {
                $kept[(int) $personId] = $row;
            }
        }

        return $kept;
    }

    protected function hydrateLateEarlyMinutes(
        array &$row,
        ?Carbon $courseStart,
        ?Carbon $courseEnd,
        ?Carbon $gekommenCarbon,
        ?Carbon $gegangenCarbon
    ): void {
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);
        $leftEarlyMinutes = (int) ($row['left_early_minutes'] ?? 0);

        if ($courseStart && $gekommenCarbon) {
            $diff = $courseStart->diffInMinutes($gekommenCarbon, false);
            $lateMinutes = $diff > 0 ? $diff : 0;
        }

        if ($courseEnd && $gegangenCarbon) {
            $leftEarlyMinutes = $gegangenCarbon->lt($courseEnd)
                ? $gegangenCarbon->diffInMinutes($courseEnd)
                : 0;
        }

        $row['late_minutes'] = $lateMinutes;
        $row['left_early_minutes'] = $leftEarlyMinutes;
    }

    protected function normalizeRemoteTime(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '' || $normalized === '00:00' || $normalized === '00:00:00' || $normalized === '0:00') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized)) {
            return substr($normalized, 0, 5);
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $normalized)) {
            [$hours, $minutes] = explode(':', $normalized, 2);

            return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . $minutes;
        }

        try {
            return Carbon::parse($normalized)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function reverseMapReasonCode(string $fehlGrund): array
    {
        $code = strtoupper(trim($fehlGrund));

        return match ($code) {
            'E', 'K' => ['present' => null, 'excused' => true],
            'UE', 'F' => ['present' => false, 'excused' => false],
            'TA', 'T' => ['present' => true, 'excused' => false],
            default => ['present' => null, 'excused' => null],
        };
    }

    protected function computeCourseTimes(CourseDay $day): array
    {
        $date = $day->date;

        if (! $date) {
            return [null, null, 0];
        }

        $courseStart = $this->parseCourseBoundaryTime($date, $day->start_time);
        $courseEnd = $this->parseCourseBoundaryTime($date, $day->end_time);

        if ($courseStart && $courseEnd && $courseEnd->gt($courseStart)) {
            return [$courseStart, $courseEnd, $courseStart->diffInMinutes($courseEnd)];
        }

        $totalHours = (float) ($day->std ?? 0.0);

        if (! $courseStart || $totalHours <= 0) {
            return [null, null, 0];
        }

        $totalMinutes = (int) round($totalHours * 60);

        return [$courseStart, (clone $courseStart)->addMinutes($totalMinutes), $totalMinutes];
    }

    protected function parseCourseBoundaryTime(?Carbon $date, mixed $rawTime): ?Carbon
    {
        if (! $date || $rawTime === null || $rawTime === '') {
            return null;
        }

        try {
            if ($rawTime instanceof Carbon) {
                $time = $rawTime->format('H:i');
            } else {
                $time = Carbon::parse((string) $rawTime)->format('H:i');
            }

            return Carbon::parse($date->toDateString() . ' ' . $time);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseTimeOnDay(?Carbon $date, ?string $time): ?Carbon
    {
        if (! $date || ! $time) {
            return null;
        }

        $time = trim($time);

        if ($time === '' || $time === '00:00' || $time === '0:00') {
            return null;
        }

        try {
            return Carbon::parse($date->toDateString() . ' ' . $time);
        } catch (\Throwable) {
            return null;
        }
    }
}
