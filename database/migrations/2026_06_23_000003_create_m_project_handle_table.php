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
        Schema::create('mProjectHandle', function (Blueprint $table) {
            $table->integer('intProjectHandle_ID')->primary();
            $table->string('txtProjectHandleDuration')->nullable();
            $table->integer('intProjectHandleMain')->nullable();
            $table->integer('intProjectHandleCollaboration')->nullable();
            $table->integer('intProjectHandleSatellite')->nullable();
            $table->integer('intProjectHandleSharing')->nullable();
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
        Schema::dropIfExists('mProjectHandle');
    }
};
