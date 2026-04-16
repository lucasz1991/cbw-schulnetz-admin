<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Facades\Log;
use App\Jobs\ApiUpdates\PersonApiUpdate;
use App\Models\CourseResult;
use App\Models\CourseRating;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persons';
    
    public const API_UPDATE_COOLDOWN_MINUTES = 30;

    protected static bool $syncLinkedUserPortalRoleEnabled = true;

    protected $fillable = [
        'user_id',
        'person_id',
        'institut_id',
        'person_nr',
        'teilnehmer_nr',
        'teilnehmer_id',
        'role',
        'status',
        'upd_date',
        'nachname',
        'vorname',
        'geschlecht',
        'titel_kennz',
        'nationalitaet',
        'familien_stand',
        'geburt_datum',
        'geburt_name',
        'geburt_land',
        'geburt_ort',
        'lkz',
        'plz',
        'ort',
        'strasse',
        'adresszusatz1',
        'adresszusatz2',
        'plz_pf',
        'postfach',
        'plz_gk',
        'telefon1',
        'telefon2',
        'person_kz',
        'plz_alt',
        'ort_alt',
        'strasse_alt',
        'telefax',
        'kunden_nr',
        'stamm_nr_aa',
        'stamm_nr_bfd',
        'stamm_nr_sons',
        'stamm_nr_kst',
        'kostentraeger',
        'bkz',
        'email_priv',
        'email_cbw',
        'geb_mmtt',
        'org_zeichen',
        'personal_nr',
        'kred_nr',
        'angestellt_von',
        'angestellt_bis',
        'leer',
        'programdata',
        'statusdata',
        'last_api_update',
    ];

    protected $casts = [
        'upd_date' => 'datetime',
        'geburt_datum' => 'date',
        'angestellt_von' => 'datetime',
        'angestellt_bis' => 'datetime',
        'programdata' => 'array',
        'statusdata' => 'array',
        'last_api_update' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($person) {
            $person->apiupdate();
        });

        
        static::retrieved(function (Person $person) {
            // nur sinnvoll, wenn mit User verknüpft
            if (empty($person->user_id) || !empty($person->programdata)) {
                return;
            }

            static::dispatchApiUpdateIfNotThrottled($person, 'retrieved');
        });

        static::saved(function (Person $person) {
            if (! static::$syncLinkedUserPortalRoleEnabled) {
                return;
            }

            $person->syncLinkedUserPortalRole();

            $originalUserId = $person->getOriginal('user_id');
            if (! empty($originalUserId) && (int) $originalUserId !== (int) $person->user_id) {
                User::find($originalUserId)?->syncPortalRoleFromPersons();
            }
        });

        static::deleted(function (Person $person) {
            if (! static::$syncLinkedUserPortalRoleEnabled) {
                return;
            }

            $person->syncLinkedUserPortalRole();
        });

        static::restored(function (Person $person) {
            if (! static::$syncLinkedUserPortalRoleEnabled) {
                return;
            }

            $person->syncLinkedUserPortalRole();
        });
    }

    public static function withoutUserPortalRoleSync(callable $callback): mixed
    {
        $previous = static::$syncLinkedUserPortalRoleEnabled;
        static::$syncLinkedUserPortalRoleEnabled = false;

        try {
            return $callback();
        } finally {
            static::$syncLinkedUserPortalRoleEnabled = $previous;
        }
    }

    public function apiupdate()
    {
        PersonApiUpdate::dispatch($this->id);
    }

        protected static function dispatchApiUpdateIfNotThrottled(Person $person, string $source): void
    {
        if (empty($person->user_id) || empty($person->id) || !empty($person->programdata)) {
            return;
        }

        $cacheKey = "person_apiupdate_cooldown:{$person->id}";

        // add() legt den Key nur an, wenn er noch nicht existiert
        $payload = [
            'last'   => now()->toDateTimeString(),
            'source' => $source,
        ];

        // Wenn Key bereits existiert -> wir sind im Cooldown -> nichts tun
        if (! Cache::add($cacheKey, $payload, now()->addMinutes(self::API_UPDATE_COOLDOWN_MINUTES))) {
            return;
        }

        // Außerhalb des Cooldowns: Job dispatchen
        PersonApiUpdate::dispatch($person->id);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolvePortalRoleCandidate(): ?string
    {
        if (! $this->hasPortalIdentity()) {
            return null;
        }

        if ($this->hasValidTutorContract()) {
            return 'tutor';
        }

        if ($this->hasValidParticipantContract()) {
            return 'guest';
        }

        return null;
    }

    public function hasPortalIdentity(): bool
    {
        $statusData = is_array($this->statusdata) ? $this->statusdata : [];

        $teilnehmerId = $statusData['teilnehmer_id']
            ?? data_get($statusData, 'vertraege.0.teilnehmer_id')
            ?? $this->teilnehmer_id
            ?? data_get($this->programdata, 'teilnehmer_id');
        $mitarbeiterId = $statusData['mitarbeiter_id'] ?? data_get($this->programdata, 'tutor.mitarbeiter_id');

        return ! empty($teilnehmerId) || ! empty($mitarbeiterId);
    }

    public function portalRolePriority(): int
    {
        return match ($this->resolvePortalRoleCandidate()) {
            'tutor' => 2,
            'guest' => 1,
            default => 0,
        };
    }

    public function portalRoleSortTimestamp(): int
    {
        $activeContracts = $this->activeParticipantContracts();

        if ($activeContracts->isNotEmpty()) {
            $maxContractTs = $activeContracts
                ->map(fn (array $vertrag) => $this->parsePortalContractDate($vertrag['vertrag_ende'] ?? null)?->endOfDay()->timestamp ?? 0)
                ->max();

            if (is_numeric($maxContractTs) && (int) $maxContractTs > 0) {
                return (int) $maxContractTs;
            }
        }

        $programEnd = $this->parsePortalContractDate(data_get($this->programdata, 'vertrag_ende'));
        if ($programEnd) {
            return $programEnd->endOfDay()->timestamp;
        }

        return $this->last_api_update?->timestamp ?? 0;
    }

    public function hasValidTutorContract(): bool
    {
        $statusData = is_array($this->statusdata) ? $this->statusdata : [];
        $vertragKy = strtoupper(trim((string) ($statusData['mitarbeiter_vertrag_ky'] ?? '')));

        return filter_var($statusData['is_tutor'] ?? false, FILTER_VALIDATE_BOOL) || $vertragKy === 'IS';
    }

    public function hasValidParticipantContract(): bool
    {
        if ($this->activeParticipantContracts()->isNotEmpty()) {
            return true;
        }

        $statusData = is_array($this->statusdata) ? $this->statusdata : [];
        $status = strtolower(trim((string) ($statusData['status'] ?? '')));

        if ($status !== 'teilnehmer') {
            return false;
        }

        $teilnehmerId = $statusData['teilnehmer_id']
            ?? data_get($statusData, 'vertraege.0.teilnehmer_id')
            ?? $this->teilnehmer_id
            ?? data_get($this->programdata, 'teilnehmer_id');
        $teilnehmerNr = $statusData['teilnehmer_nr'] ?? $this->teilnehmer_nr ?? data_get($this->programdata, 'teilnehmer_nr');

        if (empty($teilnehmerId) && empty($teilnehmerNr)) {
            return false;
        }

        $today = Carbon::today('Europe/Berlin');
        $vertragEnde = $this->parsePortalContractDate(data_get($this->programdata, 'vertrag_ende'));
        $kuendigZum = $this->parsePortalContractDate(data_get($this->programdata, 'kuendig_zum'));

        if ($kuendigZum && $kuendigZum->endOfDay()->lt($today)) {
            return false;
        }

        if ($vertragEnde && $vertragEnde->endOfDay()->lt($today)) {
            return false;
        }

        return ! empty($this->programdata) || ! empty($statusData);
    }

    public function enrollments()
    {
        return $this->hasMany(CourseParticipantEnrollment::class);
    }

    public function courseResults()
    {
        return $this->hasMany(CourseResult::class, 'person_id');
    }

    public function courseRatings()
    {
        return $this->hasMany(CourseRating::class, 'participant_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_participant_enrollments')
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

    /** Kurse, in denen die Person primärer Tutor ist */
    public function taughtCourses()
    {
        return $this->hasMany(Course::class, 'primary_tutor_person_id');
    }

    protected function syncLinkedUserPortalRole(): void
    {
        if (empty($this->user_id)) {
            return;
        }

        $user = $this->user()->first();
        if (! $user) {
            return;
        }

        $user->syncPortalRoleFromPersons();
    }

    protected function activeParticipantContracts(): Collection
    {
        $statusContracts = collect(data_get($this->statusdata, 'vertraege', []))
            ->filter(fn ($vertrag) => is_array($vertrag));

        if ($statusContracts->isEmpty()) {
            return collect();
        }

        $today = Carbon::today('Europe/Berlin');

        return $statusContracts->filter(function (array $vertrag) use ($today) {
            if (! filter_var($vertrag['is_active'] ?? false, FILTER_VALIDATE_BOOL)) {
                return false;
            }

            $vertragEnde = $this->parsePortalContractDate($vertrag['vertrag_ende'] ?? null);

            return ! $vertragEnde || $vertragEnde->endOfDay()->gte($today);
        })->values();
    }

    protected function parsePortalContractDate(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y/m/d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'Europe/Berlin')->startOfDay();
            } catch (\Throwable $e) {
                // try next format
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw), 'Europe/Berlin')->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

}
