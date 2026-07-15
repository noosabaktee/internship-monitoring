<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MProject;
use App\Support\ExposureCurveBuilder;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;

class ExposureController extends Controller
{
    public function index(): View
    {
        $projects = MProject::with([
            'stages',
            'assignments' => function ($query) {
                $query->where('bitActive', true)
                    ->whereHas('intern', function ($query) {
                        $query->where('bitActive', true);
                        RoleAccess::constrainDigitalisasiInterns($query);
                    })
                    ->with('intern');
            },
        ])
            ->where('bitActive', true)
            ->whereHas('assignments', function ($query) {
                $query->where('bitActive', true)
                    ->whereHas('intern', function ($query) {
                        $query->where('bitActive', true);
                        RoleAccess::constrainDigitalisasiInterns($query);
                    });
            })
            ->orderBy('txtProjectName')
            ->get();
        $interns = MIntern::where('bitActive', true)
            ->where(fn ($query) => RoleAccess::constrainDigitalisasiInterns($query))
            ->orderBy('txtInternName')
            ->get();
        $projectTypes = ExposureCurveBuilder::projectTypes();
        $exposurePayload = [
            ...ExposureCurveBuilder::payload($projects),
            'interns' => $interns->map(fn (MIntern $intern) => [
                'id' => (string) $intern->intIntern_ID,
                'name' => $intern->txtInternName,
                'department' => $intern->txtDept ?: '-',
            ])->values()->all(),
        ];

        $summary = [
            'projects' => $projects->count(),
            'interns' => $interns->count(),
            'stages' => $projects->sum(fn (MProject $project) => $project->stages->count()),
            'assignments' => $projects->sum(fn (MProject $project) => $project->assignments
                ->filter(fn ($assignment) => $assignment->bitActive && $assignment->intern?->bitActive)
                ->count()),
        ];

        return view('dashboard.exposure', compact('exposurePayload', 'projectTypes', 'projects', 'interns', 'summary'));
    }
}
