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
        Schema::create('trInternProject', function (Blueprint $table) {
            $table->integer('intInternProject_ID')->primary();
            $table->integer('intIntern_ID');
            $table->integer('intProject_ID');
            $table->integer('intMentor_ID');
            $table->float('floatProgress')->nullable()->comment('0 - 100 percentage');
            $table->string('txtStatus')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intIntern_ID', 'fk_trInternProject_mIntern_intIntern_ID')
                ->references('intIntern_ID')
                ->on('mIntern');
            $table->foreign('intProject_ID', 'fk_trInternProject_mProject_intProject_ID')
                ->references('intProject_ID')
                ->on('mProject');
            $table->foreign('intMentor_ID', 'fk_trInternProject_mMentor_intMentor_ID')
                ->references('intMentor_ID')
                ->on('mMentor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trInternProject');
    }
};
