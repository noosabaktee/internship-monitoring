<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrAttendance extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trAttendance';

    protected $primaryKey = 'intAttendance_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intAttendance_ID',
        'intIntern_ID',
        'intUser_ID',
        'intAttendanceLocation_ID',
        'intWorkFromHomeRequest_ID',
        'txtAttendanceWorkMode',
        'dtmAttendanceDate',
        'dtmCheckIn',
        'dtmCheckOut',
        'txtFaceDescriptorMatch',
        'floatLatitude',
        'floatLongitude',
        'txtLocationName',
        'txtStatus',
        'txtNotes',
        'dtmAttendanceClockIn',
        'dtmAttendanceClockOut',
        'txtAttendanceStatus',
        'floatAttendanceLatitude',
        'floatAttendanceLongitude',
        'floatAttendanceLocationAccuracy',
        'floatAttendanceDistanceMeter',
        'floatAttendanceAllowedDistanceMeter',
        'bitAttendanceWithinTolerance',
        'txtAttendanceAddress',
        'txtAttendanceLocationUrl',
        'txtAttendanceClockInStatus',
        'floatAttendanceFaceDistance',
        'txtAttendanceFaceAlgorithm',
        'txtAttendanceDevice',
        'floatAttendanceClockOutLatitude',
        'floatAttendanceClockOutLongitude',
        'floatAttendanceClockOutLocationAccuracy',
        'floatAttendanceClockOutDistanceMeter',
        'bitAttendanceClockOutWithinTolerance',
        'txtAttendanceClockOutAddress',
        'txtAttendanceClockOutLocationUrl',
        'txtAttendanceClockOutStatus',
        'floatAttendanceClockOutFaceDistance',
        'txtAttendanceClockOutFaceAlgorithm',
        'txtAttendanceClockOutDevice',
        'txtAttendanceClockOutNote',
        'txtAttendanceNote',
        'txtInsertedBy',
        'dtmInserted',
    ];

    protected $casts = [
        'dtmAttendanceDate' => 'date',
        'dtmCheckIn' => 'datetime',
        'dtmCheckOut' => 'datetime',
        'txtFaceDescriptorMatch' => 'array',
        'floatLatitude' => 'float',
        'floatLongitude' => 'float',
        'dtmAttendanceClockIn' => 'datetime',
        'dtmAttendanceClockOut' => 'datetime',
        'floatAttendanceLatitude' => 'float',
        'floatAttendanceLongitude' => 'float',
        'floatAttendanceLocationAccuracy' => 'float',
        'floatAttendanceFaceDistance' => 'float',
        'floatAttendanceClockOutLatitude' => 'float',
        'floatAttendanceClockOutLongitude' => 'float',
        'floatAttendanceClockOutLocationAccuracy' => 'float',
        'floatAttendanceDistanceMeter' => 'float',
        'floatAttendanceAllowedDistanceMeter' => 'float',
        'bitAttendanceWithinTolerance' => 'boolean',
        'floatAttendanceClockOutDistanceMeter' => 'float',
        'bitAttendanceClockOutWithinTolerance' => 'boolean',
        'floatAttendanceClockOutFaceDistance' => 'float',
        'dtmInserted' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }

    public function attendanceLocation()
    {
        return $this->belongsTo(MAttendanceLocation::class, 'intAttendanceLocation_ID', 'intAttendanceLocation_ID');
    }

    public function workFromHomeRequest()
    {
        return $this->belongsTo(TrWorkFromHomeRequest::class, 'intWorkFromHomeRequest_ID', 'intWorkFromHomeRequest_ID');
    }
}
