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
        Schema::create('trEvaluation', function (Blueprint $table) {
            $table->integer('intEvaluation_ID')->primary();
            $table->integer('intIntern_ID');
            $table->timestamp('dtmPeriod')->comment('Evaluation month/period');
            $table->float('floatHardSkill')->nullable();
            $table->float('floatCollaboration')->nullable();
            $table->float('floatOwnership')->nullable();
            $table->float('floatSharing')->nullable();
            $table->float('floatExposureScore')->nullable()->comment('Calculated average score');
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intIntern_ID', 'fk_trEvaluation_mIntern_intIntern_ID')
                ->references('intIntern_ID')
                ->on('mIntern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trEvaluation');
    }
};
