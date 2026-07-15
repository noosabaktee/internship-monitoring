<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrNotification extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trNotification';

    protected $primaryKey = 'intNotification_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intNotification_ID',
        'intUser_ID',
        'txtNotificationType',
        'txtNotificationTitle',
        'txtNotificationMessage',
        'txtNotificationLink',
        'txtNotificationFingerprint',
        'dtmNotificationRead',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
        'bitActive',
    ];

    protected $casts = [
        'dtmNotificationRead' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
        'bitActive' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }
}
