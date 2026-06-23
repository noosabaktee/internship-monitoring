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
        Schema::create('mProjectWeight', function (Blueprint $table) {
            $table->integer('intProjectWeight_ID')->primary();
            $table->integer('intProjectWeightMain')->nullable();
            $table->integer('intProjectWeightCollaboration')->nullable();
            $table->integer('intProjectWeightSatellite')->nullable();
            $table->integer('intProjectWeightSharing')->nullable();
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
        Schema::dropIfExists('mProjectWeight');
    }
};
