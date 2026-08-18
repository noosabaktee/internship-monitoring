<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MIntern extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mIntern';

    protected $primaryKey = 'intIntern_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intIntern_ID',
        'intUser_ID',
        'txtInternNo',
        'txtInternName',
        'txtInternGender',
        'txtUniversity',
        'txtDept',
        'txtInternType',
        'floatInternSalary',
        'txtBio',
        'dtmEndDate',
        'txtInternExtendEndDates',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'floatInternSalary' => 'float',
        'dtmEndDate' => 'datetime',
        'txtInternExtendEndDates' => 'array',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MUser::class, 'intUser_ID', 'intUser_ID');
    }

    public function projects()
    {
        return $this->hasMany(TrInternProject::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function evaluations()
    {
        return $this->hasMany(TrEvaluation::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function achievements()
    {
        return $this->hasMany(TrAchievement::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function workFromHomeRequests()
    {
        return $this->hasMany(TrWorkFromHomeRequest::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function salarySlips()
    {
        return $this->hasMany(TrSalarySlip::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function finalEvaluation()
    {
        return $this->hasOne(TrEvaluation::class, 'intIntern_ID', 'intIntern_ID')
            ->where('bitActive', true)
            ->latest('dtmEvaluationCompleted');
    }

    public function effectiveEndDate(): ?Carbon
    {
        $dates = collect([$this->dtmEndDate])
            ->merge($this->txtInternExtendEndDates ?? [])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        return $dates->isEmpty() ? null : $dates->sortDesc()->first();
    }

    public function hasCompletedInternship(?Carbon $onDate = null): bool
    {
        $endDate = $this->effectiveEndDate();

        return $endDate !== null && $endDate->lt(($onDate ?? now())->copy()->startOfDay());
    }
}
