<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Fluent;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Jobs\ApiUpdates\SyncCourseDayAttendanceJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\File;
use Illuminate\Support\Facades\Route;


class CourseDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'date',
        'start_time',
        'end_time',
        'day_sessions',
        'attendance_data',
        'topic',
        'notes',
        'note_status',
        'settings',
        'type',
    ];

    protected $casts = [
        'date'            => 'date',
        'start_time'      => 'datetime:H:i',
        'end_time'        => 'datetime:H:i',
        'day_sessions'    => 'array', // wichtig für JSON
        'attendance_data' => 'array', // wichtig für JSON
                'attendance_updated_at' => 'datetime',
        'attendance_last_synced_at' => 'datetime',
        'note_status'     => 'integer',
        'settings'       => 'array',
    ];

    public const NOTE_STATUS_MISSING   = 0;
    public const NOTE_STATUS_DRAFT     = 1;
    public const NOTE_STATUS_COMPLETED = 2;
    /**
     * Beim Erstellen Defaults für Sessions & Attendance setzen.
     */
    public const AUTO_SYNC_THRESHOLD_MINUTES = 15;

    protected static function booted(): void
    {
        static::creating(function (CourseDay $day) {
            if (empty($day->day_sessions)) {
                $day->day_sessions = self::makeDefaultSessions($day);
            }

            if (empty($day->attendance_data)) {
                $day->attendance_data = self::makeDefaultAttendance($day);
            }

            if ($day->note_status === null) {
                $day->note_status = self::NOTE_STATUS_MISSING;
            }

            if ($day->settings === null) {
                $day->settings = [];
            }
        });

        static::retrieved(function (CourseDay $day) {
            // In Queue/CLI gibt es keine Route => hier früh raus
            if (app()->runningInConsole()) {
                return;
            }

            // Request kann null sein (z.B. in manchen Tests)
            $route = request()?->route();
            if (! $route) {
                return;
            }

            // Route-Name holen
            $routeName = $route->getName();

            // Beispiel: nur auf bestimmten Routen Auto-Sync
            if (! in_array($routeName, [
                'admin.courses.show',
            ], true)) {
                return;
            }

            // oder kürzer:
            // if (! request()->routeIs('tutor.courses.*')) return;

            self::dispatchSyncIfNotThrottled($day);
        });
    }

        /**
     * Zentraler Cache-Guard für das Dispatchen des Sync-Jobs.
     *
     * - Speichert letzten Sync-Zeitpunkt im Cache
     * - Wenn jünger als 15 Minuten → kein neuer Job
     * - Schreibt Logs für Nachvollziehbarkeit
     */
    protected static function dispatchSyncIfNotThrottled(CourseDay $day): void
    {
        if (!$day->id) {
            return;
        }

        $cacheKey = "courseday_sync_last_run_{$day->id}";
        $now = now();

        $lastRun = Cache::get($cacheKey);

        if ($lastRun instanceof Carbon) {
            $diffMinutes = $lastRun->diffInMinutes($now);

            if ($diffMinutes < self::AUTO_SYNC_THRESHOLD_MINUTES) {
                return;
            }
        }
        // Timestamp speichern, Cache hält 60 Minuten
        Cache::put($cacheKey, $now, now()->diffInSeconds(now()->addMinutes(60)));
        SyncCourseDayAttendanceJob::dispatch($day);
    }

    public static function makeDefaultSessions(self $day): array
    {
        return [
            '1' => [
                'label' => '8:00',
                'start' => '08:00',
                'end'   => '09:30',
                'break' => '09:30-09:45',
                'room'  => '101',
                'topic' => '',
                'notes' => ''
            ],
            '2' => [
                'label' => '9:45',
                'start' => '09:45',
                'end'   => '11:15',
                'break' => '11:15-11:30',
                'room'  => '101',
                'topic' => '',
                'notes' => ''
            ],
            '3' => [
                'label' => '11:30',
                'start' => '11:30',
                'end'   => '13:00',
                'break' => '13:00-13:15',
                'room'  => '101',
                'topic' => '',
                'notes' => ''
            ],
            '4' => [
                'label' => '13:15',
                'start' => '13:15',
                'end'   => '14:45',
                'break' => '',
                'room'  => '101',
                'topic' => '',
                'notes' => ''
            ],
        ];
    }

    public static function makeDefaultAttendance(self $day): array
    {
        $emptyRow = fn () => [
            'present'            => false,
            'late_minutes'       => 0,
            'left_early_minutes' => 0,
            'excused'            => false,
            'note'               => null,
            'timestamps'         => ['in' => null, 'out' => null],
            'created_at'         => null,
            'updated_at'         => null,
        ];

        $byParticipant = function () use ($day, $emptyRow) {
            // Wenn es eine participants-Relation am Course gibt, vorbefüllen (optional).
            // Ansonsten einfach leer lassen.
            $participants = $day->course?->participants ?? collect();
            if (method_exists($participants, 'pluck')) {
                $map = [];
                foreach ($participants as $p) {
                    // Keyed by participant_id
                    $map[$p->id] = $emptyRow();
                }
                return $map;
            }
            return []; // kein Vorbefüllen möglich
        };

        return [
            'participants' => $byParticipant(),
            'status' => [
                'start' => 0,
                'end' => 0,
                'created_at' => null,
                'updated_at' => null,
            ],
        ];
    }

    /**
     * Attendance für einen Teilnehmer gemäß Default-Struktur updaten.
     *
     * Erwartete Keys in $data (alle optional):
     * - present (bool)
     * - late_minutes (int)
     * - left_early_minutes (int)
     * - excused (bool)
     * - note (string|null)
     * - timestamps => ['in' => datetime|null, 'out' => datetime|null]
     */
    public function setAttendance(int $participantId, array $data): void
    {
        $att = $this->attendance_data ?? [];

        // Container sicherstellen
        if (!isset($att['participants']) || !is_array($att['participants'])) {
            $att['participants'] = [];
        }
        if (!isset($att['status']) || !is_array($att['status'])) {
            $att['status'] = [
                'start' => 0,
                'end' => 0,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        // Default-Zeile
        $defaultRow = [
            'present'            => false,
            'late_minutes'       => 0,
            'left_early_minutes' => 0,
            'excused'            => false,
            'note'               => null,
            'timestamps'         => ['in' => null, 'out' => null],
            'created_at'         => null,
            'updated_at'         => null,
        ];

        // Aktuelle Zeile oder Default
        $row = $att['participants'][$participantId] ?? $defaultRow;

        // Timestamps nested mergen (nicht platt überbügeln)
        if (isset($data['timestamps']) && is_array($data['timestamps'])) {
            $row['timestamps'] = array_merge($row['timestamps'] ?? ['in' => null, 'out' => null], $data['timestamps']);
            unset($data['timestamps']);
        }

        // Restliche Felder mergen
        $row = array_merge($row, $data);

        // Touch created/updated
        $now = now()->toDateTimeString();
        if (empty($row['created_at'])) {
            $row['created_at'] = $now;
        }
        $row['updated_at'] = $now;

        // Speichern in Struktur
        $att['participants'][$participantId] = $row;

        // Status-Updated timestamp pflegen (Zähler start/end lässt du nach Bedarf unverändert)
        $att['status']['updated_at'] = $now;

        if ($att['status']['start'] == 0) {
            $att['status']['start'] = 1; // status auf 1 setzen für 'in bearbeitung'
        }

        $this->attendance_data = $att;
        $this->save();
    }


    public function getSessions()
    {
        return collect($this->day_sessions ?? [])
            ->map(function ($session, $key) {
                return new Fluent(array_merge(['id' => $key], $session));
            });

    }

    /** Notes lesen für eine Session-ID */
    public function getSessionNotes(string|int $sessionId): ?string
    {
        return data_get($this->day_sessions, [(string)$sessionId, 'notes']);
    }

    /** Notes setzen + in day_sessions JSON zurückschreiben */
    public function setSessionNotes(string|int $sessionId, ?string $notes): void
    {
        $data = $this->day_sessions ?? [];
        $sid = (string)$sessionId;
        $data[$sid] = array_merge([
            'label' => null, 'start' => null, 'end' => null, 'break' => null,
            'room' => null, 'topic' => null, 'notes' => null,
        ], $data[$sid] ?? []);
        $data[$sid]['notes'] = $notes;
        $this->day_sessions = $data; // <- wichtig: Feld am Model setzen
        $this->save(); // Speichern, damit Änderungen persistiert werden
    }

    /** Topic lesen für eine Session-ID */
    public function getSessionTopic(string|int $sessionId): ?string
    {
        return data_get($this->day_sessions, [(string)$sessionId, 'topic']);
    }

    public function setSessionTopic(string|int $sessionId, ?string $topic): void
    {
        $data = $this->day_sessions ?? [];
        $sid = (string)$sessionId;
        $data[$sid] = array_merge([
            'label' => null, 'start' => null, 'end' => null, 'break' => null,
            'room' => null, 'topic' => null, 'notes' => null,
        ], $data[$sid] ?? []);
        $data[$sid]['topic'] = $topic;
        $this->day_sessions = $data; // <- wichtig: Feld am Model setzen
        $this->save(); // Speichern, damit Änderungen persistiert werden
    }

    public function getAttendanceData()
    {
        return $this->attendance_data ?? [];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /** Tutor-Signaturen für diesen Tag (Typ z. B. sign_courseday_tutor) */
    public function tutorSignatures()
    {
        return $this->files()->where('type', 'sign_courseday_doku_tutor');
    }

    public function latestTutorSignature(): ?File
    {
        return $this->tutorSignatures()->latest()->first();
    }
}
