<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trAttendance')) {
            return;
        }

        Schema::table('trAttendance', function (Blueprint $table) {
            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutStatus')) {
                $table->string('txtAttendanceClockOutStatus')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('trAttendance') || ! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutStatus')) {
            return;
        }

        Schema::table('trAttendance', function (Blueprint $table) {
            $table->dropColumn('txtAttendanceClockOutStatus');
        });
    }
};
