<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrWorkFromHomeRequest extends Model
{
    use GeneratesIntegerIds;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $table = 'trWorkFromHomeRequest';

    protected $primaryKey = 'intWorkFromHomeRequest_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intWorkFromHomeRequest_ID',
        'intIntern_ID',
        'dtmWorkFromHomeRequestStartDate',
        'dtmWorkFromHomeRequestEndDate',
        'txtWorkFromHomeRequestReason',
        'txtWorkFromHomeRequestAttachment',
        'txtWorkFromHomeRequestStatus',
        'intApproverUser_ID',
        'dtmWorkFromHomeRequestReviewed',
        'txtWorkFromHomeRequestReviewNote',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
        'bitActive',
    ];

    protected $casts = [
        'dtmWorkFromHomeRequestStartDate' => 'date',
        'dtmWorkFromHomeRequestEndDate' => 'date',
        'dtmWorkFromHomeRequestReviewed' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
        'bitActive' => 'boolean',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function approver()
    {
        return $this->belongsTo(MUser::class, 'intApproverUser_ID', 'intUser_ID');
    }
}
