<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrAchievement extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trAchievement';
    protected $primaryKey = 'intAchievement_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intAchievement_ID',
        'intIntern_ID',
        'txtAchievementTitle',
        'txtDescription',
        'txtIcon',
        'dtmAwarded',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmAwarded' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }
}
