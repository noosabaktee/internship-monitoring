<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trAttendance', function (Blueprint $table) {
            $table->integer('intAttendanceLocation_ID')->nullable();
            $table->integer('intWorkFromHomeRequest_ID')->nullable();
            $table->string('txtAttendanceWorkMode', 20)->default('Office');
            $table->float('floatAttendanceDistanceMeter')->nullable();
            $table->float('floatAttendanceAllowedDistanceMeter')->nullable();
            $table->boolean('bitAttendanceWithinTolerance')->nullable();
            $table->float('floatAttendanceClockOutDistanceMeter')->nullable();
            $table->boolean('bitAttendanceClockOutWithinTolerance')->nullable();

            $table->foreign('intAttendanceLocation_ID', 'fk_attendance_location')
                ->references('intAttendanceLocation_ID')
                ->on('mAttendanceLocation');
            $table->foreign('intWorkFromHomeRequest_ID', 'fk_attendance_wfh_request')
                ->references('intWorkFromHomeRequest_ID')
                ->on('trWorkFromHomeRequest');
            $table->index('txtAttendanceWorkMode', 'ix_attendance_work_mode');
        });
    }

    public function down(): void
    {
        Schema::table('trAttendance', function (Blueprint $table) {
            $table->dropForeign('fk_attendance_location');
            $table->dropForeign('fk_attendance_wfh_request');
            $table->dropIndex('ix_attendance_work_mode');
            $table->dropColumn([
                'intAttendanceLocation_ID',
                'intWorkFromHomeRequest_ID',
                'txtAttendanceWorkMode',
                'floatAttendanceDistanceMeter',
                'floatAttendanceAllowedDistanceMeter',
                'bitAttendanceWithinTolerance',
                'floatAttendanceClockOutDistanceMeter',
                'bitAttendanceClockOutWithinTolerance',
            ]);
        });
    }
};
