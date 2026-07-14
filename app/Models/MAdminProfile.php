<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MAdminProfile extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mAdminProfile';
    protected $primaryKey = 'intAdminProfile_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intAdminProfile_ID',
        'intUser_ID',
        'txtAdminProfileName',
        'txtAdminProfileGender',
        'txtAdminProfileDepartment',
        'txtAdminProfilePosition',
        'txtAdminProfilePhone',
        'txtAdminProfileBio',
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

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }
}
