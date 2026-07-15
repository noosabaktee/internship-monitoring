<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mAttendanceLocation', function (Blueprint $table) {
            $table->integer('intAttendanceLocation_ID')->primary();
            $table->string('txtAttendanceLocationCode', 30)->unique();
            $table->string('txtAttendanceLocationName', 150);
            $table->text('txtAttendanceLocationAddress');
            $table->decimal('floatAttendanceLocationLatitude', 10, 7);
            $table->decimal('floatAttendanceLocationLongitude', 10, 7);
            $table->integer('intAttendanceLocationRadiusMeter')->default(100);
            $table->integer('intAttendanceLocationToleranceMeter')->default(50);
            $table->integer('intAttendanceLocationMaximumAccuracyMeter')->nullable()->default(200);
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();
            $table->boolean('bitActive')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mAttendanceLocation');
    }
};
