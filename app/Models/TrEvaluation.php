<?php

namespace App\Models;

use App\Models\Concerns\GeneratesIntegerIds;
use Illuminate\Database\Eloquent\Model;

class TrEvaluation extends Model
{
    use GeneratesIntegerIds;

    protected $table = 'trEvaluation';

    protected $primaryKey = 'intEvaluation_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'intEvaluation_ID',
        'intIntern_ID',
        'intEvaluatorUser_ID',
        'dtmEvaluationCompleted',
        'floatHardSkill',
        'floatCollaboration',
        'floatOwnership',
        'floatSharing',
        'floatExposureScore',
        'floatDisciplineAttendance',
        'floatResponsibilityInitiative',
        'floatTechnicalSkills',
        'floatTeamwork',
        'floatCommunicationSkills',
        'floatCreativityProblemSolving',
        'floatProfessionalismWorkEthics',
        'txtEvaluationStrength',
        'txtEvaluationDevelopment',
        'txtEvaluationRecommendation',
        'bitEvaluationCertificatePublished',
        'dtmEvaluationCertificatePublished',
        'intEvaluationCertificatePublishedByUser_ID',
        'bitActive',
        'txtInsertedBy',
        'dtmInserted',
        'txtUpdatedBy',
        'dtmUpdated',
    ];

    protected $casts = [
        'bitActive' => 'boolean',
        'dtmEvaluationCompleted' => 'date',
        'floatHardSkill' => 'float',
        'floatCollaboration' => 'float',
        'floatOwnership' => 'float',
        'floatSharing' => 'float',
        'floatExposureScore' => 'float',
        'floatDisciplineAttendance' => 'float',
        'floatResponsibilityInitiative' => 'float',
        'floatTechnicalSkills' => 'float',
        'floatTeamwork' => 'float',
        'floatCommunicationSkills' => 'float',
        'floatCreativityProblemSolving' => 'float',
        'floatProfessionalismWorkEthics' => 'float',
        'bitEvaluationCertificatePublished' => 'boolean',
        'dtmEvaluationCertificatePublished' => 'datetime',
        'dtmInserted' => 'datetime',
        'dtmUpdated' => 'datetime',
    ];

    public function intern()
    {
        return $this->belongsTo(MIntern::class, 'intIntern_ID', 'intIntern_ID');
    }

    public function evaluator()
    {
        return $this->belongsTo(MUser::class, 'intEvaluatorUser_ID', 'intUser_ID');
    }

    public function certificatePublisher()
    {
        return $this->belongsTo(MUser::class, 'intEvaluationCertificatePublishedByUser_ID', 'intUser_ID');
    }

    /**
     * @return array<int, array{key: string, label: string, score: float, grade: string}>
     */
    public function assessmentCriteria(): array
    {
        $criteria = [
            'discipline_attendance' => ['Discipline & Attendance', $this->floatDisciplineAttendance ?? $this->floatOwnership],
            'responsibility_initiative' => ['Responsibility & Initiative', $this->floatResponsibilityInitiative ?? $this->floatOwnership],
            'technical_skills' => ['Technical Skills', $this->floatTechnicalSkills ?? $this->floatHardSkill],
            'teamwork' => ['Teamwork', $this->floatTeamwork ?? $this->floatCollaboration],
            'communication_skills' => ['Communication Skills', $this->floatCommunicationSkills ?? $this->floatSharing],
            'creativity_problem_solving' => ['Creativity & Problem-Solving', $this->floatCreativityProblemSolving ?? $this->floatHardSkill],
            'professionalism_work_ethics' => ['Professionalism & Work Ethics', $this->floatProfessionalismWorkEthics ?? $this->floatOwnership],
        ];

        return collect($criteria)->map(function (array $criterion, string $key): array {
            $score = (float) $criterion[1];

            return [
                'key' => $key,
                'label' => $criterion[0],
                'score' => $score,
                'grade' => self::gradeFor($score),
            ];
        })->values()->all();
    }

    public static function gradeFor(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }
}
