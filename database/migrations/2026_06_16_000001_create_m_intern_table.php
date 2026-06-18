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
        Schema::create('mIntern', function (Blueprint $table) {
            $table->integer('intIntern_ID')->primary();
            $table->integer('intUser_ID')->unique();
            $table->string('txtInternNo')->nullable();
            $table->string('txtInternName')->nullable();
            $table->string('txtInternGender')->nullable()->comment('Male or Female');
            $table->string('txtUniversity')->nullable();
            $table->string('txtMajor')->nullable();
            $table->string('txtBio')->nullable();
            $table->timestamp('dtmEndDate')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intUser_ID', 'fk_mIntern_mUser_intUser_ID')
                ->references('intUser_ID')
                ->on('mUser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mIntern');
    }
};
