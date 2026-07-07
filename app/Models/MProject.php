<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MProject extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mProject';
    protected $primaryKey = 'intProject_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intProject_ID',
        'txtProjectName',
        'txtProjectType',
        'intSkillSet_ID',
        'dtmProjectStartDate',
        'dtmProjectEndDate',
        'txtDescription',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmProjectStartDate' => 'datetime',
        'dtmProjectEndDate' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(TrInternProject::class, 'intProject_ID', 'intProject_ID');
    }

    public function projectMentors()
    {
        return $this->hasMany(TrProjectMentor::class, 'intProject_ID', 'intProject_ID');
    }

    public function stages()
    {
        return $this->hasMany(TrProjectStage::class, 'intProject_ID', 'intProject_ID')
            ->where('bitActive', true)
            ->orderBy('intProjectStageNumber');
    }

    public function skillSet()
    {
        return $this->belongsTo(MSkillSet::class, 'intSkillSet_ID', 'intSkillSet_ID');
    }
}
