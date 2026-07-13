<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MSkillSet;
use App\Models\TrInternProject;
use App\Models\TrProjectMentor;
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
            'intIntern_ID.*' => ['nullable', 'integer', 'distinct', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'array'],
            'intMentor_ID.*' => ['nullable', 'integer', 'distinct', Rule::exists('mMentor', 'intMentor_ID')],
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
        $mentorIds = $this->mentorIds($validated['intMentor_ID'] ?? []);
        $progress = (int) ($validated['floatProgress'] ?? 0);

        if (! $this->projectStageRowsArePresent($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Isi tahap project terlebih dahulu.']);
        }

        if ($internIds !== [] && $mentorIds === []) {
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

        DB::transaction(function () use ($validated, $stages, $internIds, $mentorIds, $progress) {
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

            $this->syncProjectAssignments($project, $internIds, $mentorIds, $progress, $now);
            $this->syncProjectMentors($project, $mentorIds, $now);
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
        $this->hydrateProjectAssignmentFallbacks($editingProject);
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
        $projectModel = MProject::with($this->projectRelations())->findOrFail($project);
        $validated = $request->validate([
            'txtProjectName' => ['required', 'string', 'max:255'],
            'txtProjectType' => ['required', Rule::in(['Main', 'Satellite', 'Collaboration', 'Sharing'])],
            'intSkillSet_ID' => ['required', 'integer', Rule::exists('mSkillSet', 'intSkillSet_ID')],
            'dtmProjectStartDate' => ['nullable', 'date'],
            'dtmProjectEndDate' => ['nullable', 'date', 'after_or_equal:dtmProjectStartDate'],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
            'intIntern_ID' => ['nullable', 'array'],
            'intIntern_ID.*' => ['nullable', 'integer', 'distinct', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'array'],
            'intMentor_ID.*' => ['nullable', 'integer', 'distinct', Rule::exists('mMentor', 'intMentor_ID')],
            'floatProgress' => ['nullable', Rule::in(self::PROGRESS_VALUES)],
            'stages' => ['nullable', 'array'],
            'stages.*.txtProjectStageStep' => ['nullable', 'string', 'max:255'],
            'stages.*.dtmProjectStageStartDate' => ['nullable', 'date'],
            'stages.*.dtmProjectStageEndDate' => ['nullable', 'date'],
            'stages.*.floatProjectStagePlan' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stages.*.floatProjectStageActual' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $stageRows = $request->has('stages') || $request->has('stages_present')
            ? ($validated['stages'] ?? [])
            : $this->stageRowsFromProject($projectModel);
        $submittedInternIds = $this->internIds($validated['intIntern_ID'] ?? []);
        $submittedMentorIds = $this->mentorIds($validated['intMentor_ID'] ?? []);
        $internIds = $request->boolean('intIntern_ID_touched') || $submittedInternIds !== []
            ? $submittedInternIds
            : $this->storedInternIds($projectModel);
        $mentorIds = $request->boolean('intMentor_ID_touched') || $submittedMentorIds !== []
            ? $submittedMentorIds
            : $this->storedProjectMentorIds($projectModel);
        $progress = $request->has('floatProgress')
            ? (int) ($validated['floatProgress'] ?? 0)
            : $this->activeProjectProgress($projectModel);

        if (! $this->projectStageRowsArePresent($stageRows)) {
            return back()
                ->withInput()
                ->withErrors(['stages' => 'Isi tahap project terlebih dahulu.']);
        }

        if ($internIds !== [] && $mentorIds === []) {
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

        DB::transaction(function () use ($projectModel, $validated, $stages, $internIds, $mentorIds, $progress) {
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

            $this->syncProjectAssignments($projectModel, $internIds, $mentorIds, $progress, $now);
            $this->syncProjectMentors($projectModel, $mentorIds, $now);
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
        TrProjectMentor::where('intProject_ID', $projectModel->intProject_ID)->update([
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
            'projectMentors' => fn ($query) => $query->where('bitActive', true)->orderBy('intMentor_ID'),
            $withUsers ? 'projectMentors.mentor.user' : 'projectMentors.mentor',
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

    private function mentorIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->filter(fn ($mentorId) => $mentorId !== null && $mentorId !== '')
            ->map(fn ($mentorId) => (int) $mentorId)
            ->unique()
            ->values()
            ->all();
    }

    private function activeInternIds(MProject $project): array
    {
        return $this->internIds($project->assignments
            ->where('bitActive', true)
            ->pluck('intIntern_ID')
            ->all());
    }

    private function storedInternIds(MProject $project): array
    {
        $activeInternIds = $this->activeInternIds($project);

        if ($activeInternIds !== []) {
            return $activeInternIds;
        }

        return $this->internIds($this->storedProjectAssignments($project)
            ->pluck('intIntern_ID')
            ->all());
    }

    private function activeProjectMentorIds(MProject $project): array
    {
        $mentorIds = $this->mentorIds($project->projectMentors
            ->where('bitActive', true)
            ->pluck('intMentor_ID')
            ->all());

        if ($mentorIds !== []) {
            return $mentorIds;
        }

        return $this->mentorIds($project->assignments
            ->where('bitActive', true)
            ->pluck('intMentor_ID')
            ->all());
    }

    private function storedProjectMentorIds(MProject $project): array
    {
        $activeMentorIds = $this->activeProjectMentorIds($project);

        if ($activeMentorIds !== []) {
            return $activeMentorIds;
        }

        $projectMentorIds = $this->mentorIds($this->storedProjectMentors($project)
            ->pluck('intMentor_ID')
            ->all());

        if ($projectMentorIds !== []) {
            return $projectMentorIds;
        }

        return $this->mentorIds($this->storedProjectAssignments($project)
            ->pluck('intMentor_ID')
            ->all());
    }

    private function activeProjectProgress(MProject $project): int
    {
        $activeProgress = $project->assignments
            ->where('bitActive', true)
            ->first()?->floatProgress;

        if ($activeProgress !== null) {
            return (int) $activeProgress;
        }

        return (int) ($this->storedProjectAssignments($project)->first()?->floatProgress ?? 0);
    }

    private function hydrateProjectAssignmentFallbacks(MProject $project): void
    {
        if ($project->assignments->isEmpty()) {
            $project->setRelation('assignments', $this->storedProjectAssignments($project));
        }

        if ($project->projectMentors->isEmpty()) {
            $project->setRelation('projectMentors', $this->storedProjectMentors($project));
        }
    }

    private function storedProjectAssignments(MProject $project)
    {
        return TrInternProject::with(['intern', 'mentor'])
            ->where('intProject_ID', $project->intProject_ID)
            ->orderByDesc('bitActive')
            ->orderByDesc('dtmUpdated')
            ->orderByDesc('dtmInserted')
            ->orderBy('intInternProject_ID')
            ->get()
            ->unique('intIntern_ID')
            ->values();
    }

    private function storedProjectMentors(MProject $project)
    {
        return TrProjectMentor::with('mentor')
            ->where('intProject_ID', $project->intProject_ID)
            ->orderByDesc('bitActive')
            ->orderByDesc('dtmUpdated')
            ->orderByDesc('dtmInserted')
            ->orderBy('intProjectMentor_ID')
            ->get()
            ->unique('intMentor_ID')
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stageRowsFromProject(MProject $project): array
    {
        return $project->stages
            ->map(fn ($stage) => [
                'txtProjectStageStep' => $stage->txtProjectStageStep,
                'dtmProjectStageStartDate' => $stage->dtmProjectStageStartDate?->format('Y-m-d'),
                'dtmProjectStageEndDate' => $stage->dtmProjectStageEndDate?->format('Y-m-d'),
                'floatProjectStagePlan' => $stage->floatProjectStagePlan,
                'floatProjectStageActual' => $stage->floatProjectStageActual,
            ])
            ->values()
            ->all();
    }

    private function syncProjectAssignments(MProject $project, array $internIds, array $mentorIds, int $progress, $now): void
    {
        $existingAssignments = TrInternProject::where('intProject_ID', $project->intProject_ID)
            ->get()
            ->groupBy('intIntern_ID');

        TrInternProject::where('intProject_ID', $project->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        $primaryMentorId = $mentorIds[0] ?? null;

        foreach ($internIds as $internId) {
            $assignment = ($existingAssignments->get($internId) ?? $existingAssignments->get((string) $internId))?->first();

            if ($assignment) {
                TrInternProject::where($assignment->getKeyName(), $assignment->getKey())->update([
                    'intMentor_ID' => $primaryMentorId,
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
                'intMentor_ID' => $primaryMentorId,
                'floatProgress' => $progress,
                'txtStatus' => $this->progressStatus($progress),
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        }
    }

    private function syncProjectMentors(MProject $project, array $mentorIds, $now): void
    {
        $existingProjectMentors = TrProjectMentor::where('intProject_ID', $project->intProject_ID)
            ->get()
            ->groupBy('intMentor_ID');

        TrProjectMentor::where('intProject_ID', $project->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        foreach ($mentorIds as $mentorId) {
            $projectMentor = ($existingProjectMentors->get($mentorId) ?? $existingProjectMentors->get((string) $mentorId))?->first();

            if ($projectMentor) {
                TrProjectMentor::where($projectMentor->getKeyName(), $projectMentor->getKey())->update([
                    'bitActive' => true,
                    'txtUpdatedBy' => 'system',
                    'dtmUpdated' => $now,
                ]);

                continue;
            }

            TrProjectMentor::create([
                'intProject_ID' => $project->intProject_ID,
                'intMentor_ID' => $mentorId,
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $stageRows
     */
    private function projectStageRowsArePresent(array $stageRows): bool
    {
        foreach ($stageRows as $stage) {
            $hasStep = trim((string) ($stage['txtProjectStageStep'] ?? '')) !== '';
            $hasStartDate = ($stage['dtmProjectStageStartDate'] ?? '') !== '';
            $hasEndDate = ($stage['dtmProjectStageEndDate'] ?? '') !== '';
            $hasPlan = ($stage['floatProjectStagePlan'] ?? '') !== '';

            if ($hasStep || $hasStartDate || $hasEndDate || $hasPlan) {
                return true;
            }
        }

        return false;
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
