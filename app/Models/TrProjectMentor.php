<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrProjectMentor extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trProjectMentor';
    protected $primaryKey = 'intProjectMentor_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intProjectMentor_ID',
        'intProject_ID',
        'intMentor_ID',
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

    public function project()
    {
        return $this->belongsTo(MProject::class, 'intProject_ID', 'intProject_ID');
    }

    public function mentor()
    {
        return $this->belongsTo(MMentor::class, 'intMentor_ID', 'intMentor_ID');
    }
}
