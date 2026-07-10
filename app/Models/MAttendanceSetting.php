<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MAttendanceSetting extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mAttendanceSetting';
    protected $primaryKey = 'intAttendanceSetting_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intAttendanceSetting_ID',
        'txtAttendanceSettingStartTime',
        'txtAttendanceSettingEndTime',
        'txtAttendanceSettingClockInStartTime',
        'txtAttendanceSettingClockInEndTime',
        'txtAttendanceSettingClockOutStartTime',
        'txtAttendanceSettingClockOutEndTime',
        'floatAttendanceSettingFaceThreshold',
        'bitAttendanceSettingLocationRequired',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'floatAttendanceSettingFaceThreshold' => 'float',
        'bitAttendanceSettingLocationRequired' => 'boolean',
        'bitActive' => 'boolean',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];
}
