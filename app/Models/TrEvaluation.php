<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrEvaluation extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trEvaluation';

    protected $primaryKey = 'intEvaluation_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intEvaluation_ID',
        'intIntern_ID',
        'intEvaluatorUser_ID',
        'dtmEvaluationCompleted',
        'floatHardSkill',
        'floatCollaboration',
        'floatOwnership',
        'floatSharing',
        'floatExposureScore',
        'txtEvaluationStrength',
        'txtEvaluationDevelopment',
        'txtEvaluationRecommendation',
        'bitEvaluationCertificatePublished',
        'dtmEvaluationCertificatePublished',
        'intEvaluationCertificatePublishedByUser_ID',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmEvaluationCompleted' => 'date',
        'floatHardSkill' => 'float',
        'floatCollaboration' => 'float',
        'floatOwnership' => 'float',
        'floatSharing' => 'float',
        'floatExposureScore' => 'float',
        'bitEvaluationCertificatePublished' => 'boolean',
        'dtmEvaluationCertificatePublished' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function evaluator()
    {
        return $this->belongsTo(MUser::class, 'intEvaluatorUser_ID', 'intUser_ID');
    }

    public function certificatePublisher()
    {
        return $this->belongsTo(MUser::class, 'intEvaluationCertificatePublishedByUser_ID', 'intUser_ID');
    }
}
