<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\CourseMaterialAcknowledgement;
use App\Models\File;
use App\Models\CourseDay;
use App\Models\CourseResult;
use App\Models\CourseRating;


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

    public function getCourseShortNameAttribute(): string
    {
        return data_get($this->source_snapshot, 'course.kurzbez', '');
    }

    public function getCourseClassNameAttribute(): string
    {
        return data_get($this->source_snapshot, 'course.klassen_co_ks', '');
    }


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



        public function results()
        {
            return $this->hasMany(CourseResult::class);
        }

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

    public function materialAcknowledgements()
{
    return $this->hasMany(CourseMaterialAcknowledgement::class);
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


/**
 * Rekursive UTF-8-Bereinigung für PDF-Daten.
 */
protected function sanitizePdfData($value)
{
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $k => $v) {
            $clean[$this->sanitizePdfData($k)] = $this->sanitizePdfData($v);
        }
        return $clean;
    }

    if (is_object($value)) {
        // Eloquent Collections / Models etc. durchlaufen
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn ($v) => $this->sanitizePdfData($v))->all();
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            // nur Attribute + Relations, die wir wirklich brauchen,
            // aber fürs PDF reicht meist ->toArray()
            return $this->sanitizePdfData($value->toArray());
        }

        return $value;
    }

    if (is_string($value)) {
        // Versuche, aus Latin1/Win-1252 etc. nach UTF-8 zu konvertieren
        $encoded = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        // Sicherstellen, dass der String wirklich gültiges UTF-8 ist
        return mb_check_encoding($encoded, 'UTF-8') ? $encoded : utf8_encode($encoded);
    }

    return $value;
}


  public function canExportAttendancePdf(): bool
    {
        // Du willst sie bewusst ausnehmen – hier könntest du auch immer true machen,
        // oder minimal prüfen, ob überhaupt Tage existieren.
        return $this->days()->exists();
    }

    public function canExportDokuPdf(): bool
    {
        // Doku nur, wenn es mind. einen Unterrichtstag Note gibt
        return $this->days()
            ->whereNotNull('notes')
            ->exists();
    }

    public function canExportMaterialConfirmationsPdf(): bool
    {
        // Sinnvoll: es gibt Teilnehmer UND mind. eine Bestätigung
        return $this->participants()->exists()
            && $this->materialAcknowledgements()->exists();
    }

    public function canExportInvoicePdf(): bool
    {
        // Nur wenn eine Rechnungsdatei hinterlegt ist
        return $this->files()
            ->where('type', 'invoice')
            ->exists();
    }

    public function canExportRedThreadPdf(): bool
{
    return $this->files()
        ->where('type', 'roter_faden')
        ->exists();
}

public function canExportExamResultsPdf(): bool
{
    // Export nur sinnvoll, wenn es überhaupt Ergebnisse gibt
    return $this->results()->exists();
}


