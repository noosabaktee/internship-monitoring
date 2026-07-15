<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MUser;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.analytics', $this->pageData($request));
    }

    public function create(Request $request): View
    {
        return view('dashboard.analytics', [
            ...$this->pageData($request),
            'mode' => 'create',
            'selectedInternId' => $request->integer('intern') ?: null,
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::can($user, 'crud-analytics'), 403);
        $validated = $this->validateEvaluation($request);

        $evaluation = DB::transaction(function () use ($validated, $user): TrEvaluation {
            $intern = MIntern::lockForUpdate()->findOrFail($validated['intIntern_ID']);

            if (TrEvaluation::where('intIntern_ID', $intern->intIntern_ID)->where('bitActive', true)->exists()) {
                throw ValidationException::withMessages([
                    'intIntern_ID' => 'Rapor akhir intern ini sudah pernah diisi dan tidak dapat dibuat dua kali.',
                ]);
            }

            return TrEvaluation::create([
                ...$validated,
                'intEvaluatorUser_ID' => $user->intUser_ID,
                'dtmEvaluationCompleted' => now('Asia/Jakarta')->toDateString(),
                'floatExposureScore' => $this->averageScore($validated),
                'bitActive' => true,
                'txtInsertedBy' => $user->txtEmail,
                'dtmInserted' => now(),
            ]);
        });

        $evaluation->load('intern.user');

        if ($evaluation->intern?->user) {
            $notifications->send(
                $evaluation->intern->user,
                'internship',
                'Rapor akhir internship selesai',
                'Rapor final sudah diisi. Sertifikat akan tersedia setelah diterbitkan oleh mentor atau Headmaster.',
                route('analytics.index'),
                'evaluation-ready:'.$evaluation->intEvaluation_ID,
            );
        }

        return redirect()->route('analytics.index')->with('success', 'Rapor final berhasil disimpan. Periksa kembali lalu terbitkan sertifikat agar dapat diunduh intern.');
    }

    public function show(Request $request, string $analytic): View
    {
        $evaluation = TrEvaluation::with(['intern.user', 'evaluator.mentor', 'evaluator.adminProfile'])->findOrFail($analytic);
        $this->authorizeEvaluation($request, $evaluation);

        return view('dashboard.analytics', [
            ...$this->pageData($request),
            'viewingEvaluation' => $evaluation,
            'mode' => 'show',
        ]);
    }

    public function edit(Request $request, string $analytic): View
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::can($user, 'crud-analytics'), 403);
        $evaluation = TrEvaluation::with('intern')->where('bitActive', true)->findOrFail($analytic);

        return view('dashboard.analytics', [
            ...$this->pageData($request),
            'editingEvaluation' => $evaluation,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $analytic): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::can($user, 'crud-analytics'), 403);
        $evaluation = TrEvaluation::where('bitActive', true)->findOrFail($analytic);
        $validated = $this->validateEvaluation($request);

        if ((int) $validated['intIntern_ID'] !== (int) $evaluation->intIntern_ID) {
            throw ValidationException::withMessages(['intIntern_ID' => 'Intern pada rapor final tidak dapat diganti.']);
        }

        $evaluation->update([
            ...$validated,
            'floatExposureScore' => $this->averageScore($validated),
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('analytics.index')->with('success', 'Rapor final berhasil diperbarui. Sertifikat menggunakan nilai terbaru.');
    }

    public function destroy(string $analytic): RedirectResponse
    {
        TrEvaluation::whereKey($analytic)->firstOrFail();

        return back()->withErrors(['evaluation' => 'Rapor final tidak dapat dihapus karena menjadi dasar penerbitan sertifikat.']);
    }

    public function certificate(Request $request, string $analytic): Response
    {
        $evaluation = TrEvaluation::with(['intern.user', 'evaluator.mentor', 'evaluator.adminProfile'])
            ->where('bitActive', true)
            ->findOrFail($analytic);
        $this->authorizeEvaluation($request, $evaluation);
        $user = $this->currentUser($request);

        abort_if(RoleAccess::isIntern($user) && ! $evaluation->bitEvaluationCertificatePublished, 404);

        $intern = $evaluation->intern;
        $startDate = $intern->dtmInserted;
        $endDate = $intern->effectiveEndDate() ?? $evaluation->dtmEvaluationCompleted;
        $certificateNumber = sprintf(
            'KDC/INT/%s/%04d',
            $evaluation->dtmEvaluationCertificatePublished?->format('Y')
                ?? $evaluation->dtmEvaluationCompleted?->format('Y')
                ?? now()->format('Y'),
            $evaluation->intEvaluation_ID,
        );
        $logoPath = public_path('images/KDC.png');
        $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $html = view('dashboard.certificate-pdf', [
            'evaluation' => $evaluation,
            'intern' => $intern,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'certificateNumber' => $certificateNumber,
            'logoData' => $logoData,
            'evaluatorName' => $this->evaluatorName($evaluation->evaluator),
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
        ]);
    }

    public function publishCertificate(Request $request, string $analytic, NotificationService $notifications): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::can($user, 'crud-analytics'), 403);

        $evaluation = TrEvaluation::with('intern.user')
            ->where('bitActive', true)
            ->findOrFail($analytic);

        if (! $evaluation->bitEvaluationCertificatePublished) {
            $evaluation->update([
                'bitEvaluationCertificatePublished' => true,
                'dtmEvaluationCertificatePublished' => now('Asia/Jakarta'),
                'intEvaluationCertificatePublishedByUser_ID' => $user->intUser_ID,
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);
        }

        if ($evaluation->intern?->user) {
            $notifications->send(
                $evaluation->intern->user,
                'certificate',
                'Sertifikat internship sudah diterbitkan',
                'Sertifikat internship resmi kamu sudah tersedia dan dapat diunduh dari halaman Rapor Intern.',
                route('analytics.index'),
                'certificate-published:'.$evaluation->intEvaluation_ID,
            );
        }

        return redirect()->route('analytics.index')->with('success', 'Sertifikat berhasil diterbitkan dan sekarang dapat diunduh oleh intern.');
    }

    private function pageData(Request $request): array
    {
        $user = $this->currentUser($request);
        $query = TrEvaluation::with(['intern.user', 'evaluator.mentor', 'evaluator.adminProfile'])
            ->where('bitActive', true);

        if (RoleAccess::isIntern($user)) {
            $query->where('intIntern_ID', $user->intern->intIntern_ID);
        }

        $evaluations = $query->orderByDesc('dtmEvaluationCompleted')->get();
        $evaluatedInternIds = TrEvaluation::where('bitActive', true)->pluck('intIntern_ID');
        $interns = MIntern::where('bitActive', true)
            ->whereNotIn('intIntern_ID', $evaluatedInternIds)
            ->orderBy('txtInternName')
            ->get();

        return [
            'authUser' => $user,
            'evaluations' => $evaluations,
            'interns' => $interns,
            'averageExposure' => round((float) $evaluations->avg('floatExposureScore'), 1),
            'averageCollaboration' => round((float) $evaluations->avg('floatCollaboration'), 1),
            'averageSharing' => round((float) $evaluations->avg('floatSharing'), 1),
            'evaluatedInterns' => $evaluations->unique('intIntern_ID')->count(),
            'publishedCertificates' => $evaluations->where('bitEvaluationCertificatePublished', true)->count(),
            'activeAssignments' => TrInternProject::where('bitActive', true)->count(),
            'canManageAnalytics' => RoleAccess::can($user, 'crud-analytics'),
        ];
    }

    private function validateEvaluation(Request $request): array
    {
        return $request->validate([
            'intIntern_ID' => ['required', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'floatHardSkill' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatCollaboration' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatOwnership' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatSharing' => ['required', 'numeric', 'min:0', 'max:100'],
            'txtEvaluationStrength' => ['required', 'string', 'max:2000'],
            'txtEvaluationDevelopment' => ['required', 'string', 'max:2000'],
            'txtEvaluationRecommendation' => ['required', 'string', 'max:2000'],
        ]);
    }

    private function averageScore(array $data): float
    {
        return round(((float) $data['floatHardSkill']
            + (float) $data['floatCollaboration']
            + (float) $data['floatOwnership']
            + (float) $data['floatSharing']) / 4, 2);
    }

    private function authorizeEvaluation(Request $request, TrEvaluation $evaluation): void
    {
        $user = $this->currentUser($request);
        $ownsEvaluation = RoleAccess::isIntern($user)
            && $user->intern->intIntern_ID === $evaluation->intIntern_ID;

        abort_unless($ownsEvaluation || ! RoleAccess::isIntern($user), 403);
    }

    private function currentUser(Request $request): MUser
    {
        return MUser::with(['intern', 'mentor', 'adminProfile'])->findOrFail($request->session()->get('auth_user_id'));
    }

    private function evaluatorName(?MUser $evaluator): string
    {
        return $evaluator?->mentor?->txtMentorName
            ?? $evaluator?->adminProfile?->txtAdminProfileName
            ?? $evaluator?->txtEmail
            ?? 'Kalbe Digital Core';
    }
}
