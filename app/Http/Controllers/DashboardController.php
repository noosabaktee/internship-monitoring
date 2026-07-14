<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MProject;
use App\Models\TrAchievement;
use App\Models\TrCalendarSharing;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Support\ExposureCurveBuilder;
use App\Support\ProjectScoreboard;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalInterns = MIntern::where('bitActive', true)->count();
        $interns = MIntern::with(['evaluations', 'projects.project', 'achievements'])
            ->where('bitActive', true)
            ->where(function ($query) {
                $query->where('txtInternType', RoleAccess::INTERN_DIGITALISASI)
                    ->orWhereNull('txtInternType');
            })
            ->orderBy('txtInternName')
            ->get();
        $latestEvaluations = TrEvaluation::with('intern')
            ->where('bitActive', true)
            ->whereHas('intern', function ($query) {
                $query->where('txtInternType', RoleAccess::INTERN_DIGITALISASI)
                    ->orWhereNull('txtInternType');
            })
            ->orderByDesc('dtmPeriod')
            ->get()
            ->unique('intIntern_ID');
        $leaderboardRows = ProjectScoreboard::rows();
        $topPerformers = $leaderboardRows->take(3)->values();
        $assignments = TrInternProject::with(['intern', 'project.skillSet', 'mentor'])
            ->where('bitActive', true)
            ->get();
        $activeAssignments = $assignments->filter(fn ($assignment) => $assignment->project?->bitActive);
        $projectTypes = $activeAssignments
            ->groupBy(fn ($assignment) => $assignment->project?->txtProjectType ?: 'Other')
            ->map->count();
        $skillSetProjects = $activeAssignments
            ->groupBy(fn ($assignment) => $assignment->project?->skillSet?->txtSkillSetName ?: 'Unmapped')
            ->map->count()
            ->sortDesc();
        $upcomingCalendarSharings = TrCalendarSharing::with(['creator.intern', 'creator.mentor'])
            ->where('bitActive', true)
            ->whereDate('dtmCalendarSharingDate', '>=', now()->startOfDay())
            ->orderBy('dtmCalendarSharingDate')
            ->orderBy('intCalendarSharing_ID')
            ->take(5)
            ->get();
        $sCurveProjects = MProject::with(['stages', 'assignments.intern'])
            ->where('bitActive', true)
            ->orderBy('txtProjectName')
            ->get();

        return view('dashboard.index', [
            'interns' => $interns,
            'latestEvaluations' => $latestEvaluations,
            'leaderboardRows' => $leaderboardRows,
            'topPerformers' => $topPerformers,
            'assignments' => $assignments,
            'totalInterns' => $totalInterns,
            'averageScore' => round((float) $leaderboardRows->avg('score'), 1),
            'collaborationCount' => (int) ($projectTypes['Collaboration'] ?? 0),
            'sharingCount' => (int) ($projectTypes['Sharing'] ?? 0),
            'achievementCount' => TrAchievement::where('bitActive', true)->count(),
            'skillSetProjects' => $skillSetProjects,
            'upcomingCalendarSharings' => $upcomingCalendarSharings,
            'mainSCurvePayload' => ExposureCurveBuilder::payload($sCurveProjects),
        ]);
    }
}