public function generateAttendanceListPdfFile(): ?string
{
    $this->loadMissing(['days', 'participants', 'tutor']);

    $days = $this->days()
        ->orderBy('date')
        ->get();

    if ($days->isEmpty()) {
        return null;
    }

    $participants = $this->participants
        ->sortBy(fn ($p) => mb_strtoupper($p->nachname ?? $p->last_name ?? ''))
        ->values();

    $participantsById = $participants->keyBy('id');
    $personIds        = $participants->pluck('id')->all();

    /**
     * Matrix: [person_id][day_id] = [
     *   'morning_present'     => bool|null, // null nur bei "entschuldigt"
     *   'end_present'         => bool|null,
     *   'excused'             => bool,
     *   'late_minutes'        => int,
     *   'left_early_minutes'  => int,
     * ]
     */
    $attendanceMatrix = [];

    /**
     * Liste von Teil-Anwesenheiten für Seite 2:
     */
    $partials = [];

    foreach ($days as $day) {
        $att = $day->attendance_data ?? [];
        $map = Arr::get($att, 'participants', []);

        foreach ($personIds as $pid) {
            $row = $map[$pid] ?? null;

            // STANDARD: voll anwesend (x / x)
            $morningPresent    = true;
            $endPresent        = true;
            $excused           = false;
            $lateMinutes       = 0;
            $leftEarlyMinutes  = 0;

            if ($row) {
                // Wenn "present" nicht gesetzt ist → als true behandeln (Standard = anwesend)
                $presentRaw       = $row['present'] ?? null;
                $present          = is_null($presentRaw) ? true : (bool) $presentRaw;
                $excused          = (bool)($row['excused'] ?? false);
                $leftEarlyMinutes = (int)($row['left_early_minutes'] ?? 0);
                $lateMinutes      = (int)($row['late_minutes'] ?? 0);

                if ($excused) {
                    // Entschuldigt: im PDF als "E" (beide Zellen), keine true/false-Flags
                    $morningPresent = null;
                    $endPresent     = null;
                } else {
                    // Vormittag: nicht da ODER zu spät → F
                    if (!$present || $lateMinutes > 0) {
                        $morningPresent = false;
                    } else {
                        $morningPresent = true;
                    }

                    // Nachmittag / Ende: nicht da ODER früher gegangen → F
                    if (!$present || $leftEarlyMinutes > 0) {
                        $endPresent = false;
                    } else {
                        $endPresent = true;
                    }
                }

                // Für Seite 2: nur interessante Fälle (teilweise anwesend)
                if (!$excused && ($lateMinutes > 0 || $leftEarlyMinutes > 0)) {
                    $partials[] = [
                        'person_id'          => $pid,
                        'person'             => $participantsById[$pid] ?? null,
                        'date'               => $day->date,
                        'late_minutes'       => $lateMinutes,
                        'left_early_minutes' => $leftEarlyMinutes,
                        'excused'            => $excused,
                    ];
                }
            }

            $attendanceMatrix[$pid][$day->id] = [
                'morning_present'    => $morningPresent,
                'end_present'        => $endPresent,
                'excused'            => $excused,
                'late_minutes'       => $lateMinutes,
                'left_early_minutes' => $leftEarlyMinutes,
            ];
        }
    }

    $startDate = $days->first()->date;
    $endDate   = $days->last()->date;

    $firstDay  = $days->first();
    $startTime = $firstDay->start_time ?? null;

    $meta = [
        'date_from'   => $startDate,
        'date_to'     => $endDate,
        'num_days'    => $days->count(),
        'room'        => $this->room ?? 'online',
        'start_time'  => $startTime instanceof \Carbon\Carbon
            ? $startTime->format('H:i')
            : (is_string($startTime)
                ? \Carbon\Carbon::parse($startTime)->format('H:i')
                : null),
        'class_label' => $this->klassen_id,
        'module'      => $this->settings['kurzbez_ba'] ?? $this->title,
        'tutor_name'  => optional($this->tutor)->full_name
            ?? trim(($this->tutor->vorname ?? '').' '.($this->tutor->nachname ?? '')),
    ];

    // rows['cells'][day_id] = Matrix-Eintrag
    $rows = $participants->map(function ($person) use ($days, $attendanceMatrix) {
        $cells = [];

        foreach ($days as $day) {
            $cells[$day->id] = $attendanceMatrix[$person->id][$day->id] ?? [
                // Fallback, falls mal was fehlt → Standard: x / x
                'morning_present'    => true,
                'end_present'        => true,
                'excused'            => false,
                'late_minutes'       => 0,
                'left_early_minutes' => 0,
                'empty'              => true,
            ];
        }

        return [
            'person' => $person,
            'cells'  => $cells,
        ];
    });

    $html = view('pdf.courses.attendance-list', [
        'days'     => $days,
        'rows'     => $rows,
        'meta'     => $meta,
        'partials' => $partials,
    ])->render();

    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8, ISO-8859-1, Windows-1252');

    $pdf = Pdf::loadHTML($html)
        ->setPaper('a4', 'landscape');

    $tmpPath = tempnam(sys_get_temp_dir(), 'att_') . '.pdf';
    $pdf->save($tmpPath);

    return $tmpPath;
}



public function exportAttendanceListPdf(): ?StreamedResponse
{
    $path = $this->generateAttendanceListPdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Für diesen Kurs sind keine Unterrichtstage vorhanden.');
    }

    $filename = sprintf(
        'Klassen-Anwesenheitsliste_%s.pdf',
        $this->klassen_id ?: $this->id
    );

    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $filename);
}


