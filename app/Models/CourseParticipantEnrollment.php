<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseParticipantEnrollment extends Pivot
{
    use SoftDeletes;

    protected $table = 'course_participant_enrollments';

    // Falls die Pivot-Tabelle eine eigene Auto-ID hat:
    public $incrementing = true;
    protected $primaryKey = 'id';
    protected $keyType = 'int';

    // Wenn deine Pivot-Tabelle KEINE created_at/updated_at hat, dann:
    // public $timestamps = false;

    protected $fillable = [
        'course_id','person_id',
        'teilnehmer_id','tn_baustein_id','baustein_id',
        'klassen_id','termin_id','vtz','kurzbez_ba',
        'status','is_active',
        'results','notes',
        'source_snapshot','source_last_upd','last_synced_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'results'          => 'array',
        'notes'            => 'array',
        'source_snapshot'  => 'array',
        'source_last_upd'  => 'datetime',
        'last_synced_at'   => 'datetime',
    ];

    public function course() { return $this->belongsTo(Course::class); }
    public function person() { return $this->belongsTo(Person::class); }
}
