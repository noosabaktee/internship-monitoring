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
        if (! Schema::hasTable('mFaceEnrollment')) {
            Schema::create('mFaceEnrollment', function (Blueprint $table) {
                $table->integer('intFaceEnrollment_ID')->primary();
                $table->integer('intUser_ID')->unique();
                $table->text('txtFaceEnrollmentDescriptor')->nullable();
                $table->string('txtFaceEnrollmentAlgorithm')->nullable()->default('insightface-buffalo_l-v1');
                $table->integer('intFaceEnrollmentSampleCount')->nullable();
                $table->float('floatFaceEnrollmentQuality')->nullable();
                $table->timestamp('dtmFaceEnrollmentRegistered')->nullable();
                $table->boolean('bitActive')->nullable();
                $table->string('txtInsertedBy')->nullable();
                $table->timestamp('dtmInserted')->nullable();
                $table->string('txtUpdatedBy')->nullable();
                $table->timestamp('dtmUpdated')->nullable();

                $table->foreign('intUser_ID', 'fk_mFaceEnrollment_mUser_intUser_ID')
                    ->references('intUser_ID')
                    ->on('mUser');
            });

            return;
        }

        Schema::table('mFaceEnrollment', function (Blueprint $table) {
            if (! Schema::hasColumn('mFaceEnrollment', 'txtFaceEnrollmentDescriptor')) {
                $table->text('txtFaceEnrollmentDescriptor')->nullable();
            }

            if (! Schema::hasColumn('mFaceEnrollment', 'txtFaceEnrollmentAlgorithm')) {
                $table->string('txtFaceEnrollmentAlgorithm')->nullable()->default('insightface-buffalo_l-v1');
            }

            if (! Schema::hasColumn('mFaceEnrollment', 'intFaceEnrollmentSampleCount')) {
                $table->integer('intFaceEnrollmentSampleCount')->nullable();
            }

            if (! Schema::hasColumn('mFaceEnrollment', 'floatFaceEnrollmentQuality')) {
                $table->float('floatFaceEnrollmentQuality')->nullable();
            }

            if (! Schema::hasColumn('mFaceEnrollment', 'dtmFaceEnrollmentRegistered')) {
                $table->timestamp('dtmFaceEnrollmentRegistered')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mFaceEnrollment');
    }
};