public function generateDokuPdfFile(): ?string
{
    $this->loadMissing(['days', 'tutor']);

    $days = $this->days()
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    if ($days->isEmpty()) {
        return null;
    }

    $from = $days->first()->date instanceof Carbon
        ? $days->first()->date
        : Carbon::parse($days->first()->date);

    $to = $days->last()->date instanceof Carbon
        ? $days->last()->date
        : Carbon::parse($days->last()->date);

    $meta = [
        'date_from'   => $from,
        'date_to'     => $to,
        'num_days'    => $days->count(), // alle Tage, egal note_status
        'class_label' => $this->klassen_id,
        'module'      => $this->settings['kurzbez_ba'] ?? $this->title,
        'location'    => $this->room ?? '—',
        'year'        => $from->format('Y'),
        'tutor_name'  => optional($this->tutor)->full_name
            ?? trim(($this->tutor->vorname ?? '').' '.($this->tutor->nachname ?? '')),
    ];

    $rows = $days->map(function ($day, $idx) {
        $date = $day->date instanceof Carbon
            ? $day->date
            : Carbon::parse($day->date);

        $start = $day->start_time
            ? Carbon::parse($day->start_time)->format('H:i')
            : '08:00';

        $end = $day->end_time
            ? Carbon::parse($day->end_time)->format('H:i')
            : '16:00';

        $ue = $day->std ?? null;
        if (is_numeric($ue)) {
            $ue = (int) round((float) $ue);
        }

        // note_status auswerten
        $noteStatus = (int) ($day->note_status ?? 0);

        // Default: nur aufzählen, keine Inhalte / keine Signatur
        $notesHtml    = '';
        $ueValue      = null;
        $signatureSrc = null; // Base64 für <img src="...">

        // Nur bei note_status = 2 Inhalte + Signatur ausgeben
        if ($noteStatus === 2) {
            $notesHtml = $day->notes ?? '';
            $ueValue   = $ue;

            $signatureFile = $day->latestTutorSignature();
            $path          = $signatureFile?->getEphemeralPublicUrl();

            if ($signatureFile && $path) {
                $mime = $signatureFile->mime_type ?? 'image/png';

                $data = @file_get_contents($path);
                if ($data !== false) {
                    $signatureSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

        return [
            'index'               => $idx + 1,
            'date'                => $date,
            'time_range'          => $start.'-'.$end,
            'notes_html'          => $notesHtml,
            'ue'                  => $ueValue,
            'tutor_signature_src' => $signatureSrc,
            'note_status'         => $noteStatus,
        ];
    });

    $html = view('pdf.courses.documentation', [
        'meta' => $meta,
        'rows' => $rows,
    ])->render();

    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8, ISO-8859-1, Windows-1252');

    $pdf = Pdf::loadHTML($html)
        ->setPaper('a4', 'portrait');

    $tmpPath = tempnam(sys_get_temp_dir(), 'doku_') . '.pdf';
    $pdf->save($tmpPath);

    return $tmpPath;
}


public function exportDokuPdf(): ?StreamedResponse
{
    $path = $this->generateDokuPdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Für diesen Kurs sind keine Unterrichtstage vorhanden.');
    }

    $days = $this->days()
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $from = $days->first()->date instanceof Carbon
        ? $days->first()->date
        : Carbon::parse($days->first()->date);

    $filename = sprintf(
        'Unterrichtsdokumentation_%s.pdf',
        $this->klassen_id ?: $from->format('Y-m-d')
    );

    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $filename);
}

public function generateMaterialConfirmationsPdfFile(): ?string
{
    $participants = $this->participants
        ->sortBy(fn ($p) => mb_strtoupper($p->nachname ?? ''))
        ->values();

    if ($participants->isEmpty()) {
        return null;
    }

    $acks = $this->materialAcknowledgements()
        ->with(['person', 'enrollment', 'files'])
        ->get()
        ->groupBy('person_id');

    $rows = $participants->map(function ($person) use ($acks) {
        $list = $acks->get($person->id);
        $ack  = $list?->sortByDesc('acknowledged_at')->first();

        $signatureFile = $ack?->latestParticipantSignature();
        $signatureSrc  = null;

        if ($signatureFile) {
            $path = $signatureFile->getEphemeralPublicUrl();

            if ($path) {
                $mime = $signatureFile->mime_type ?? 'image/png';
                $data = @file_get_contents($path);

                if ($data !== false) {
                    $signatureSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

        return [
            'person'          => $person,
            'ack'             => $ack,
            'acknowledged_at' => $ack?->acknowledged_at,
            'signature_src'   => $signatureSrc,
        ];
    });

    $pdf = Pdf::loadView('pdf.courses.material-confirmations', [
        'course' => $this,
        'rows'   => $rows,
    ]);

    $tmpPath = tempnam(sys_get_temp_dir(), 'mat_') . '.pdf';
    $pdf->save($tmpPath);

    return $tmpPath;
}

public function exportMaterialConfirmationsPdf(): ?StreamedResponse
{
    $path = $this->generateMaterialConfirmationsPdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Keine Teilnehmer / Materialbestätigungen vorhanden.');
    }

    $filename = sprintf(
        'Material-Bestaetigungen_%s.pdf',
        $this->klassen_id ?: $this->id
    );
    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $filename);
}


public function generateInvoicePdfFile(): ?string
{
    $file = $this->files()
        ->where('type', 'invoice')
        ->latest()
        ->first();

    if (! $file) {
        return null;
    }

    $downloadUrl = $file->getEphemeralPublicUrl();
    if (! $downloadUrl) {
        return null;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'inv_') . '.pdf';

    $in  = @fopen($downloadUrl, 'rb');
    if (! $in) {
        return null;
    }

    $out = fopen($tmpPath, 'wb');
    while (! feof($in)) {
        fwrite($out, fread($in, 8192));
    }
    fclose($in);
    fclose($out);

    return $tmpPath;
}

public function exportInvoicePdf(): ?StreamedResponse
{
    $path = $this->generateInvoicePdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Keine Dozentenrechnung vorhanden.');
    }

    $downloadName = sprintf(
        'Dozenten-Rechnung_%s.pdf',
        $this->klassen_id ?: $this->id
    );

    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $downloadName);
}


