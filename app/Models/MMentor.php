<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class MMentor extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'mMentor';
    protected $primaryKey = 'intMentor_ID';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'intMentor_ID',
        'intUser_ID',
        'txtMentorName',
        'txtDepartment',
        'txtRole',
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

    public function internProjects()
    {
        return $this->hasMany(TrInternProject::class, 'intMentor_ID', 'intMentor_ID');
    }
}
