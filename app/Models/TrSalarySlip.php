<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrSalarySlip extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trSalarySlip';

    protected $primaryKey = 'intSalarySlip_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intSalarySlip_ID',
        'intIntern_ID',
        'intSalarySlipCreatedByUser_ID',
        'dtmSalarySlipPeriodStart',
        'dtmSalarySlipPeriodEnd',
        'txtSalarySlipFileName',
        'txtSalarySlipFilePath',
        'intSalarySlipWorkdays',
        'intSalarySlipPresentDays',
        'intSalarySlipLateDays',
        'intSalarySlipAbsentDays',
        'intSalarySlipPendingDays',
        'intSalarySlipPaidDays',
        'floatSalarySlipDailySalary',
        'floatSalarySlipGrossSalary',
        'floatSalarySlipDeduction',
        'floatSalarySlipNetSalary',
        'txtInsertedBy',
        'dtmInserted',
    ];

    protected $casts = [
        'dtmSalarySlipPeriodStart' => 'date',
        'dtmSalarySlipPeriodEnd' => 'date',
        'intSalarySlipWorkdays' => 'integer',
        'intSalarySlipPresentDays' => 'integer',
        'intSalarySlipLateDays' => 'integer',
        'intSalarySlipAbsentDays' => 'integer',
        'intSalarySlipPendingDays' => 'integer',
        'intSalarySlipPaidDays' => 'integer',
        'floatSalarySlipDailySalary' => 'float',
        'floatSalarySlipGrossSalary' => 'float',
        'floatSalarySlipDeduction' => 'float',
        'floatSalarySlipNetSalary' => 'float',
        'dtmInserted' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function creator()
    {
        return $this->belongsTo(MUser::class, 'intSalarySlipCreatedByUser_ID', 'intUser_ID');
    }
}
