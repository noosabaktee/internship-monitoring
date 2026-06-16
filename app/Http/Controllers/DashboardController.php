<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MProject;
use App\Models\TrAchievement;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $interns = MIntern::with(['evaluations', 'projects.project', 'achievements'])
            ->where('bitActive', true)
            ->orderBy('txtInternName')
            ->get();
        $latestEvaluations = TrEvaluation::with('intern')
            ->where('bitActive', true)
            ->orderByDesc('dtmPeriod')
            ->get()
            ->unique('intIntern_ID');
        $topPerformers = $latestEvaluations->sortByDesc('floatExposureScore')->take(3)->values();
        $assignments = TrInternProject::with(['intern', 'project', 'mentor'])->where('bitActive', true)->get();
        $projectTypes = MProject::where('bitActive', true)
            ->select('txtProjectType', DB::raw('count(*) as total'))
            ->groupBy('txtProjectType')
            ->pluck('total', 'txtProjectType');

        return view('dashboard.index', [
            'interns' => $interns,
            'latestEvaluations' => $latestEvaluations,
            'topPerformers' => $topPerformers,
            'assignments' => $assignments,
            'totalInterns' => $interns->count(),
            'averageScore' => round((float) $latestEvaluations->avg('floatExposureScore'), 1),
            'collaborationCount' => (int) ($projectTypes['Collaboration'] ?? 0),
            'sharingCount' => (int) ($projectTypes['Sharing'] ?? 0),
            'achievementCount' => TrAchievement::where('bitActive', true)->count(),
        ]);
    }
}
