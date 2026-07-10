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
            Schema::create('trAttendance', function (Blueprint $table) {
                $table->integer('intAttendance_ID')->primary();
                $table->integer('intUser_ID');
                $table->date('dtmAttendanceDate')->nullable();
                $table->timestamp('dtmAttendanceClockIn')->nullable();
                $table->string('txtAttendanceStatus')->nullable()->default('Hadir')->comment('Hadir or Tidak Masuk');
                $table->float('floatAttendanceLatitude')->nullable();
                $table->float('floatAttendanceLongitude')->nullable();
                $table->float('floatAttendanceLocationAccuracy')->nullable();
                $table->string('txtAttendanceAddress')->nullable();
                $table->string('txtAttendanceLocationUrl')->nullable();
                $table->float('floatAttendanceFaceDistance')->nullable();
                $table->string('txtAttendanceFaceAlgorithm')->nullable()->default('insightface-buffalo_l-v1');
                $table->string('txtAttendanceDevice')->nullable();
                $table->string('txtAttendanceNote')->nullable();
                $table->string('txtInsertedBy')->nullable();
                $table->timestamp('dtmInserted')->nullable();

                $table->unique(['intUser_ID', 'dtmAttendanceDate'], 'uq_trAttendance_intUser_ID_dtmAttendanceDate');
                $table->foreign('intUser_ID', 'fk_trAttendance_mUser_intUser_ID')
                    ->references('intUser_ID')
                    ->on('mUser');
            });

            return;
        }

        Schema::table('trAttendance', function (Blueprint $table) {
            if (! Schema::hasColumn('trAttendance', 'intUser_ID')) {
                $table->integer('intUser_ID')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'dtmAttendanceClockIn')) {
                $table->timestamp('dtmAttendanceClockIn')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceStatus')) {
                $table->string('txtAttendanceStatus')->nullable()->default('Hadir');
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceLatitude')) {
                $table->float('floatAttendanceLatitude')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceLongitude')) {
                $table->float('floatAttendanceLongitude')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceLocationAccuracy')) {
                $table->float('floatAttendanceLocationAccuracy')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceAddress')) {
                $table->string('txtAttendanceAddress')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceLocationUrl')) {
                $table->string('txtAttendanceLocationUrl')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'floatAttendanceFaceDistance')) {
                $table->float('floatAttendanceFaceDistance')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceFaceAlgorithm')) {
                $table->string('txtAttendanceFaceAlgorithm')->nullable()->default('insightface-buffalo_l-v1');
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceDevice')) {
                $table->string('txtAttendanceDevice')->nullable();
            }

            if (! Schema::hasColumn('trAttendance', 'txtAttendanceNote')) {
                $table->string('txtAttendanceNote')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trAttendance');
    }
};
