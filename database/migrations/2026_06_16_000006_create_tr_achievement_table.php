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
        Schema::create('trAchievement', function (Blueprint $table) {
            $table->integer('intAchievement_ID')->primary();
            $table->integer('intIntern_ID');
            $table->string('txtAchievementTitle')->nullable()->comment('e.g., Top Performer');
            $table->string('txtDescription')->nullable();
            $table->string('txtIcon')->nullable()->comment('FontAwesome icon class');
            $table->timestamp('dtmAwarded')->nullable();
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intIntern_ID', 'fk_trAchievement_mIntern_intIntern_ID')
                ->references('intIntern_ID')
                ->on('mIntern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trAchievement');
    }
};
