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
use Illuminate\Support\Facades\Cache;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';
    
    public const API_UPDATE_COOLDOWN_MINUTES = 30;

    protected $fillable = [
        'user_id',
        'person_id',
        'institut_id',
        'person_nr',
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

}
