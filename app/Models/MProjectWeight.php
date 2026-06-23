<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MProjectWeight extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mProjectWeight';
    protected $primaryKey = 'intProjectWeight_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intProjectWeight_ID',
        'intProjectWeightMain',
        'intProjectWeightCollaboration',
        'intProjectWeightSatellite',
        'intProjectWeightSharing',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'intProjectWeightMain' => 'integer',
        'intProjectWeightCollaboration' => 'integer',
        'intProjectWeightSatellite' => 'integer',
        'intProjectWeightSharing' => 'integer',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];
}
