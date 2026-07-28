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
            $table->float('floatDisciplineAttendance')->nullable();
            $table->float('floatResponsibilityInitiative')->nullable();
            $table->float('floatTechnicalSkills')->nullable();
            $table->float('floatTeamwork')->nullable();
            $table->float('floatCommunicationSkills')->nullable();
            $table->float('floatCreativityProblemSolving')->nullable();
            $table->float('floatProfessionalismWorkEthics')->nullable();
        });

        DB::table('trEvaluation')->orderBy('intEvaluation_ID')->get()->each(function ($evaluation): void {
            DB::table('trEvaluation')
                ->where('intEvaluation_ID', $evaluation->intEvaluation_ID)
                ->update([
                    'floatDisciplineAttendance' => $evaluation->floatOwnership,
                    'floatResponsibilityInitiative' => $evaluation->floatOwnership,
                    'floatTechnicalSkills' => $evaluation->floatHardSkill,
                    'floatTeamwork' => $evaluation->floatCollaboration,
                    'floatCommunicationSkills' => $evaluation->floatSharing,
                    'floatCreativityProblemSolving' => $evaluation->floatHardSkill,
                    'floatProfessionalismWorkEthics' => $evaluation->floatOwnership,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->dropColumn([
                'floatDisciplineAttendance',
                'floatResponsibilityInitiative',
                'floatTechnicalSkills',
                'floatTeamwork',
                'floatCommunicationSkills',
                'floatCreativityProblemSolving',
                'floatProfessionalismWorkEthics',
            ]);
        });
    }
};
