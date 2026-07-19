<?php

namespace App\Livewire\Admin\UserProfile;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseMaterialAcknowledgement;
use App\Models\CourseResult;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentParticipantCourseScope;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class UserCourses extends Component
{
    use WithoutUrlPagination;
    use WithPagination;

    protected string $pageName = 'coursesPage';

    public User $user;

    public string $search = '';

    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount(User $user)
    {
        $this->user = $user->load(['persons']); // mehrere persons laden
    }

    public function updatingSearch()
    {
        $this->resetPage($this->pageName);
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                        <span class="loader"></span>
                        <span class="text-sm text-gray-700">wird geladen…</span>
                    </div>
                </div>
            </div>
        HTML;
    }

    public function render()
    {
        $courses = collect();
        $courseMeta = [];
        $contracts = $this->buildContractOverviews();
        $contractCourseGroups = [];

        // Liste aller person_ids des Users (multi-person)
        $personIds = $this->user->persons->pluck('id')->toArray();

        // ------------------------------------------------------------------
        // 📌 FALL 1: User ist Dozent → Kurse über Course.primary_tutor_person_id
        // ------------------------------------------------------------------
        if ($this->user->role === 'tutor') {

            $query = Course::whereIn('primary_tutor_person_id', $personIds)
                ->with('tutor')
                ->orderBy('planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%'.$this->search.'%';

                $query->where(function ($qq) use ($s) {
                    $qq->where('courses.title', 'like', $s)
                        ->orWhere('courses.klassen_id', 'like', $s)
                        ->orWhere('courses.termin_id', 'like', $s)
                        ->orWhere('courses.vtz', 'like', $s)
                        ->orWhere('courses.room', 'like', $s);
                });
            }

            $courses = $query->paginate($this->perPage, ['*'], $this->pageName);

        }
        // ------------------------------------------------------------------
        // FALL 2: User ist Teilnehmer → Pivot-Relation über alle Persons
        // ------------------------------------------------------------------
        else {

            $pivot = 'course_participant_enrollments';
            $participantPersons = $this->user->persons
                ->filter(fn (Person $person) => ! empty($person->id))
                ->values();

            $query = Course::query()
                ->join($pivot, function (JoinClause $join) use ($pivot) {
                    $join->on("$pivot.course_id", '=', 'courses.id')
                        ->whereNull("$pivot.deleted_at")
                        ->where("$pivot.is_active", true);
                })
                ->whereNull('courses.deleted_at')
                ->whereIn("$pivot.person_id", $participantPersons->pluck('id'))
                ->select([
                    'courses.*',
                    "$pivot.id as _enrollment_id",
                    "$pivot.person_id as _person_id",
                    "$pivot.teilnehmer_id as _enrollment_teilnehmer_id",
                    "$pivot.tn_baustein_id as _enrollment_tn_baustein_id",
                    "$pivot.baustein_id as _enrollment_baustein_id",
                    "$pivot.klassen_id as _enrollment_klassen_id",
                    "$pivot.termin_id as _enrollment_termin_id",
                    "$pivot.kurzbez_ba as _enrollment_kurzbez_ba",
                    "$pivot.status as _enrollment_status",
                    "$pivot.results as _enrollment_results",
                    "$pivot.notes as _enrollment_notes",
                ])
                ->with('tutor')
                ->orderBy('courses.planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%'.$this->search.'%';

                $query->where(function ($qq) use ($s, $pivot) {
                    $qq->where('courses.title', 'like', $s)
                        ->orWhere('courses.klassen_id', 'like', $s)
                        ->orWhere('courses.termin_id', 'like', $s)
                        ->orWhere("$pivot.klassen_id", 'like', $s)
                        ->orWhere("$pivot.termin_id", 'like', $s)
                        ->orWhere("$pivot.kurzbez_ba", 'like', $s)
                        ->orWhere("$pivot.status", 'like', $s);
                });
            }

            // Admin profiles must show complete contract groups. Global
            // pagination would split a contract's modules across pages.
            $courses = $query->get();
        }

        if ($courses->count() > 0) {
            $rows = method_exists($courses, 'getCollection')
                ? $courses->getCollection()
                : collect($courses);

            if ($this->user->role !== 'tutor') {
                $rows = $this->filterVisibleVacationCourses($rows);
                $courses = $rows;
            }

            $courseIds = $rows->pluck('id')->filter()->unique()->values();
            $contextPersonIds = $rows->pluck('_person_id')->filter()->unique()->values();

            $personsById = $contextPersonIds->isEmpty()
                ? collect()
                : Person::query()
                    ->whereIn('id', $contextPersonIds)
                    ->get()
                    ->keyBy('id');

            $daysByCourse = $courseIds->isEmpty()
                ? collect()
                : CourseDay::query()
                    ->whereIn('course_id', $courseIds)
                    ->get(['id', 'course_id', 'attendance_data'])
                    ->groupBy('course_id');

            $acksByKey = ($courseIds->isEmpty() || $contextPersonIds->isEmpty())
                ? collect()
                : CourseMaterialAcknowledgement::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereIn('person_id', $contextPersonIds)
                    ->whereNotNull('acknowledged_at')
                    ->orderByDesc('acknowledged_at')
                    ->get()
                    ->groupBy(fn ($ack) => $this->pairKey((int) $ack->course_id, (int) $ack->person_id));

            $resultsByKey = ($courseIds->isEmpty() || $contextPersonIds->isEmpty())
                ? collect()
                : CourseResult::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereIn('person_id', $contextPersonIds)
                    ->orderByDesc('updated_at')
                    ->get()
                    ->groupBy(fn ($result) => $this->pairKey((int) $result->course_id, (int) $result->person_id));

            foreach ($rows as $course) {
                $rowKey = $this->rowKeyForCourse($course);
                $personId = isset($course->_person_id) ? (int) $course->_person_id : null;

                $meta = [
                    'person' => null,
                    'material_ack' => null,
                    'material_ack_at' => null,
                    'course_result' => null,
                    'enrollment_results' => [],
                    'attendance' => [
                        'tracked_days' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'excused' => 0,
                        'late_count' => 0,
                        'late_minutes' => 0,
                        'left_early_count' => 0,
                        'left_early_minutes' => 0,
                    ],
                ];

                if ($personId) {
                    $meta['person'] = $personsById->get($personId);

                    $pairKey = $this->pairKey((int) $course->id, $personId);

                    $ack = optional($acksByKey->get($pairKey))->first();
                    $meta['material_ack'] = (bool) $ack;
                    $meta['material_ack_at'] = $ack?->acknowledged_at;

                    $meta['course_result'] = optional($resultsByKey->get($pairKey))->first();

                    $meta['attendance'] = $this->buildAttendanceStats(
                        $daysByCourse->get($course->id, collect()),
                        $personId
                    );
                }

                $meta['enrollment_results'] = $this->normalizeEnrollmentResults(
                    $course->_enrollment_results ?? null
                );

                $courseMeta[$rowKey] = $meta;
            }
        } else {
            $rows = collect();
        }

        if ($this->user->role !== 'tutor') {
            $contractCourseGroups = $this->buildContractCourseGroups(
                $contracts,
                $rows,
                trim($this->search) === ''
            );
        }

        return view('livewire.admin.user-profile.user-courses', [
            'courses' => $courses,
            'courseMeta' => $courseMeta,
            'contracts' => $contracts,
            'contractCourseGroups' => $contractCourseGroups,
        ]);
    }

    protected function buildContractOverviews(): array
    {
        return $this->user->persons
            ->flatMap(fn (Person $person) => CurrentParticipantCourseScope::contractOverviewsFor($person))
            ->filter()
            ->map(function (array $contract) {
                foreach (['beginn', 'ende', 'letzter_tag', 'kuendig_zum'] as $key) {
                    $contract[$key.'_fmt'] = $this->formatContractDate($contract[$key] ?? null);
                }

                return $contract;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<int, array{key:string, contract:array<string, mixed>, courses:Collection}>
     */
    protected function buildContractCourseGroups(
        array $contracts,
        Collection $courses,
        bool $includeEmptyContracts = true
    ): array {
        $remaining = $courses
            ->groupBy(fn ($course) => $this->contractGroupKey(
                (int) ($course->_person_id ?? 0),
                $course->_enrollment_teilnehmer_id ?? null
            ));
        $groups = collect();

        foreach ($contracts as $index => $contract) {
            $personPk = (int) ($contract['person_pk'] ?? 0);
            $teilnehmerId = trim((string) ($contract['teilnehmer_id'] ?? ''));

            if ($teilnehmerId !== '') {
                $key = $this->contractGroupKey($personPk, $teilnehmerId);
                $contractCourses = collect($remaining->pull($key, collect()))->values();
            } else {
                // A contract without a participant id must not absorb all
                // legacy enrollments whose participant id is also missing.
                $key = 'contract|'.implode('|', [
                    $personPk,
                    $contract['beratung_id'] ?? '',
                    $contract['teilnehmer_nr'] ?? '',
                    $index,
                ]);
                $contractCourses = collect();
            }

            if (! $includeEmptyContracts && $contractCourses->isEmpty()) {
                continue;
            }

            $groups->push([
                'key' => $key,
                'contract' => $contract,
                'courses' => $contractCourses,
            ]);
        }

        foreach ($remaining as $key => $unassignedCourses) {
            $unassignedCourses = collect($unassignedCourses)->values();
            if ($unassignedCourses->isEmpty()) {
                continue;
            }

            $firstCourse = $unassignedCourses->first();
            $personPk = (int) ($firstCourse->_person_id ?? 0);
            $teilnehmerId = trim((string) ($firstCourse->_enrollment_teilnehmer_id ?? '')) ?: null;
            $person = $this->user->persons->firstWhere('id', $personPk);

            $groups->push([
                'key' => (string) $key,
                'contract' => [
                    'person_pk' => $personPk,
                    'person_name' => $person
                        ? trim(($person->vorname ?? '').' '.($person->nachname ?? ''))
                        : null,
                    'person_id' => $person?->person_id,
                    'teilnehmer_id' => $teilnehmerId,
                    'teilnehmer_nr' => null,
                    'beratung_id' => null,
                    'beginn' => null,
                    'ende' => null,
                    'is_active' => null,
                    'is_current' => false,
                    'is_unassigned' => true,
                    'contract_state' => 'unassigned',
                ],
                'courses' => $unassignedCourses,
            ]);
        }

        return $groups->values()->all();
    }

    protected function contractGroupKey(int $personPk, mixed $teilnehmerId): string
    {
        $teilnehmerId = trim((string) ($teilnehmerId ?? ''));

        return $personPk.'|'.($teilnehmerId !== '' ? $teilnehmerId : '__unassigned__');
    }

    protected function formatContractDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        foreach (['Y/m/d', 'Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'Europe/Berlin')->format('d.m.Y');
            } catch (\Throwable) {
                // try next known format
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw), 'Europe/Berlin')->format('d.m.Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    protected function rowKeyForCourse(Course $course): string
    {
        return implode('-', [
            (int) $course->id,
            (int) ($course->_person_id ?? 0),
            (int) ($course->_enrollment_id ?? 0),
        ]);
    }

    protected function pairKey(int $courseId, int $personId): string
    {
        return $courseId.':'.$personId;
    }

    protected function normalizeEnrollmentResults(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function buildAttendanceStats(Collection $days, int $personId): array
    {
        $stats = [
            'tracked_days' => 0,
            'present' => 0,
            'absent' => 0,
            'excused' => 0,
            'late_count' => 0,
            'late_minutes' => 0,
            'left_early_count' => 0,
            'left_early_minutes' => 0,
        ];

        foreach ($days as $day) {
            $row = data_get($day->attendance_data, 'participants.'.$personId);

            if (! is_array($row)) {
                continue;
            }

            $stats['tracked_days']++;

            $present = (bool) ($row['present'] ?? false);
            $excused = (bool) ($row['excused'] ?? false);

            if ($present) {
                $stats['present']++;
            } elseif ($excused) {
                $stats['excused']++;
            } else {
                $stats['absent']++;
            }

            $lateMinutes = max(0, (int) ($row['late_minutes'] ?? 0));
            if ($lateMinutes > 0) {
                $stats['late_count']++;
                $stats['late_minutes'] += $lateMinutes;
            }

            $leftEarlyMinutes = max(0, (int) ($row['left_early_minutes'] ?? 0));
            if ($leftEarlyMinutes > 0) {
                $stats['left_early_count']++;
                $stats['left_early_minutes'] += $leftEarlyMinutes;
            }
        }

        return $stats;
    }

    protected function filterVisibleVacationCourses(Collection $courses): Collection
    {
        $personsById = $this->user->persons->keyBy('id');

        return $courses
            ->filter(function (Course $course) use ($personsById): bool {
                if (! $this->isVacationCourse($course)) {
                    return true;
                }

                $personId = (int) ($course->_person_id ?? 0);
                $person = $personsById->get($personId);

                return $person instanceof Person
                    && $this->vacationHasSyncedPredecessor($course, $person);
            })
            ->values();
    }

    protected function isVacationCourse(Course $course): bool
    {
        return mb_strtolower(trim((string) ($course->type ?? ''))) === 'ferien'
            || mb_strtoupper(trim((string) ($course->_enrollment_kurzbez_ba ?? ''))) === 'FERI'
            || str_starts_with(mb_strtoupper((string) ($course->klassen_id ?? '')), 'FERI-');
    }

    protected function vacationHasSyncedPredecessor(Course $vacation, Person $person): bool
    {
        $predecessorClassId = $this->vacationPredecessorClassId($vacation, $person);
        if ($predecessorClassId === null) {
            return false;
        }

        return Course::query()
            ->where('klassen_id', $predecessorClassId)
            ->where(function ($query) {
                $query->whereNull('type')->orWhere('type', '!=', 'ferien');
            })
            ->whereHas('participants', fn ($query) => $query->where('persons.id', $person->id))
            ->exists();
    }

    protected function vacationPredecessorClassId(Course $vacation, Person $person): ?string
    {
        $programData = is_array($person->programdata) ? $person->programdata : [];
        $programParticipantId = trim((string) data_get($programData, 'teilnehmer_id', ''));
        $enrollmentParticipantId = trim((string) ($vacation->_enrollment_teilnehmer_id ?? ''));
        if (
            $programParticipantId !== ''
            && $enrollmentParticipantId !== ''
            && $programParticipantId !== $enrollmentParticipantId
        ) {
            return null;
        }

        $blocks = collect(data_get($programData, 'tn_baust', []))
            ->filter(fn ($block) => is_array($block))
            ->map(function (array $block): array {
                $block['_start'] = $this->parseBlockDate($block['beginn_baustein'] ?? null);
                $block['_end'] = $this->parseBlockDate($block['ende_baustein'] ?? null);

                return $block;
            })
            ->filter(fn (array $block) => $block['_start'] instanceof Carbon)
            ->sortBy(fn (array $block) => $block['_start']->timestamp)
            ->values();

        if ($blocks->isEmpty()) {
            return null;
        }

        $vacationStart = $vacation->planned_start_date instanceof Carbon
            ? $vacation->planned_start_date->copy()->startOfDay()
            : $this->parseBlockDate($vacation->planned_start_date);
        $vacationEnd = $vacation->planned_end_date instanceof Carbon
            ? $vacation->planned_end_date->copy()->startOfDay()
            : $this->parseBlockDate($vacation->planned_end_date);
        $vacationBlockId = trim((string) ($vacation->_enrollment_tn_baustein_id ?? ''));

        $vacationIndex = $blocks->search(function (array $block) use ($vacationBlockId, $vacationStart, $vacationEnd): bool {
            if (mb_strtoupper(trim((string) ($block['kurzbez'] ?? ''))) !== 'FERI') {
                return false;
            }

            $blockId = trim((string) ($block['tn_baustein_id'] ?? ''));
            if ($vacationBlockId !== '' && $blockId !== '' && $blockId === $vacationBlockId) {
                return true;
            }

            return $vacationStart
                && $block['_start']->isSameDay($vacationStart)
                && (! $vacationEnd || ($block['_end'] && $block['_end']->isSameDay($vacationEnd)));
        });

        if ($vacationIndex === false) {
            return null;
        }

        $predecessor = null;
        for ($index = $vacationIndex - 1; $index >= 0; $index--) {
            $candidate = $blocks->get($index);
            if (mb_strtoupper(trim((string) ($candidate['kurzbez'] ?? ''))) !== 'FERI') {
                $predecessor = $candidate;
                break;
            }
        }

        $predecessorClassId = trim((string) ($predecessor['klassen_id'] ?? ''));
        return $predecessorClassId !== '' ? $predecessorClassId : null;
    }

    protected function parseBlockDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        foreach (['Y/m/d', 'Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'Europe/Berlin')->startOfDay();
            } catch (\Throwable) {
                // try next known format
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw), 'Europe/Berlin')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