public function generateRedThreadPdfFile(): ?string
{
    $file = $this->files()
        ->where('type', 'roter_faden')
        ->latest('id')
        ->first();

    if (! $file) {
        return null;
    }

    $downloadUrl = $file->getEphemeralPublicUrl();
    if (! $downloadUrl) {
        return null;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'rf_') . '.pdf';

    $in  = @fopen($downloadUrl, 'rb');
    if (! $in) {
        return null;
    }

    $out = fopen($tmpPath, 'wb');
    while (! feof($in)) {
        fwrite($out, fread($in, 8192));
    }
    fclose($in);
    fclose($out);

    return $tmpPath;
}

public function exportRedThreadPdf(): ?StreamedResponse
{
    $path = $this->generateRedThreadPdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Kein "Roter Faden"-Dokument vorhanden.');
    }

    $downloadName = sprintf(
        'Roter-Faden_%s.pdf',
        $this->klassen_id ?: $this->id
    );

    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $downloadName);
}


public function generateExamResultsPdfFile(): ?string
{
    // Relevante Relationen laden
    $this->loadMissing(['participants', 'results', 'tutor', 'days']);

    // Teilnehmer sortiert
    $participants = $this->participants
        ->sortBy(fn ($p) => mb_strtoupper($p->nachname ?? $p->last_name ?? ''))
        ->values();

    if ($participants->isEmpty()) {
        return null;
    }

    /** @var \Illuminate\Support\Collection<int,\App\Models\CourseResult> $results */
    $results = $this->results()
        ->orderBy('updated_at', 'desc')
        ->get();

    // Resultate nach person_id mappen (latest per Person)
    $resultsByPerson = $results
        ->groupBy('person_id')
        ->map(fn ($group) => $group->first());

    // Zeitraum / Meta
    $days = $this->days()
        ->orderBy('date')
        ->get();

    $from = $this->planned_start_date
        ?? ($days->first()?->date ?? null);

    $to = $this->planned_end_date
        ?? ($days->last()?->date ?? null);

    // Rows für das PDF: pro Teilnehmer + ggf. zugehöriges CourseResult
    $rows = $participants->map(function ($person) use ($resultsByPerson) {
        $result = $resultsByPerson->get($person->id); // CourseResult|null

        return [
            'person' => $person,
            'result' => $result,
        ];
    });

    $pdf = Pdf::loadView('pdf.courses.exam-results', [
        'course' => $this,
        'rows'   => $rows,
        'from'   => $from,
        'to'     => $to,
    ])->setPaper('a4', 'portrait');

    $tmpPath = tempnam(sys_get_temp_dir(), 'exam_') . '.pdf';
    $pdf->save($tmpPath);

    return $tmpPath;
}


