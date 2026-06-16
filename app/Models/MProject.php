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
        'txtDescription',
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

    public function assignments()
    {
        return $this->hasMany(TrInternProject::class, 'intProject_ID', 'intProject_ID');
    }
}
