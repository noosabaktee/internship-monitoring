<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrInternProject extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trInternProject';
    protected $primaryKey = 'intInternProject_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intInternProject_ID',
        'intIntern_ID',
        'intProject_ID',
        'intMentor_ID',
        'floatProgress',
        'txtStatus',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'floatProgress' => 'float',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function project()
    {
        return $this->belongsTo(MProject::class, 'intProject_ID', 'intProject_ID');
    }

    public function mentor()
    {
        return $this->belongsTo(MMentor::class, 'intMentor_ID', 'intMentor_ID');
    }
}
