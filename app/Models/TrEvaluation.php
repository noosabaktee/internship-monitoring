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
        'dtmPeriod',
        'floatHardSkill',
        'floatCollaboration',
        'floatOwnership',
        'floatSharing',
        'floatExposureScore',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmPeriod' => 'datetime',
        'floatHardSkill' => 'float',
        'floatCollaboration' => 'float',
        'floatOwnership' => 'float',
        'floatSharing' => 'float',
        'floatExposureScore' => 'float',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }
}
