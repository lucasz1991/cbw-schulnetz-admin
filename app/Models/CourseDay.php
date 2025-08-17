<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Fluent;



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
    ];

    protected $casts = [
        'date'            => 'date',
        'start_time'      => 'datetime:H:i',
        'end_time'        => 'datetime:H:i',
        'day_sessions'    => 'array', // wichtig für JSON
        'attendance_data' => 'array', // wichtig für JSON
    ];


    /**
     * Beim Erstellen Defaults für Sessions & Attendance setzen.
     */
    protected static function booted(): void
    {
        static::creating(function (CourseDay $day) {
            // Sessions nur setzen, wenn nicht bereits befüllt
            if (empty($day->day_sessions)) {
                $day->day_sessions = self::makeDefaultSessions($day);
            }

            // Attendance nur setzen, wenn nicht bereits befüllt
            if (empty($day->attendance_data)) {
                $day->attendance_data = self::makeDefaultAttendance($day);
            }
        });
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
            'start'   => ['participants' => $byParticipant()],
            'end' => ['participants' => $byParticipant()],
        ];
    }

    /**
     * Helper: Attendance für einen Teilnehmer/Session updaten.
     */
    public function setAttendance(int $participantId, string $sessionKey, array $data): void
    {
        $sessions = $this->attendance_data ?? [];
        $sessions[$sessionKey]['participants'] = $sessions[$sessionKey]['participants'] ?? [];

        $current = $sessions[$sessionKey]['participants'][$participantId] ?? [
            'present'            => false,
            'late_minutes'       => 0,
            'left_early_minutes' => 0,
            'excused'            => false,
            'note'               => null,
            'timestamps'         => ['in' => null, 'out' => null],
        ];

        $sessions[$sessionKey]['participants'][$participantId] = array_merge($current, $data);

        $this->attendance_data = $sessions;
    }

    public function getSessions()
    {
        return collect($this->day_sessions ?? [])
            ->map(function ($session, $key) {
                return new Fluent(array_merge(['id' => $key], $session));
            });

    }

    public function getAttendanceData()
    {
        return $this->attendance_data ?? [];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
