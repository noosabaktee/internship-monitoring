<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MSkillSet;
use App\Models\TrInternProject;
use App\Models\TrProjectStage;
use App\Support\ExposureCurveBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const PROGRESS_STATUSES = [
        0 => 'Open',
        25 => 'Inprogress',
        50 => 'Project Review',
        75 => 'Trial/testing',
        100 => 'Completed',
    ];

    private const PROGRESS_VALUES = ['0', '25', '50', '75', '100'];

    public function index(): View
    {
        $projects = MProject::with($this->projectRelations())->orderBy('intProject_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();
        $skillSets = MSkillSet::where('bitActive', true)->orderBy('txtSkillSetName')->get();

        return view('dashboard.projects', compact('projects', 'interns', 'mentors', 'skillSets'));
    }

    public function create(): View
    {
        $projects = MProject::with($this->projectRelations())->orderBy('intProject_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();
        $skillSets = MSkillSet::where('bitActive', true)->orderBy('txtSkillSetName')->get();

        return view('dashboard.projects', [
            'projects' => $projects,
            'interns' => $interns,
            'mentors' => $mentors,
            'skillSets' => $skillSets,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'txtProjectName' => ['required', 'string', 'max:255'],
            'txtProjectType' => ['required', Rule::in(['Main', 'Satellite', 'Collaboration', 'Sharing'])],
            'intSkillSet_ID' => ['required', 'integer', Rule::exists('mSkillSet', 'intSkillSet_ID')],
            'dtmProjectStartDate' => ['nullable', 'date'],
            'dtmProjectEndDate' => ['nullable', 'date', 'after_or_equal:dtmProjectStartDate'],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
            'intIntern_ID' => ['nullable', 'array'],
            'intIntern_ID.*' => ['integer', 'distinct', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'integer', Rule::exists('mMentor', 'intMentor_ID')],
            'floatProgress' => ['nullable', Rule::in(self::PROGRESS_VALUES)],
            'stages' => ['nullable', 'array'],
            'stages.*.txtProjectStageStep' => ['nullable', 'string', 'max:255'],
            'stages.*.dtmProjectStageStartDate' => ['nullable', 'date'],
            'stages.*.dtmProjectStageEndDate' => ['nullable', 'date'],
            'stages.*.floatProjectStagePlan' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stages.*.floatProjectStageActual' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $stageRows = $validated['stages'] ?? [];
        $internIds = $this->internIds($validated['intIntern_ID'] ?? []);

        if ($internIds !== [] && empty($validated['intMentor_ID'])) {
            return back()
                ->withInput()
                ->withErrors(['intMentor_ID' => 'Mentor harus dipilih saat intern di-assign ke project.']);
        }

        if (! $this->projectStageRowsAreComplete($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Setiap tahap yang diisi harus memiliki step, start date, end date, dan plan.']);
        }

        if (! $this->projectStageDatesAreValid($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'End date tahap project tidak boleh sebelum start date.']);
        }

        $stages = $this->projectStages($stageRows);

        if (! $this->stageActualsAreValid($stages)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Actual tahap project tidak boleh lebih besar dari plan.']);
        }

        if (! $this->stagePlanIsValid($stages)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Total plan tahap project harus tepat 100%.']);
        }

        DB::transaction(function () use ($validated, $stages, $internIds) {
            $now = now();
            $project = MProject::create([
                'txtProjectName' => $validated['txtProjectName'],
                'txtProjectType' => $validated['txtProjectType'],
                'intSkillSet_ID' => $validated['intSkillSet_ID'],
                'dtmProjectStartDate' => $validated['dtmProjectStartDate'] ?? null,
                'dtmProjectEndDate' => $validated['dtmProjectEndDate'] ?? null,
                'txtDescription' => $validated['txtDescription'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            $this->syncProjectAssignments($project, $internIds, $validated, $now);
            $this->syncProjectStages($project, $stages, $now);
        });

        return redirect()->route('projects.index')->with('success', 'Project data has been added.');
    }

    public function show(string $project): View
    {
        $project = MProject::with($this->projectRelations(withUsers: true))->findOrFail($project);
        $projectSCurvePayload = ExposureCurveBuilder::payload(collect([$project]));

        return view('dashboard.project-detail', compact('project', 'projectSCurvePayload'));
    }

    public function edit(string $project): View
    {
        $projects = MProject::with($this->projectRelations())->orderBy('intProject_ID')->get();
        $editingProject = MProject::with($this->projectRelations())->findOrFail($project);
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();
        $skillSets = MSkillSet::where('bitActive', true)->orderBy('txtSkillSetName')->get();

        return view('dashboard.projects', [
            'projects' => $projects,
            'editingProject' => $editingProject,
            'interns' => $interns,
            'mentors' => $mentors,
            'skillSets' => $skillSets,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $project): RedirectResponse
    {
        $projectModel = MProject::findOrFail($project);
        $validated = $request->validate([
            'txtProjectName' => ['required', 'string', 'max:255'],
            'txtProjectType' => ['required', Rule::in(['Main', 'Satellite', 'Collaboration', 'Sharing'])],
            'intSkillSet_ID' => ['required', 'integer', Rule::exists('mSkillSet', 'intSkillSet_ID')],
            'dtmProjectStartDate' => ['nullable', 'date'],
            'dtmProjectEndDate' => ['nullable', 'date', 'after_or_equal:dtmProjectStartDate'],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
            'intIntern_ID' => ['nullable', 'array'],
            'intIntern_ID.*' => ['integer', 'distinct', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'integer', Rule::exists('mMentor', 'intMentor_ID')],
            'floatProgress' => ['nullable', Rule::in(self::PROGRESS_VALUES)],
            'stages' => ['nullable', 'array'],
            'stages.*.txtProjectStageStep' => ['nullable', 'string', 'max:255'],
            'stages.*.dtmProjectStageStartDate' => ['nullable', 'date'],
            'stages.*.dtmProjectStageEndDate' => ['nullable', 'date'],
            'stages.*.floatProjectStagePlan' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stages.*.floatProjectStageActual' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $stageRows = $validated['stages'] ?? [];
        $internIds = $this->internIds($validated['intIntern_ID'] ?? []);

        if ($internIds !== [] && empty($validated['intMentor_ID'])) {
            return back()
                ->withInput()
                ->withErrors(['intMentor_ID' => 'Mentor harus dipilih saat intern di-assign ke project.']);
        }

        if (! $this->projectStageRowsAreComplete($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Setiap tahap yang diisi harus memiliki step, start date, end date, dan plan.']);
        }

        if (! $this->projectStageDatesAreValid($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'End date tahap project tidak boleh sebelum start date.']);
        }

        $stages = $this->projectStages($stageRows);

        if (! $this->stageActualsAreValid($stages)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Actual tahap project tidak boleh lebih besar dari plan.']);
        }

        if (! $this->stagePlanIsValid($stages)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Total plan tahap project harus tepat 100%.']);
        }

        DB::transaction(function () use ($projectModel, $validated, $stages, $internIds) {
            $now = now();
            $projectModel->update([
                'txtProjectName' => $validated['txtProjectName'],
                'txtProjectType' => $validated['txtProjectType'],
                'intSkillSet_ID' => $validated['intSkillSet_ID'],
                'dtmProjectStartDate' => $validated['dtmProjectStartDate'] ?? null,
                'dtmProjectEndDate' => $validated['dtmProjectEndDate'] ?? null,
                'txtDescription' => $validated['txtDescription'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);

            $this->syncProjectAssignments($projectModel, $internIds, $validated, $now);
            $this->syncProjectStages($projectModel, $stages, $now);
        });

        return redirect()->route('projects.index')->with('success', 'Project data has been updated.');
    }

    public function destroy(string $project): RedirectResponse
    {
        $projectModel = MProject::findOrFail($project);
        $now = now();

        $projectModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        TrInternProject::where('intProject_ID', $projectModel->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        TrProjectStage::where('intProject_ID', $projectModel->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('projects.index')->with('success', 'Project data has been deactivated.');
    }

    private function progressStatus(int $progress): string
    {
        return self::PROGRESS_STATUSES[$progress] ?? self::PROGRESS_STATUSES[0];
    }

    private function projectRelations(bool $withUsers = false): array
    {
        return [
            'skillSet',
            'stages',
            'assignments' => fn ($query) => $query->where('bitActive', true)->orderBy('intIntern_ID'),
            $withUsers ? 'assignments.intern.user' : 'assignments.intern',
            $withUsers ? 'assignments.mentor.user' : 'assignments.mentor',
        ];
    }

    private function internIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->filter(fn ($internId) => $internId !== null && $internId !== '')
            ->map(fn ($internId) => (int) $internId)
            ->unique()
            ->values()
            ->all();
    }

    private function syncProjectAssignments(MProject $project, array $internIds, array $validated, $now): void
    {
        $existingAssignments = TrInternProject::where('intProject_ID', $project->intProject_ID)
            ->get()
            ->groupBy('intIntern_ID');

        TrInternProject::where('intProject_ID', $project->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        $progress = (int) ($validated['floatProgress'] ?? 0);

        foreach ($internIds as $internId) {
            $assignment = ($existingAssignments->get($internId) ?? $existingAssignments->get((string) $internId))?->first();

            if ($assignment) {
                $assignment->update([
                    'intMentor_ID' => $validated['intMentor_ID'],
                    'floatProgress' => $progress,
                    'txtStatus' => $this->progressStatus($progress),
                    'bitActive' => true,
                    'txtUpdatedBy' => 'system',
                    'dtmUpdated' => $now,
                ]);

                continue;
            }

            TrInternProject::create([
                'intIntern_ID' => $internId,
                'intProject_ID' => $project->intProject_ID,
                'intMentor_ID' => $validated['intMentor_ID'],
                'floatProgress' => $progress,
                'txtStatus' => $this->progressStatus($progress),
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $stageRows
     */
    private function projectStageRowsAreComplete(array $stageRows): bool
    {
        foreach ($stageRows as $stage) {
            $hasStep = trim((string) ($stage['txtProjectStageStep'] ?? '')) !== '';
            $hasStartDate = ($stage['dtmProjectStageStartDate'] ?? '') !== '';
            $hasEndDate = ($stage['dtmProjectStageEndDate'] ?? '') !== '';
            $hasPlan = ($stage['floatProjectStagePlan'] ?? '') !== '';
            $hasActual = ($stage['floatProjectStageActual'] ?? '') !== '';
            $isStarted = $hasStep || $hasStartDate || $hasEndDate || $hasPlan || $hasActual;

            if ($isStarted && ! ($hasStep && $hasStartDate && $hasEndDate && $hasPlan)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $stageRows
     */
    private function projectStageDatesAreValid(array $stageRows): bool
    {
        foreach ($stageRows as $stage) {
            $startDate = $stage['dtmProjectStageStartDate'] ?? null;
            $endDate = $stage['dtmProjectStageEndDate'] ?? null;

            if ($startDate && $endDate && strtotime((string) $endDate) < strtotime((string) $startDate)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $stageRows
     * @return array<int, array{txtProjectStageStep: string, dtmProjectStageStartDate: string, dtmProjectStageEndDate: string, floatProjectStagePlan: float, floatProjectStageActual: float}>
     */
    private function projectStages(array $stageRows): array
    {
        return collect($stageRows)
            ->filter(fn ($stage) => ! empty($stage['txtProjectStageStep']) || ($stage['dtmProjectStageStartDate'] ?? '') !== '' || ($stage['dtmProjectStageEndDate'] ?? '') !== '' || ($stage['floatProjectStagePlan'] ?? '') !== '' || ($stage['floatProjectStageActual'] ?? '') !== '')
            ->map(fn ($stage) => [
                'txtProjectStageStep' => trim((string) ($stage['txtProjectStageStep'] ?? '')),
                'dtmProjectStageStartDate' => (string) ($stage['dtmProjectStageStartDate'] ?? ''),
                'dtmProjectStageEndDate' => (string) ($stage['dtmProjectStageEndDate'] ?? ''),
                'floatProjectStagePlan' => (float) ($stage['floatProjectStagePlan'] ?? 0),
                'floatProjectStageActual' => (float) ($stage['floatProjectStageActual'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{txtProjectStageStep: string, dtmProjectStageStartDate: string, dtmProjectStageEndDate: string, floatProjectStagePlan: float, floatProjectStageActual: float}> $stages
     */
    private function stageActualsAreValid(array $stages): bool
    {
        return collect($stages)->every(
            fn ($stage) => $stage['floatProjectStageActual'] <= $stage['floatProjectStagePlan']
        );
    }

    /**
     * @param array<int, array{txtProjectStageStep: string, dtmProjectStageStartDate: string, dtmProjectStageEndDate: string, floatProjectStagePlan: float, floatProjectStageActual: float}> $stages
     */
    private function stagePlanIsValid(array $stages): bool
    {
        if ($stages === []) {
            return true;
        }

        $totalPlan = collect($stages)->sum('floatProjectStagePlan');

        return abs($totalPlan - 100) < 0.001;
    }

    /**
     * @param array<int, array{txtProjectStageStep: string, dtmProjectStageStartDate: string, dtmProjectStageEndDate: string, floatProjectStagePlan: float, floatProjectStageActual: float}> $stages
     */
    private function syncProjectStages(MProject $project, array $stages, $now): void
    {
        TrProjectStage::where('intProject_ID', $project->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        foreach ($stages as $index => $stage) {
            TrProjectStage::create([
                'intProject_ID' => $project->intProject_ID,
                'intProjectStageNumber' => $index + 1,
                'txtProjectStageStep' => $stage['txtProjectStageStep'],
                'dtmProjectStageStartDate' => $stage['dtmProjectStageStartDate'],
                'dtmProjectStageEndDate' => $stage['dtmProjectStageEndDate'],
                'floatProjectStagePlan' => $stage['floatProjectStagePlan'],
                'floatProjectStageActual' => $stage['floatProjectStageActual'],
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        }
    }
}
