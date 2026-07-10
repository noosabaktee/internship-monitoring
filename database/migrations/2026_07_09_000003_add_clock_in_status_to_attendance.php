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
            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockInStatus')) {
                $table->string('txtAttendanceClockInStatus')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('trAttendance') || ! Schema::hasColumn('trAttendance', 'txtAttendanceClockInStatus')) {
            return;
        }

        Schema::table('trAttendance', function (Blueprint $table) {
            $table->dropColumn('txtAttendanceClockInStatus');
        });
    }
};
