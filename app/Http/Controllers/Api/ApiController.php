<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MUser;
use App\Models\TrEvaluation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function user(Request $request): MUser
    {
        /** @var MUser|null $user */
        $user = $request->attributes->get('kmi_api_user') ?: $request->user();

        return $user instanceof MUser ? $user : abort(401, 'Autentikasi API diperlukan.');
    }

    protected function success(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        $body = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, callable $transform, string $message = 'OK'): JsonResponse
    {
        return $this->success(
            collect($paginator->items())->map($transform)->values(),
            $message,
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        );
    }

    protected function person(?MUser $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing(['intern', 'mentor', 'adminProfile']);

        return [
            'id' => (int) $user->intUser_ID,
            'email' => $user->txtEmail,
            'role' => $user->txtRole,
            'profile_photo' => $user->txtProfilePhoto,
            'name' => $user->intern?->txtInternName
                ?? $user->mentor?->txtMentorName
                ?? $user->adminProfile?->txtAdminProfileName
                ?? $user->txtEmail,
            'intern' => $user->intern ? $this->internPayload($user->intern) : null,
            'mentor' => $user->mentor ? [
                'id' => (int) $user->mentor->intMentor_ID,
                'name' => $user->mentor->txtMentorName,
                'gender' => $user->mentor->txtMentorGender,
                'department' => $user->mentor->txtDepartment,
                'role' => $user->mentor->txtRole,
            ] : null,
            'admin_profile' => $user->adminProfile ? [
                'id' => (int) $user->adminProfile->intAdminProfile_ID,
                'name' => $user->adminProfile->txtAdminProfileName,
                'gender' => $user->adminProfile->txtAdminProfileGender,
                'department' => $user->adminProfile->txtAdminProfileDepartment,
                'position' => $user->adminProfile->txtAdminProfilePosition,
                'phone' => $user->adminProfile->txtAdminProfilePhone,
                'bio' => $user->adminProfile->txtAdminProfileBio,
            ] : null,
        ];
    }

    protected function internPayload(mixed $intern): array
    {
        return [
            'id' => (int) $intern->intIntern_ID,
            'user_id' => $intern->intUser_ID ? (int) $intern->intUser_ID : null,
            'number' => $intern->txtInternNo,
            'name' => $intern->txtInternName,
            'gender' => $intern->txtInternGender,
            'university' => $intern->txtUniversity,
            'department' => $intern->txtDept,
            'type' => $intern->txtInternType,
            'salary' => $intern->floatInternSalary,
            'bio' => $intern->txtBio,
            'end_date' => $intern->dtmEndDate?->toDateString(),
            'effective_end_date' => $intern->effectiveEndDate()?->toDateString(),
            'completed' => $intern->hasCompletedInternship(),
            'active' => (bool) $intern->bitActive,
        ];
    }

    protected function projectPayload(mixed $project, bool $withRelations = true): array
    {
        $project->loadMissing($withRelations ? ['skillSet', 'stages', 'assignments.intern', 'assignments.mentor', 'projectMentors.mentor'] : []);

        return [
            'id' => (int) $project->intProject_ID,
            'name' => $project->txtProjectName,
            'type' => $project->txtProjectType,
            'skill_set' => $project->skillSet ? [
                'id' => (int) $project->skillSet->intSkillSet_ID,
                'name' => $project->skillSet->txtSkillSetName,
                'description' => $project->skillSet->txtSkillSetDescription,
            ] : null,
            'start_date' => $project->dtmProjectStartDate?->toDateString(),
            'end_date' => $project->dtmProjectEndDate?->toDateString(),
            'description' => $project->txtDescription,
            'active' => (bool) $project->bitActive,
            'stages' => $project->relationLoaded('stages') ? $project->stages->map(fn ($stage) => [
                'id' => (int) $stage->intProjectStage_ID,
                'number' => (int) $stage->intProjectStageNumber,
                'step' => $stage->txtProjectStageStep,
                'start_date' => $stage->dtmProjectStageStartDate?->toDateString(),
                'end_date' => $stage->dtmProjectStageEndDate?->toDateString(),
                'plan' => $stage->floatProjectStagePlan,
                'actual' => $stage->floatProjectStageActual,
            ])->values() : [],
            'assignments' => $project->relationLoaded('assignments') ? $project->assignments->map(fn ($assignment) => [
                'id' => (int) $assignment->intInternProject_ID,
                'intern' => $assignment->intern ? $this->internPayload($assignment->intern) : null,
                'mentor' => $assignment->mentor ? [
                    'id' => (int) $assignment->mentor->intMentor_ID,
                    'name' => $assignment->mentor->txtMentorName,
                ] : null,
                'progress' => $assignment->floatProgress,
                'status' => $assignment->txtStatus,
                'active' => (bool) $assignment->bitActive,
            ])->values() : [],
            'mentors' => $project->relationLoaded('projectMentors') ? $project->projectMentors->map(fn ($projectMentor) => [
                'id' => (int) $projectMentor->intProjectMentor_ID,
                'mentor' => $projectMentor->mentor ? [
                    'id' => (int) $projectMentor->mentor->intMentor_ID,
                    'name' => $projectMentor->mentor->txtMentorName,
                    'department' => $projectMentor->mentor->txtDepartment,
                ] : null,
                'active' => (bool) $projectMentor->bitActive,
            ])->values() : [],
        ];
    }

    protected function evaluation(mixed $evaluation): array
    {
        $evaluation->loadMissing(['intern', 'evaluator']);

        return [
            'id' => (int) $evaluation->intEvaluation_ID,
            'intern' => $evaluation->intern ? $this->internPayload($evaluation->intern) : null,
            'evaluator' => $this->person($evaluation->evaluator),
            'completed_at' => $evaluation->dtmEvaluationCompleted?->toDateString(),
            'hard_skill' => $evaluation->floatHardSkill,
            'collaboration' => $evaluation->floatCollaboration,
            'ownership' => $evaluation->floatOwnership,
            'sharing' => $evaluation->floatSharing,
            'exposure_score' => $evaluation->floatExposureScore,
            'grade' => TrEvaluation::gradeFor((float) $evaluation->floatExposureScore),
            'assessment_criteria' => $evaluation->assessmentCriteria(),
            'strength' => $evaluation->txtEvaluationStrength,
            'development' => $evaluation->txtEvaluationDevelopment,
            'recommendation' => $evaluation->txtEvaluationRecommendation,
            'certificate_published' => (bool) $evaluation->bitEvaluationCertificatePublished,
            'certificate_published_at' => $evaluation->dtmEvaluationCertificatePublished?->toISOString(),
            'certificate_url' => $evaluation->bitEvaluationCertificatePublished
                ? route('api.v1.me.evaluations.certificate', $evaluation->intEvaluation_ID)
                : null,
            'active' => (bool) $evaluation->bitActive,
        ];
    }

    protected function achievementPayload(mixed $achievement): array
    {
        $achievement->loadMissing('intern');

        return [
            'id' => (int) $achievement->intAchievement_ID,
            'intern' => $achievement->intern ? $this->internPayload($achievement->intern) : null,
            'title' => $achievement->txtAchievementTitle,
            'description' => $achievement->txtDescription,
            'icon' => $achievement->txtIcon,
            'awarded_at' => $achievement->dtmAwarded?->toISOString(),
            'active' => (bool) $achievement->bitActive,
        ];
    }
}
