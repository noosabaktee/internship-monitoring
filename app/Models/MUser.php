<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MUser extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mUser';
    protected $primaryKey = 'intUser_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intUser_ID',
        'txtEmail',
        'txtPassword',
        'txtRole',
        'txtProfilePhoto',
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

    public function intern()
    {
        return $this->hasOne(MIntern::class, 'intUser_ID', 'intUser_ID');
    }

    public function mentor()
    {
        return $this->hasOne(MMentor::class, 'intUser_ID', 'intUser_ID');
    }

    public function calendarSharings()
    {
        return $this->hasMany(TrCalendarSharing::class, 'intCalendarSharingCreatorUser_ID', 'intUser_ID');
    }
}
