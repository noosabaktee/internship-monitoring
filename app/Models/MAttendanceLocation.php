<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MAttendanceLocation extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mAttendanceLocation';

    protected $primaryKey = 'intAttendanceLocation_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intAttendanceLocation_ID',
        'txtAttendanceLocationCode',
        'txtAttendanceLocationName',
        'txtAttendanceLocationAddress',
        'floatAttendanceLocationLatitude',
        'floatAttendanceLocationLongitude',
        'intAttendanceLocationRadiusMeter',
        'intAttendanceLocationToleranceMeter',
        'intAttendanceLocationMaximumAccuracyMeter',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
        'bitActive',
    ];

    protected $casts = [
        'floatAttendanceLocationLatitude' => 'float',
        'floatAttendanceLocationLongitude' => 'float',
        'intAttendanceLocationRadiusMeter' => 'integer',
        'intAttendanceLocationToleranceMeter' => 'integer',
        'intAttendanceLocationMaximumAccuracyMeter' => 'integer',
        'bitActive' => 'boolean',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];
}
