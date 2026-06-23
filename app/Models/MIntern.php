<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

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
}
