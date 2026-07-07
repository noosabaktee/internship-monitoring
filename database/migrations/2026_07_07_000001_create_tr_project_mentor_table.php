<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trProjectMentor', function (Blueprint $table) {
            $table->integer('intProjectMentor_ID')->primary();
            $table->integer('intProject_ID');
            $table->integer('intMentor_ID');
            $table->boolean('bitActive')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();

            $table->foreign('intProject_ID', 'fk_trProjectMentor_mProject_intProject_ID')
                ->references('intProject_ID')
                ->on('mProject');
            $table->foreign('intMentor_ID', 'fk_trProjectMentor_mMentor_intMentor_ID')
                ->references('intMentor_ID')
                ->on('mMentor');
        });

        $now = now();
        $rows = DB::table('trInternProject')
            ->select('intProject_ID', 'intMentor_ID')
            ->where('bitActive', true)
            ->whereNotNull('intMentor_ID')
            ->distinct()
            ->orderBy('intProject_ID')
            ->orderBy('intMentor_ID')
            ->get();

        foreach ($rows as $index => $row) {
            DB::table('trProjectMentor')->insert([
                'intProjectMentor_ID' => $index + 1,
                'intProject_ID' => $row->intProject_ID,
                'intMentor_ID' => $row->intMentor_ID,
                'bitActive' => true,
                'txtInsertedBy' => 'migration',
                'dtmInserted' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trProjectMentor');
    }
};
