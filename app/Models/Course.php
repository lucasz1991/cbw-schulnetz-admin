<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;


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

    public function files(): MorphMany
    {
        return $this->morphMany(\App\Models\File::class, 'fileable');
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

    // ---- Gemeinsames Status-Mapping -------------------------------------------
    /**
     * 0 = fehlt (rot), 1 = ok (grün), 2 = teilweise/ausstehend (gelb)
     */
    public function assetsStateMeta(int $state): array
    {
        return match ($state) {
            0 => ['icon' => 'fad fa-times-circle',  'color' => 'text-red-600',    'bg' => 'bg-white',   'title' => 'Fehlt'],
            1 => ['icon' => 'fad fa-check-circle',  'color' => 'text-green-600',  'bg' => 'bg-white',   'title' => 'Vollständig'],
            2 => ['icon' => 'fad fa-spinner',       'color' => 'text-yellow-600', 'bg' => 'bg-white',   'title' => 'Teilweise / ausstehend'],
            default => ['icon' => 'fad fa-question-circle','color'=>'text-gray-400','bg'=>'bg-gray-50', 'title' => 'Unbekannt'],
        };
    }

    // Optional: ein kleiner Renderer, falls du im Blade direkt ein <i> ausgeben willst.
    protected function renderAssetIcon(string $baseTitle, int $state): string
    {
        $m = $this->assetsStateMeta($state);
        return sprintf('<i class="%s %s" title="%s: %s"></i>', $m['icon'], $m['color'], e($baseTitle), e($m['title']));
    }


    /**
     * 0 = keine/fehlende Doku, 1 = alle vergangene Tage dokumentiert, 2 = teils dokumentiert
     */
    public function documentationState(): int
    {
        $today = now()->toDateString();

        $pastDays = $this->days()
            ->whereDate('date', '<=', $today)   // Feldnamen ggf. anpassen
            ->get(['id', 'notes']);

        if ($pastDays->isEmpty()) {
            // Keine vergangenen Tage -> als "fehlt" werten (0) oder 1, wenn du "nichts zu dokumentieren" als ok siehst
            return 0;
        }

        $total  = $pastDays->count();
        $filled = $pastDays->filter(fn ($d) => trim((string)$d->notes) !== '')->count();

        if ($filled === 0)        return 0;
        if ($filled < $total)     return 2;
        return 1;
    }

    // Optionales Icon (wenn du direkt im Blade ohne Logik ausgeben willst)
    public function getDocumentationIconHtmlAttribute(): string
    {
        return $this->renderAssetIcon('Dokumentation', $this->documentationState());
    }

    /**
     * 0 = fehlt, 1 = vorhanden, 2 = (optional) teilw./ausstehend – falls du z. B. mehrere Pflichtdateien erwartest
     */
    public function redThreadState(): int
    {
        $exists = $this->files()
            ->where('type',  'roter_faden')
            ->exists();

        return $exists ? 1 : 0;
    }

    public function getRedThreadIconHtmlAttribute(): string
    {
        return $this->renderAssetIcon('Roter Faden', $this->redThreadState());
    }


    public function participantsConfirmationsState(): int
    {
        // Distinct aktive Teilnehmer zählen
        $total = DB::table('course_participant_enrollments')
            ->where('course_id', $this->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->distinct()
            ->count('person_id');

        if ($total === 0) {
            // Kein Teilnehmer -> als "fehlt" werten (0). 
            // Falls du das als "ok" sehen willst, ändere auf "return 1;"
            return 0;
        }

        // Distinct bestätigte Personen (nur mit acknowledged_at) für diesen Kurs
        $ack = DB::table('course_material_acknowledgements')
            ->where('course_id', $this->id)
            ->whereNotNull('acknowledged_at')
            ->distinct()
            ->count('person_id');

        if ($ack === 0)       return 0; // keiner bestätigt
        if ($ack < $total)    return 2; // teilweise / ausstehend
        return 1;                       // alle bestätigt
    }


    public function getParticipantsConfirmationsIconHtmlAttribute(): string
    {
        return $this->renderAssetIcon('Teilnahmebestätigungen', $this->participantsConfirmationsState());
    }

    public function invoiceState()
    {
        $exists = $this->files()
            ->where('type',  'invoice')
            ->exists();

        return $exists ? 1 : 0;
    }

    public function getInvoiceIconHtmlAttribute(): string
    {
        return $this->renderAssetIcon('Dozenten Rechnung', $this->invoiceState());
    }



    // ---- STATUS: Icon/Badge-Meta ----------------------------------------------

    /**
     * Liefert konsolidierte Meta-Daten für Status-Anzeige.
     * icon  = FontAwesome 5 Klasse (du nutzt "fad")
     * color = Tailwind Text-Farbe
     * bg    = Tailwind Hintergrund (für Badge-Variante)
     * title = Tooltip-Text
     * size  = Icon-Größe
     */
    public function statusMeta(): array
    {
        return match ($this->status) {
            'scheduled' => [
                'icon'  => 'far fa-clock',
                'color' => 'text-yellow-600',
                'bg'    => 'bg-yellow-100',
                'title' => 'Geplant',
                'size'  => 'text-lg',
            ],
            'active' => [
                'icon'  => 'fad fa-play-circle',
                'color' => 'text-green-600',
                'bg'    => 'bg-green-100',
                'title' => 'Aktiv (läuft)',
                'size'  => 'text-lg',
            ],
            'completed' => [
                'icon'  => 'fad fa-check-circle',
                'color' => 'text-blue-600',
                'bg'    => 'bg-blue-100',
                'title' => 'Abgeschlossen',
                'size'  => 'text-lg',
            ],
            default => [
                'icon'  => 'fad fa-question-circle',
                'color' => 'text-gray-400',
                'bg'    => 'bg-gray-50',
                'title' => 'Unbekannt',
                'size'  => 'text-lg',
            ],
        };
    }

    /** Klassenstring für das Icon (ohne title) */
    public function getStatusIconAttribute(): string
    {
        $m = $this->statusMeta();
        return trim("{$m['icon']} {$m['color']} {$m['size']}");
    }

    /** Tooltip-Text */
    public function getStatusIconTitleAttribute(): string
    {
        return $this->statusMeta()['title'];
    }

    /** Kompletter <i> Tag als HTML (für {!! !!}) */
    public function getStatusIconHtmlAttribute(): string
    {
        $m = $this->statusMeta();
        $classes = trim("{$m['icon']} {$m['color']} {$m['size']}");
        return '<i class="'.$classes.'" title="'.e($m['title']).'"></i>';
    }

    /**
     * Kleine Badge/Pill mit Icon (ohne Text, nur Tooltip).
     * Beispiel-Styling: dezenter BG + abgerundet.
     */
    public function getStatusPillHtmlAttribute(): string
    {
        $m = $this->statusMeta();
        $iconOnly = '<i class="'.$m['icon'].' '.$m['color'].'"></i>';

        return '<span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded '.$m['bg'].' '.$m['color'].'"
                    title="'.e($m['title']).'">'.$iconOnly.'</span>';
    }

}
