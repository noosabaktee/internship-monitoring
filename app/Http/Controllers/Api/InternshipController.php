<?php

namespace App\Http\Controllers\Api;

use App\Models\MIntern;
use App\Models\TrAchievement;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Support\RoleAccess;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            ->map(function ($evaluation): array {
                $payload = $this->evaluation($evaluation);
                if ($evaluation->bitEvaluationCertificatePublished) {
                    return $payload;
                }

                return collect($payload)->except([
                    'evaluator',
                    'hard_skill',
                    'collaboration',
                    'ownership',
                    'sharing',
                    'exposure_score',
                    'grade',
                    'assessment_criteria',
                    'strength',
                    'development',
                    'recommendation',
                ])->all();
            })
            ->values(), 'Evaluasi internship berhasil diambil.');
    }

    public function certificate(Request $request, int $evaluation): Response
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Sertifikat pribadi hanya tersedia untuk intern.');

        $model = TrEvaluation::with(['intern.user', 'evaluator.mentor', 'evaluator.adminProfile'])
            ->where('bitActive', true)
            ->findOrFail($evaluation);

        abort_unless((int) $model->intIntern_ID === (int) $user->intern->intIntern_ID, 403);
        abort_unless($model->bitEvaluationCertificatePublished, 404, 'Sertifikat belum diterbitkan.');

        $intern = $model->intern;
        $certificateNumber = sprintf(
            'KDC/INT/%s/%04d',
            $model->dtmEvaluationCertificatePublished?->format('Y')
                ?? $model->dtmEvaluationCompleted?->format('Y')
                ?? now()->format('Y'),
            $model->intEvaluation_ID,
        );
        $evaluator = $this->person($model->evaluator);
        $image = static function (string $filename): ?string {
            $path = public_path('images/certificate/'.$filename);

            return is_file($path) ? 'data:image/png;base64,'.base64_encode(file_get_contents($path)) : null;
        };
        $html = view('dashboard.certificate-pdf', [
            'evaluation' => $model,
            'intern' => $intern,
            'startDate' => $intern->dtmInserted,
            'endDate' => $intern->effectiveEndDate() ?? $model->dtmEvaluationCompleted,
            'certificateNumber' => $certificateNumber,
            'evaluatorName' => $evaluator['name'] ?? 'Kalbe Digital Core',
            'pageOneBackground' => $image('page-1-background.png'),
            'pageTwoBackground' => $image('page-2-background.png'),
            'watermarkData' => $image('kalbe-nutritionals-watermark.png'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $filename = 'sertifikat-internship-'.str($intern->txtInternName)->slug().'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
