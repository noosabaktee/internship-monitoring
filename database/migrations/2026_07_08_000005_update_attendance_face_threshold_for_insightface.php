<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mAttendanceSetting') || ! Schema::hasColumn('mAttendanceSetting', 'floatAttendanceSettingFaceThreshold')) {
            return;
        }

        DB::table('mAttendanceSetting')
            ->where(function ($query) {
                $query->whereNull('floatAttendanceSettingFaceThreshold')
                    ->orWhere('floatAttendanceSettingFaceThreshold', '>=', 0.8);
            })
            ->update([
                'floatAttendanceSettingFaceThreshold' => 0.38,
                'txtUpdatedBy' => 'migration',
                'dtmUpdated' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
