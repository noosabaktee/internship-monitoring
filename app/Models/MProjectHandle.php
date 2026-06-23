<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MProjectHandle extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mProjectHandle';
    protected $primaryKey = 'intProjectHandle_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intProjectHandle_ID',
        'txtProjectHandleDuration',
        'intProjectHandleMain',
        'intProjectHandleCollaboration',
        'intProjectHandleSatellite',
        'intProjectHandleSharing',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'intProjectHandleMain' => 'integer',
        'intProjectHandleCollaboration' => 'integer',
        'intProjectHandleSatellite' => 'integer',
        'intProjectHandleSharing' => 'integer',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];
}
