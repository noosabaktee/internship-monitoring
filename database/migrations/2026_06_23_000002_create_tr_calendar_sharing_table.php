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
        Schema::create('trCalendarSharing', function (Blueprint $table) {
            $table->integer('intCalendarSharing_ID')->primary();
            $table->integer('intCalendarSharingCreatorUser_ID')->nullable();
            $table->string('txtCalendarSharingTheme')->nullable();
            $table->string('txtCalendarSharingObjective')->nullable();
            $table->string('txtCalendarSharingDescription')->nullable();
            $table->string('txtCalendarSharingTargetAudience')->nullable();
            $table->timestamp('dtmCalendarSharingDate')->nullable();
            $table->string('txtCalendarSharingStatus')->nullable()->default('Open')->comment('Open, Complete, Cancel, Reschedule');
            $table->string('txtCalendarSharingIcon')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intCalendarSharingCreatorUser_ID', 'fk_trCalendarSharing_mUser_intCalendarSharingCreatorUser_ID')
                ->references('intUser_ID')
                ->on('mUser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trCalendarSharing');
    }
};
