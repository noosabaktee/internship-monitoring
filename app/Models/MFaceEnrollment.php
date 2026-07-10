<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MFaceEnrollment extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mFaceEnrollment';
    protected $primaryKey = 'intFaceEnrollment_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intFaceEnrollment_ID',
        'intUser_ID',
        'txtFaceEnrollmentDescriptor',
        'txtFaceEnrollmentAlgorithm',
        'intFaceEnrollmentSampleCount',
        'floatFaceEnrollmentQuality',
        'dtmFaceEnrollmentRegistered',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'txtFaceEnrollmentDescriptor' => 'array',
        'intFaceEnrollmentSampleCount' => 'integer',
        'floatFaceEnrollmentQuality' => 'float',
        'dtmFaceEnrollmentRegistered' => 'datetime',
        'bitActive' => 'boolean',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }
}
