<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrProjectStage extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trProjectStage';
    protected $primaryKey = 'intProjectStage_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intProjectStage_ID',
        'intProject_ID',
        'intProjectStageNumber',
        'txtProjectStageStep',
        'dtmProjectStageStartDate',
        'dtmProjectStageEndDate',
        'floatProjectStagePlan',
        'floatProjectStageActual',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'intProjectStageNumber' => 'integer',
        'dtmProjectStageStartDate' => 'datetime',
        'dtmProjectStageEndDate' => 'datetime',
        'floatProjectStagePlan' => 'float',
        'floatProjectStageActual' => 'float',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(MProject::class, 'intProject_ID', 'intProject_ID');
    }
}
