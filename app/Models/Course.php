<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Wenn du lieber alles freigeben willst:
     * protected $guarded = [];
     * Ich liste hier explizit die Felder aus deiner Migration.
     */
    protected $fillable = [
        // Externe Identität
        'klassen_id',
        'termin_id',

        // Kontext/Filter
        'institut_id',
        'vtz',
        'room',

        // Anzeige/Meta
        'title',
        'description',

        // Grobe Plan-Daten
        'planned_start_date',
        'planned_end_date',

        // Sync/Offline
        'source_snapshot',
        'source_last_upd',
        'type',
        'settings',
        'is_active',

        // Komfort: primärer Tutor (Person, nicht User)
        'primary_tutor_person_id',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'source_last_upd'    => 'datetime',
        'is_active'          => 'boolean',
        'settings'           => 'array',
        'source_snapshot'    => 'array',
    ];

    /**
     * Für dein UI: dynamische Counter.
     * participants_count = Anzahl Personen vom Typ 'participant'
     * dates_count        = Anzahl CourseDays
     */
    protected $appends = [
        'participants_count',
        'dates_count',
        'status',
        'status_label',
        'status_badge_classes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getParticipantsCountAttribute(): int
    {
        // zählt nur Teilnehmer (people.type = 'participant')
        return $this->participants()->count();
    }

    public function getDatesCountAttribute(): int
    {
        return $this->days()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    // Primärer Tutor (Komfort, optional)
    public function tutor()
    {
        return $this->belongsTo(Person::class, 'primary_tutor_person_id');
    }

    // Unterrichtstage (CourseDay)
    public function days()
    {
        return $this->hasMany(CourseDay::class);
    }

    // Alias, falls du im Code schon "dates()" benutzt hast:
    public function dates()
    {
        return $this->days();
    }


    public function enrollments()
    {
        return $this->hasMany(CourseParticipantEnrollment::class);
    }

    /**
     * Teilnehmer als Personen (nur aktive, nicht gelöschte Pivot-Reihen)
     */
    public function participants()
    {
        return $this->belongsToMany(Person::class, 'course_participant_enrollments', 'course_id', 'person_id')
            ->using(CourseParticipantEnrollment::class)
            ->withPivot([
                'id','teilnehmer_id','tn_baustein_id','baustein_id',
                'klassen_id','termin_id','vtz','kurzbez_ba',
                'status','is_active','results','notes',
                'source_snapshot','source_last_upd','last_synced_at',
                'deleted_at'
            ])
            ->as('enrollment')
            ->wherePivotNull('deleted_at')
            ->wherePivot('is_active', true);
    }


    // Falls du Kursbewertungen behalten willst
    public function ratings()
    {
        return $this->hasMany(CourseRating::class);
    }

    // FilePool (morphable) – lässt du wie gehabt
    public function filePool()
    {
        return $this->morphOne(FilePool::class, 'filepoolable');
    }



    // ---- STATUS: Accessors --------------------------------------------------

    public function getStatusAttribute(): string
    {
        $now   = now();
        $start = $this->planned_start_date;
        $end   = $this->planned_end_date;

        if ($start && $end) {
            if ($now->lt($start)) {
                return 'scheduled';
            }
            if ($now->between($start, $end)) {
                return 'active';
            }
            return 'completed';
        }

        if ($start && !$end) {
            return $now->lt($start) ? 'scheduled' : 'active';
        }

        return 'unknown';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Geplant',
            'active'    => 'Aktiv (läuft)',
            'completed' => 'Abgeschlossen',
            default     => '—',
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'px-2 py-1 text-xs font-semibold rounded bg-sky-50 text-sky-700',
            'active'    => 'px-2 py-1 text-xs font-semibold rounded bg-green-50 text-green-700',
            'completed' => 'px-2 py-1 text-xs font-semibold rounded bg-emerald-50 text-emerald-700',
            default     => 'text-xs text-gray-400',
        };
    }

    // ---- STATUS: kleine Helfer ---------------------------------------------

    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isRunning(): bool   { return $this->status === 'active'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }

        // ---- STATUS: Scopes (optional, nice to have) ----------------------------

    public function scopePlanned($q)
    {
        return $q->whereNotNull('planned_start_date')
                 ->whereDate('planned_start_date', '>', now()->toDateString());
    }

    public function scopeRunning($q)
    {
        return $q->whereNotNull('planned_start_date')
                 ->whereNotNull('planned_end_date')
                 ->whereDate('planned_start_date', '<=', now()->toDateString())
                 ->whereDate('planned_end_date', '>=', now()->toDateString())
               ->orWhere(function($qq){
                   // Falls Enddatum offen ist, aber bereits gestartet
                   $qq->whereNotNull('planned_start_date')
                      ->whereNull('planned_end_date')
                      ->whereDate('planned_start_date', '<=', now()->toDateString());
               });
    }

    public function scopeCompleted($q)
    {
        return $q->whereNotNull('planned_end_date')
                 ->whereDate('planned_end_date', '<', now()->toDateString());
    }

    // ---- (deine bestehenden Accessors/Relations/Scopes bleiben unverändert) ----



    /*
    |--------------------------------------------------------------------------
    | Scopes (praktisch fürs Admin-UI)
    |--------------------------------------------------------------------------
    */
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByKlassenId($q, string $klassenId)
    {
        return $q->where('klassen_id', $klassenId);
    }

    public function scopeOfInstitut($q, int $institutId)
    {
        return $q->where('institut_id', $institutId);
    }

    public function scopeWithCounts($q)
    {
        return $q
            ->withCount(['days as dates_count'])
            ->withCount([
                'participants as participants_count' => function ($sub) {
                    $sub->where('persons.type', 'participant'); // <— wichtig
                }
            ]);
    }


    // ---- Hilffunctionen zu Status zu Dokumentations, Roten Fäden und Teilnehmer Bestätigungen -----------------------------------------------

    public function hasDocumentation(): bool
    {
        return true;
    }

    public function hasRedThread(): bool
    {
        return false;
    }

    public function hasParticipantsConfirmed(): bool
    {
        return true;
    }

}
