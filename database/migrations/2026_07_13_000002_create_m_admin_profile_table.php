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
        Schema::create('mAdminProfile', function (Blueprint $table) {
            $table->integer('intAdminProfile_ID')->primary();
            $table->integer('intUser_ID')->unique();
            $table->string('txtAdminProfileName')->nullable();
            $table->string('txtAdminProfileGender')->nullable()->comment('Male or Female');
            $table->string('txtAdminProfileDepartment')->nullable();
            $table->string('txtAdminProfilePosition')->nullable();
            $table->string('txtAdminProfilePhone')->nullable();
            $table->string('txtAdminProfileBio')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intUser_ID', 'fk_mAdminProfile_mUser_intUser_ID')
                ->references('intUser_ID')
                ->on('mUser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mAdminProfile');
    }
};
