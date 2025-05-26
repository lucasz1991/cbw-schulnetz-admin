<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Jobs\ClaimRatingAIEval;


class ClaimRating extends Model
{
    use HasFactory, SoftDeletes;
    // Possible statuses for the ClaimRating model:
    // - pending: The claim rating is awaiting review.
    // - approved: The claim rating has been approved.
    // - rejected: The claim rating has been rejected.
    protected $fillable = [
        'user_id',
        'insurance_subtype_id',
        'insurance_type_id',
        'insurance_id',
        'rating_questionnaire_versions_id',
        'answers',
        'status',
        'attachments',
        'rating_score',
        'moderator_comment',
        'is_public',
        'verification_hash',
    ];

    protected $casts = [
        'answers' => 'array',
        'attachments' => 'array',
        'is_public' => 'boolean',
    ];

    public function reanalyse(){
        $this->status = 'rating';
        $this->saveQuietly();
        ClaimRatingAIEval::dispatch($this);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function insuranceType()
    {
        return $this->belongsTo(InsuranceType::class);
    }
    
    public function insuranceSubtype()
    {
        return $this->belongsTo(InsuranceSubtype::class);
    }

    public function questionnaireVersion()
    {
        return $this->belongsTo(RatingQuestionnaireVersion::class);
    }
}