public function exportExamResultsPdf(): ?StreamedResponse
{
    $path = $this->generateExamResultsPdfFile();

    if (! $path || ! file_exists($path)) {
        abort(404, 'Keine Prüfungsergebnisse für diesen Kurs gefunden.');
    }

    $filename = sprintf(
        'Pruefungsergebnisse_%s.pdf',
        $this->klassen_id ?: $this->id
    );

    return response()->streamDownload(function () use ($path) {
        readfile($path);
        @unlink($path);
    }, $filename);
}



    /**
     * Gibt es Bewertungen für diesen Kurs?
     */
    public function canExportCourseRatingsPdf(): bool
    {
        return $this->ratings()->exists();
    }

    /**
     * Erzeugt eine temporäre PDF-Datei mit den Baustein-Bewertungen.
     * Rückgabewert ist der Pfad zur temporären Datei oder null.
     */
    public function generateCourseRatingsPdfFile(): ?string
    {
        $this->loadMissing(['ratings', 'tutor', 'days']);

        /** @var \Illuminate\Support\Collection<int, \App\Models\CourseRating> $ratings */
        $ratings = $this->ratings()
            ->orderBy('created_at')
            ->get();

        if ($ratings->isEmpty()) {
            return null;
        }

        // Zeitraum / Termin-Label
        $days = $this->days()
            ->orderBy('date')
            ->get();

        $from = $this->planned_start_date
            ?? ($days->first()?->date ?? null);

        $to = $this->planned_end_date
            ?? ($days->last()?->date ?? null);

        $fromLabel = $from ? \Carbon\Carbon::parse($from)->format('d.m.Y') : '—';
        $toLabel   = $to   ? \Carbon\Carbon::parse($to)->format('d.m.Y')   : '—';

        $terminLabel = trim(($this->termin_id ?: '') . ' - ' . $fromLabel . ' bis ' . $toLabel);

        // Kleine Helper für Durchschnittswerte
        $avgField = function (string $field) use ($ratings): ?float {
            $values = $ratings
                ->pluck($field)
                ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
                ->map(fn ($v) => (float) $v);

            if ($values->isEmpty()) {
                return null;
            }

            return round($values->avg(), 2);
        };

        $avgCategory = function (array $fields) use ($avgField): ?float {
            $vals = collect($fields)
                ->map(fn ($f) => $avgField($f))
                ->filter(fn ($v) => $v !== null)
                ->values();

            if ($vals->isEmpty()) {
                return null;
            }

            return round($vals->avg(), 2);
        };

        // Sektionen im Stil deines PDFs
        $sections = [
            'kb' => [
                'label' => 'Kundenbetreuung',
                'avg'   => $avgCategory(['kb_1', 'kb_2', 'kb_3']),
                'questions' => [
                    [
                        'label' => 'Wie kompetent sind die Mitarbeiter/-innen der Kundenbetreuung?',
                        'avg'   => $avgField('kb_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('kb_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('kb_3'),
                    ],
                ],
            ],
            'sa' => [
                'label' => 'Systemadministration',
                'avg'   => $avgCategory(['sa_1', 'sa_2', 'sa_3']),
                'questions' => [
                    [
                        'label' => 'Wie kompetent sind die Mitarbeiter/-innen der Systemadministration?',
                        'avg'   => $avgField('sa_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('sa_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('sa_3'),
                    ],
                ],
            ],
            'il' => [
                'label' => 'Institutsleitung',
                'avg'   => $avgCategory(['il_1', 'il_2', 'il_3']),
                'questions' => [
                    [
                        'label' => 'Wie beurteilen Sie die Organisation im Institut?',
                        'avg'   => $avgField('il_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('il_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('il_3'),
                    ],
                ],
            ],
            'do' => [
                'label' => 'Dozent/-in',
                'avg'   => $avgCategory(['do_1', 'do_2', 'do_3']),
                'questions' => [
                    [
                        'label' => 'War der Dozent / die Dozentin Ihnen gegenüber freundlich und höflich?',
                        'avg'   => $avgField('do_1'),
                    ],
                    [
                        'label' => 'Wie beurteilen Sie die Fachkompetenz der/s Dozenten/-in?',
                        'avg'   => $avgField('do_2'),
                    ],
                    [
                        'label' => 'Wie beurteilen Sie ihre/seine methodischen und didaktischen Fähigkeiten?',
                        'avg'   => $avgField('do_3'),
                    ],
                ],
            ],
        ];

        // Meta-Infos für Kopfbereich
        $meta = [
            'class_label'   => $this->courseClassName,
            'module_label' => $this->courseShortName,
            'tutor_name'    => optional($this->tutor)->full_name
                ?? trim(($this->tutor->vorname ?? '').' '.($this->tutor->nachname ?? '')),
            'termin_label'  => $terminLabel,
            'ratings_count' => $ratings->count(),
        ];

        // View rendern
        $pdf = Pdf::loadView('pdf.courses.course-ratings', [
            'course'   => $this,
            'meta'     => $meta,
            'sections' => $sections,
            'ratings'  => $ratings,
        ])->setPaper('a4', 'portrait');

        $tmpPath = tempnam(sys_get_temp_dir(), 'rating_') . '.pdf';
        $pdf->save($tmpPath);

        return $tmpPath;
    }

    /**
     * Stream-Download für die Baustein-Bewertung.
     */
    public function exportCourseRatingsPdf(): ?StreamedResponse
    {
        $path = $this->generateCourseRatingsPdfFile();

        if (! $path || ! file_exists($path)) {
            abort(404, 'Für diesen Kurs sind keine Bewertungen vorhanden.');
        }

        $filename = sprintf(
            'Baustein-Bewertung_%s.pdf',
            $this->klassen_id ?: $this->id
        );

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $filename);
    }


    public function getExportBaseName(): string
    {
        return sprintf(
            '%s_Baustein_Export_%s',
            $this->courseShortName.'_'.$this->termin_id,
            now()->format('d-m-Y')
        );
    }

public function generateExportAllZipFile(array $settings = []): ?string
{
    $includeDocumentation = $settings['includeDocumentation'] ?? true;
    $includeRedThread     = $settings['includeRedThread']     ?? true;
    $includeParticipants  = $settings['includeParticipants']  ?? true;
    $includeAttendance    = $settings['includeAttendance']    ?? true;
    $includeExamResults   = $settings['includeExamResults']   ?? true;
    $includeTutorData     = $settings['includeTutorData']     ?? true;

    // Vorab prüfen, ob überhaupt irgendwas exportierbar ist
    $hasAny =
        ($includeAttendance    && $this->canExportAttendancePdf())             ||
        ($includeDocumentation && $this->canExportDokuPdf())                   ||
        ($includeParticipants  && $this->canExportMaterialConfirmationsPdf())  ||
        ($includeTutorData     && $this->canExportInvoicePdf())                ||
        ($includeRedThread     && $this->canExportRedThreadPdf())              ||
        ($includeExamResults   && $this->canExportExamResultsPdf());

    if (! $hasAny) {
        return null;
    }

    $zipPath   = tempnam(sys_get_temp_dir(), 'course_zip_');
    $zip       = new ZipArchive();
    $tempFiles = [];
    $addedAny  = false;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
    }

    // Anwesenheitsliste
    if ($includeAttendance && $this->canExportAttendancePdf()) {
        $path = $this->generateAttendanceListPdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'Anwesenheitsliste.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    // Unterrichtsdokumentation
    if ($includeDocumentation && $this->canExportDokuPdf()) {
        $path = $this->generateDokuPdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'Unterrichtsdokumentation.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    // Materialbestätigungen
    if ($includeParticipants && $this->canExportMaterialConfirmationsPdf()) {
        $path = $this->generateMaterialConfirmationsPdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'Materialbestaetigungen.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    // Dozentenrechnung
    if ($includeTutorData && $this->canExportInvoicePdf()) {
        $path = $this->generateInvoicePdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'Dozentenrechnung.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    // Roter Faden
    if ($includeRedThread && $this->canExportRedThreadPdf()) {
        $path = $this->generateRedThreadPdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'RoterFaden.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    // Prüfungsergebnisse
    if ($includeExamResults && $this->canExportExamResultsPdf()) {
        $path = $this->generateExamResultsPdfFile();
        if ($path && file_exists($path)) {
            $zip->addFile($path, 'Pruefungsergebnisse.pdf');
            $tempFiles[] = $path;
            $addedAny    = true;
        }
    }

    $zip->close();

    // Temp-PDFs nach dem Packen direkt löschen – sie stecken jetzt im ZIP
    foreach ($tempFiles as $file) {
        @unlink($file);
    }

    if (! $addedAny) {
        @unlink($zipPath);
        return null;
    }

    return $zipPath;
}

public function exportAll(array $settings = []): ?StreamedResponse
{
    $exportBaseName = $settings['exportName'] ?? $this->getExportBaseName();
    $zipFileName    = $exportBaseName . '.zip';

    $zipPath = $this->generateExportAllZipFile($settings);

    if (! $zipPath || ! file_exists($zipPath)) {
        abort(404, 'Für diesen Baustein gibt es keine exportierbaren Dokumente.');
    }

    return response()->streamDownload(function () use ($zipPath) {
        readfile($zipPath);
        @unlink($zipPath);
    }, $zipFileName);
}


}
