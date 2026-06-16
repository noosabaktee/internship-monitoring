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
        Schema::create('mProject', function (Blueprint $table) {
            $table->integer('intProject_ID')->primary();
            $table->string('txtProjectName')->nullable();
            $table->string('txtProjectType')->nullable()->comment('Main, Satellite, Collaboration, Sharing');
            $table->string('txtDescription')->nullable();
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
        Schema::dropIfExists('mProject');
    }
};
