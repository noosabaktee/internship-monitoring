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
        if (! Schema::hasTable('mAttendanceSetting')) {
            Schema::create('mAttendanceSetting', function (Blueprint $table) {
                $table->integer('intAttendanceSetting_ID')->primary();
                $table->string('txtAttendanceSettingStartTime')->nullable()->default('06:00');
                $table->string('txtAttendanceSettingEndTime')->nullable()->default('23:59');
                $table->float('floatAttendanceSettingFaceThreshold')->nullable()->default(0.38);
                $table->boolean('bitAttendanceSettingLocationRequired')->nullable()->default(true);
                $table->boolean('bitActive')->nullable();
                $table->string('txtInsertedBy')->nullable();
                $table->timestamp('dtmInserted')->nullable();
                $table->string('txtUpdatedBy')->nullable();
                $table->timestamp('dtmUpdated')->nullable();
            });

            return;
        }

        Schema::table('mAttendanceSetting', function (Blueprint $table) {
            if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingStartTime')) {
                $table->string('txtAttendanceSettingStartTime')->nullable()->default('06:00');
            }

            if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingEndTime')) {
                $table->string('txtAttendanceSettingEndTime')->nullable()->default('23:59');
            }

            if (! Schema::hasColumn('mAttendanceSetting', 'floatAttendanceSettingFaceThreshold')) {
                $table->float('floatAttendanceSettingFaceThreshold')->nullable()->default(0.38);
            }

            if (! Schema::hasColumn('mAttendanceSetting', 'bitAttendanceSettingLocationRequired')) {
                $table->boolean('bitAttendanceSettingLocationRequired')->nullable()->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mAttendanceSetting');
    }
};
