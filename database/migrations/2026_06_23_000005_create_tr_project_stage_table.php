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
        Schema::create('trProjectStage', function (Blueprint $table) {
            $table->integer('intProjectStage_ID')->primary();
            $table->integer('intProject_ID');
            $table->integer('intProjectStageNumber')->nullable();
            $table->string('txtProjectStageStep')->nullable();
            $table->timestamp('dtmProjectStageStartDate')->nullable();
            $table->timestamp('dtmProjectStageEndDate')->nullable();
            $table->float('floatProjectStagePlan')->nullable()->comment('Planned percentage, total active stages per project must equal 100');
            $table->float('floatProjectStageActual')->nullable()->comment('Actual percentage completed for S-curve comparison');
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intProject_ID', 'fk_trProjectStage_mProject_intProject_ID')
                ->references('intProject_ID')
                ->on('mProject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trProjectStage');
    }
};
