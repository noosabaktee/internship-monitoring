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
        Schema::create('mUser', function (Blueprint $table) {
            $table->integer('intUser_ID')->primary();
            $table->string('txtEmail')->nullable();
            $table->string('txtPassword')->nullable();
            $table->string('txtRole')->nullable()->comment('Admin, Mentor, or Intern');
            $table->string('txtProfilePhoto')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mUser');
    }
};
