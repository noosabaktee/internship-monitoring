<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->integer('intEvaluatorUser_ID')->nullable();
            $table->date('dtmEvaluationCompleted')->nullable();
            $table->text('txtEvaluationStrength')->nullable();
            $table->text('txtEvaluationDevelopment')->nullable();
            $table->text('txtEvaluationRecommendation')->nullable();

            $table->foreign('intEvaluatorUser_ID', 'fk_evaluation_evaluator')
                ->references('intUser_ID')
                ->on('mUser');
            $table->index('intIntern_ID', 'ix_evaluation_intern');
        });

        DB::table('trEvaluation')
            ->whereNull('dtmEvaluationCompleted')
            ->orderBy('intEvaluation_ID')
            ->get(['intEvaluation_ID', 'dtmPeriod', 'dtmInserted'])
            ->each(function ($evaluation): void {
                $completed = $evaluation->dtmPeriod ?? $evaluation->dtmInserted ?? now();

                DB::table('trEvaluation')
                    ->where('intEvaluation_ID', $evaluation->intEvaluation_ID)
                    ->update(['dtmEvaluationCompleted' => substr((string) $completed, 0, 10)]);
            });

        DB::table('trEvaluation')
            ->select('intIntern_ID')
            ->groupBy('intIntern_ID')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('intIntern_ID')
            ->each(function ($internId): void {
                $keepId = DB::table('trEvaluation')
                    ->where('intIntern_ID', $internId)
                    ->orderByDesc('dtmEvaluationCompleted')
                    ->orderByDesc('intEvaluation_ID')
                    ->value('intEvaluation_ID');

                DB::table('trEvaluation')
                    ->where('intIntern_ID', $internId)
                    ->where('intEvaluation_ID', '<>', $keepId)
                    ->update([
                        'bitActive' => false,
                        'txtUpdatedBy' => 'migration',
                        'dtmUpdated' => now(),
                    ]);
            });

        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->dropColumn('dtmPeriod');
        });
    }

    public function down(): void
    {
        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->timestamp('dtmPeriod')->nullable();
        });

        DB::table('trEvaluation')
            ->whereNull('dtmPeriod')
            ->orderBy('intEvaluation_ID')
            ->get(['intEvaluation_ID', 'dtmEvaluationCompleted'])
            ->each(function ($evaluation): void {
                DB::table('trEvaluation')
                    ->where('intEvaluation_ID', $evaluation->intEvaluation_ID)
                    ->update(['dtmPeriod' => $evaluation->dtmEvaluationCompleted ?? now()]);
            });

        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->dropForeign('fk_evaluation_evaluator');
            $table->dropIndex('ix_evaluation_intern');
            $table->dropColumn([
                'intEvaluatorUser_ID',
                'dtmEvaluationCompleted',
                'txtEvaluationStrength',
                'txtEvaluationDevelopment',
                'txtEvaluationRecommendation',
            ]);
        });
    }
};
