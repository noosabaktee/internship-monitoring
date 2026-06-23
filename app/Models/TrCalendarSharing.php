<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrCalendarSharing extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trCalendarSharing';
    protected $primaryKey = 'intCalendarSharing_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intCalendarSharing_ID',
        'intCalendarSharingCreatorUser_ID',
        'txtCalendarSharingTheme',
        'txtCalendarSharingObjective',
        'txtCalendarSharingDescription',
        'txtCalendarSharingTargetAudience',
        'dtmCalendarSharingDate',
        'txtCalendarSharingStatus',
        'txtCalendarSharingIcon',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmCalendarSharingDate' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(MUser::class, 'intCalendarSharingCreatorUser_ID', 'intUser_ID');
    }
}
