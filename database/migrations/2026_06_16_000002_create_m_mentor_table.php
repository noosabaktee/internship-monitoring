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
        Schema::create('mMentor', function (Blueprint $table) {
            $table->integer('intMentor_ID')->primary();
            $table->integer('intUser_ID')->unique();
            $table->string('txtMentorName')->nullable();
            $table->string('txtDepartment')->nullable();
            $table->string('txtRole')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intUser_ID', 'fk_mMentor_mUser_intUser_ID')
                ->references('intUser_ID')
                ->on('mUser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mMentor');
    }
};
