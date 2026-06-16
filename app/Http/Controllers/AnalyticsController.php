<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $evaluations = TrEvaluation::where('bitActive', true)->orderBy('dtmPeriod')->get();
        $latestEvaluations = $evaluations->sortByDesc('dtmPeriod')->unique('intIntern_ID');

        return view('dashboard.analytics', [
            'evaluations' => $evaluations,
            'interns' => MIntern::where('bitActive', true)->orderBy('txtInternName')->get(),
            'averageExposure' => round((float) $latestEvaluations->avg('floatExposureScore'), 1),
            'averageCollaboration' => round((float) $latestEvaluations->avg('floatCollaboration'), 1),
            'averageSharing' => round((float) $latestEvaluations->avg('floatSharing'), 1),
            'activeInterns' => MIntern::where('bitActive', true)->count(),
            'activeAssignments' => TrInternProject::where('bitActive', true)->count(),
        ]);
    }

    public function create(): View
    {
        $evaluations = TrEvaluation::with('intern')->where('bitActive', true)->orderByDesc('dtmPeriod')->get();
        $latestEvaluations = $evaluations->sortByDesc('dtmPeriod')->unique('intIntern_ID');

        return view('dashboard.analytics', [
            'evaluations' => $evaluations,
            'interns' => MIntern::where('bitActive', true)->orderBy('txtInternName')->get(),
            'averageExposure' => round((float) $latestEvaluations->avg('floatExposureScore'), 1),
            'averageCollaboration' => round((float) $latestEvaluations->avg('floatCollaboration'), 1),
            'averageSharing' => round((float) $latestEvaluations->avg('floatSharing'), 1),
            'activeInterns' => MIntern::where('bitActive', true)->count(),
            'activeAssignments' => TrInternProject::where('bitActive', true)->count(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEvaluation($request);

        TrEvaluation::create([
            ...$validated,
            'floatExposureScore' => $this->averageScore($validated),
            'bitActive' => true,
            'txtInsertedBy' => 'system',
            'dtmInserted' => now(),
        ]);

        return redirect()->route('analytics.index')->with('success', 'Evaluasi berhasil ditambahkan.');
    }

    public function show(string $analytic): View
    {
        return view('dashboard.analytics', compact('analytic'));
    }

    public function edit(string $analytic): View
    {
        $evaluations = TrEvaluation::with('intern')->where('bitActive', true)->orderByDesc('dtmPeriod')->get();
        $latestEvaluations = $evaluations->sortByDesc('dtmPeriod')->unique('intIntern_ID');
        $editingEvaluation = TrEvaluation::findOrFail($analytic);

        return view('dashboard.analytics', [
            'evaluations' => $evaluations,
            'interns' => MIntern::where('bitActive', true)->orderBy('txtInternName')->get(),
            'averageExposure' => round((float) $latestEvaluations->avg('floatExposureScore'), 1),
            'averageCollaboration' => round((float) $latestEvaluations->avg('floatCollaboration'), 1),
            'averageSharing' => round((float) $latestEvaluations->avg('floatSharing'), 1),
            'activeInterns' => MIntern::where('bitActive', true)->count(),
            'activeAssignments' => TrInternProject::where('bitActive', true)->count(),
            'editingEvaluation' => $editingEvaluation,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $analytic): RedirectResponse
    {
        $evaluation = TrEvaluation::findOrFail($analytic);
        $validated = $this->validateEvaluation($request);

        $evaluation->update([
            ...$validated,
            'floatExposureScore' => $this->averageScore($validated),
            'bitActive' => true,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('analytics.index')->with('success', 'Evaluasi berhasil diperbarui.');
    }

    public function destroy(string $analytic): RedirectResponse
    {
        TrEvaluation::findOrFail($analytic)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('analytics.index')->with('success', 'Evaluasi berhasil dinonaktifkan.');
    }

    private function validateEvaluation(Request $request): array
    {
        return $request->validate([
            'intIntern_ID' => ['required', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'dtmPeriod' => ['required', 'date'],
            'floatHardSkill' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatCollaboration' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatOwnership' => ['required', 'numeric', 'min:0', 'max:100'],
            'floatSharing' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function averageScore(array $data): float
    {
        return round((
            (float) $data['floatHardSkill']
            + (float) $data['floatCollaboration']
            + (float) $data['floatOwnership']
            + (float) $data['floatSharing']
        ) / 4, 2);
    }
}
