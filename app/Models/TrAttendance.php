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
        'txtAttendanceStatus',
        'floatAttendanceLatitude',
        'floatAttendanceLongitude',
        'floatAttendanceLocationAccuracy',
        'txtAttendanceAddress',
        'txtAttendanceLocationUrl',
        'floatAttendanceFaceDistance',
        'txtAttendanceFaceAlgorithm',
        'txtAttendanceDevice',
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
        'floatAttendanceLatitude' => 'float',
        'floatAttendanceLongitude' => 'float',
        'floatAttendanceLocationAccuracy' => 'float',
        'floatAttendanceFaceDistance' => 'float',
        'dtmInserted' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }
}
