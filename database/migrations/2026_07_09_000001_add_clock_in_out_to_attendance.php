<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mAttendanceSetting')) {
            Schema::table('mAttendanceSetting', function (Blueprint $table) {
                if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingClockInStartTime')) {
                    $table->string('txtAttendanceSettingClockInStartTime')->nullable()->default('06:30');
                }

                if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingClockInEndTime')) {
                    $table->string('txtAttendanceSettingClockInEndTime')->nullable()->default('09:00');
                }

                if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingClockOutStartTime')) {
                    $table->string('txtAttendanceSettingClockOutStartTime')->nullable()->default('16:00');
                }

                if (! Schema::hasColumn('mAttendanceSetting', 'txtAttendanceSettingClockOutEndTime')) {
                    $table->string('txtAttendanceSettingClockOutEndTime')->nullable()->default('18:30');
                }
            });

            DB::table('mAttendanceSetting')
                ->whereNull('txtAttendanceSettingClockInStartTime')
                ->update(['txtAttendanceSettingClockInStartTime' => '06:30']);
            DB::table('mAttendanceSetting')
                ->whereNull('txtAttendanceSettingClockInEndTime')
                ->update(['txtAttendanceSettingClockInEndTime' => '09:00']);
            DB::table('mAttendanceSetting')
                ->whereNull('txtAttendanceSettingClockOutStartTime')
                ->update(['txtAttendanceSettingClockOutStartTime' => '16:00']);
            DB::table('mAttendanceSetting')
                ->whereNull('txtAttendanceSettingClockOutEndTime')
                ->update(['txtAttendanceSettingClockOutEndTime' => '18:30']);
        }

        if (! Schema::hasTable('trAttendance')) {
            return;
        }

        Schema::table('trAttendance', function (Blueprint $table) {
            if (! Schema::hasColumn('trAttendance', 'dtmAttendanceClockOut')) {
                $table->timestamp('dtmAttendanceClockOut')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceClockOutLatitude')) {
                $table->float('floatAttendanceClockOutLatitude')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceClockOutLongitude')) {
                $table->float('floatAttendanceClockOutLongitude')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceClockOutLocationAccuracy')) {
                $table->float('floatAttendanceClockOutLocationAccuracy')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutAddress')) {
                $table->string('txtAttendanceClockOutAddress')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutLocationUrl')) {
                $table->string('txtAttendanceClockOutLocationUrl')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceClockOutFaceDistance')) {
                $table->float('floatAttendanceClockOutFaceDistance')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutFaceAlgorithm')) {
                $table->string('txtAttendanceClockOutFaceAlgorithm')->nullable()->default('insightface-buffalo_l-v1');
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutDevice')) {
                $table->string('txtAttendanceClockOutDevice')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceClockOutNote')) {
                $table->string('txtAttendanceClockOutNote')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('trAttendance')) {
            Schema::table('trAttendance', function (Blueprint $table) {
                $columns = [
                    'dtmAttendanceClockOut',
                    'floatAttendanceClockOutLatitude',
                    'floatAttendanceClockOutLongitude',
                    'floatAttendanceClockOutLocationAccuracy',
                    'txtAttendanceClockOutAddress',
                    'txtAttendanceClockOutLocationUrl',
                    'floatAttendanceClockOutFaceDistance',
                    'txtAttendanceClockOutFaceAlgorithm',
                    'txtAttendanceClockOutDevice',
                    'txtAttendanceClockOutNote',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('trAttendance', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('mAttendanceSetting')) {
            Schema::table('mAttendanceSetting', function (Blueprint $table) {
                $columns = [
                    'txtAttendanceSettingClockInStartTime',
                    'txtAttendanceSettingClockInEndTime',
                    'txtAttendanceSettingClockOutStartTime',
                    'txtAttendanceSettingClockOutEndTime',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('mAttendanceSetting', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
