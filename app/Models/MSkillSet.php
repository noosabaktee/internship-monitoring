<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MSkillSet extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mSkillSet';
    protected $primaryKey = 'intSkillSet_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intSkillSet_ID',
        'txtSkillSetName',
        'txtSkillSetDescription',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function projects()
    {
        return $this->hasMany(MProject::class, 'intSkillSet_ID', 'intSkillSet_ID');
    }
}
