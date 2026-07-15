<?php

namespace App\Http\Controllers\Api;

use App\Models\MIntern;
use App\Models\TrAchievement;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternshipController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Ringkasan internship hanya tersedia untuk intern.');
        $intern = MIntern::with(['projects.project.skillSet', 'evaluations', 'achievements', 'workFromHomeRequests'])
            ->findOrFail($user->intern->intIntern_ID);
        $assignments = $intern->projects->filter(fn ($assignment) => $assignment->bitActive);
        $latestEvaluation = $intern->evaluations->where('bitActive', true)->sortByDesc('dtmEvaluationCompleted')->first();

        return $this->success([
            'intern' => $this->internPayload($intern),
            'status' => $intern->hasCompletedInternship() ? 'completed' : 'active',
            'progress' => round((float) $assignments->avg('floatProgress'), 2),
            'active_projects' => $assignments->count(),
            'completed_projects' => $assignments->filter(fn ($assignment) => (float) $assignment->floatProgress >= 100)->count(),
            'latest_evaluation' => $latestEvaluation ? $this->evaluation($latestEvaluation) : null,
            'achievement_count' => $intern->achievements->where('bitActive', true)->count(),
            'wfh_request_count' => $intern->workFromHomeRequests->where('bitActive', true)->count(),
        ], 'Ringkasan internship berhasil diambil.');
    }

    public function projects(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Project pribadi hanya tersedia untuk intern.');
        $query = TrInternProject::with(['project.skillSet', 'project.stages', 'mentor'])
            ->where('intIntern_ID', $user->intern->intIntern_ID)
            ->when($request->has('active'), fn ($query) => $query->where('bitActive', $request->boolean('active')))
            ->orderByDesc('bitActive')
            ->orderByDesc('dtmInserted');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($assignment) => $this->assignment($assignment),
            'Project internship berhasil diambil.',
        );
    }

    public function updateProject(Request $request, int $assignment): JsonResponse
    {
        $user = $this->user($request);
        $model = TrInternProject::with(['project', 'mentor'])->findOrFail($assignment);
        $isOwner = RoleAccess::isIntern($user) && (int) $model->intIntern_ID === (int) $user->intern?->intIntern_ID;
        $isMentor = RoleAccess::isMentor($user) && (int) $model->intMentor_ID === (int) $user->mentor?->intMentor_ID;
        $isAdmin = RoleAccess::isHeadmaster($user);
        abort_unless($isOwner || $isMentor || $isAdmin, 403, 'Kamu tidak dapat memperbarui assignment ini.');

        $validated = $request->validate([
            'progress' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $model->update([
            ...isset($validated['progress']) ? ['floatProgress' => $validated['progress']] : [],
            ...array_key_exists('status', $validated) ? ['txtStatus' => $validated['status']] : [],
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return $this->success($this->assignment($model->fresh(['project.skillSet', 'project.stages', 'mentor'])), 'Progress project berhasil diperbarui.');
    }

    public function evaluations(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Evaluasi pribadi hanya tersedia untuk intern.');

        return $this->success(TrEvaluation::with(['intern', 'evaluator'])
            ->where('intIntern_ID', $user->intern->intIntern_ID)
            ->where('bitActive', true)
            ->orderByDesc('dtmEvaluationCompleted')
            ->get()
            ->map(fn ($evaluation) => $this->evaluation($evaluation))
            ->values(), 'Evaluasi internship berhasil diambil.');
    }

    public function achievements(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Achievement pribadi hanya tersedia untuk intern.');

        return $this->success(TrAchievement::with('intern')
            ->where('intIntern_ID', $user->intern->intIntern_ID)
            ->where('bitActive', true)
            ->orderByDesc('dtmAwarded')
            ->get()
            ->map(fn ($achievement) => $this->achievementPayload($achievement))
            ->values(), 'Achievement internship berhasil diambil.');
    }

    protected function assignment(TrInternProject $assignment): array
    {
        return [
            'id' => (int) $assignment->intInternProject_ID,
            'project' => $assignment->project ? $this->projectPayload($assignment->project) : null,
            'mentor' => $assignment->mentor ? [
                'id' => (int) $assignment->mentor->intMentor_ID,
                'name' => $assignment->mentor->txtMentorName,
                'department' => $assignment->mentor->txtDepartment,
            ] : null,
            'progress' => $assignment->floatProgress,
            'status' => $assignment->txtStatus,
            'active' => (bool) $assignment->bitActive,
            'created_at' => $assignment->dtmInserted?->toISOString(),
            'updated_at' => $assignment->dtmUpdated?->toISOString(),
        ];
    }
}
