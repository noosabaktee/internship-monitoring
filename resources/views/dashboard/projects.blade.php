@extends('layouts.app', [
    'title' => 'Projects - Kalbe Internship Dashboard',
    'pageTitle' => 'PROJECTS',
    'pageSubtitle' => 'Manage Kalbe project data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingProject)
        ? route('projects.update', $editingProject->intProject_ID)
        : route('projects.store');
    $assignment = isset($editingProject) ? $editingProject->assignments->first() : null;
    $progressOptions = [
        0 => 'Open',
        25 => 'Inprogress',
        50 => 'Project Review',
        75 => 'Trial/testing',
        100 => 'Completed',
    ];
    $projectStageRows = old('stages');
    if ($projectStageRows === null && isset($editingProject)) {
        $projectStageRows = $editingProject->stages
            ->map(fn ($stage) => [
                'txtProjectStageStep' => $stage->txtProjectStageStep,
                'floatProjectStageWeight' => $stage->floatProjectStageWeight,
            ])
            ->values()
            ->all();
    }
    $projectStageRows = is_array($projectStageRows) ? array_values($projectStageRows) : [];
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Project Data</h2>
            <p>Track status and PIC for Collaboration, Main, Satellite, and Sharing projects.</p>
        </div>
        <a class="btn btn-primary btn-add" href="{{ route('projects.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Project</a>
    </div>

    @if ($isFormOpen)
        <section class="profile-card mb-4">
            <div class="profile-card-header d-flex align-items-start justify-content-between gap-3">
                <div class="profile-card-title">
                    <h2>{{ isset($editingProject) ? 'Edit Project' : 'Add New Project' }}</h2>
                    <p>Project master data is stored in mProject. Intern, mentor, and progress assignments are stored in trInternProject.</p>
                </div>
                <a href="{{ route('projects.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>

            <form class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingProject)
                    @method('PUT')
                @endisset

                <div class="col-md-6">
                    <label class="form-label">Project Name</label>
                    <input class="form-control" name="txtProjectName" value="{{ old('txtProjectName', $editingProject->txtProjectName ?? '') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-control" name="txtProjectType" required>
                        @foreach (['Main', 'Satellite', 'Collaboration', 'Sharing'] as $type)
                            <option value="{{ $type }}" @selected(old('txtProjectType', $editingProject->txtProjectType ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Skill Set</label>
                    <select class="form-control" name="intSkillSet_ID" required>
                        <option value="">Select skill set</option>
                        @foreach ($skillSets as $skillSet)
                            <option value="{{ $skillSet->intSkillSet_ID }}" @selected((string) old('intSkillSet_ID', $editingProject->intSkillSet_ID ?? '') === (string) $skillSet->intSkillSet_ID)>{{ $skillSet->txtSkillSetName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingProject->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingProject->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Project</label>
                    <input class="form-control" type="date" name="dtmProjectStartDate" value="{{ old('dtmProjectStartDate', isset($editingProject) && $editingProject->dtmProjectStartDate ? $editingProject->dtmProjectStartDate->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Project</label>
                    <input class="form-control" type="date" name="dtmProjectEndDate" value="{{ old('dtmProjectEndDate', isset($editingProject) && $editingProject->dtmProjectEndDate ? $editingProject->dtmProjectEndDate->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Intern</label>
                    <select class="form-control" name="intIntern_ID">
                        <option value="">Not assigned yet</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $assignment->intIntern_ID ?? '') === (string) $intern->intIntern_ID)>{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mentor</label>
                    <select class="form-control" name="intMentor_ID">
                        <option value="">Not assigned yet</option>
                        @foreach ($mentors as $mentor)
                            <option value="{{ $mentor->intMentor_ID }}" @selected((string) old('intMentor_ID', $assignment->intMentor_ID ?? '') === (string) $mentor->intMentor_ID)>{{ $mentor->txtMentorName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Progress (%)</label>
                    <select class="form-control" name="floatProgress">
                        @foreach ($progressOptions as $progressValue => $progressLabel)
                            <option value="{{ $progressValue }}" @selected((string) old('floatProgress', (int) ($assignment->floatProgress ?? 0)) === (string) $progressValue)>{{ $progressLabel }} - {{ $progressValue }}%</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="txtDescription" rows="3">{{ old('txtDescription', $editingProject->txtDescription ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <div class="project-stage-head">
                        <div>
                            <label class="form-label">Project Stages</label>
                            <div class="project-stage-total" id="projectStageTotal">Total: 0%</div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" id="addProjectStageButton" type="button"><i class="fa-solid fa-plus"></i> Add Tahap</button>
                    </div>
                    <p class="project-stage-warning" id="projectStageWarning" hidden></p>
                    <div class="project-stage-list" id="projectStageList">
                        @foreach ($projectStageRows as $stageIndex => $stage)
                            <div class="project-stage-row">
                                <div class="project-stage-number">Tahap {{ $stageIndex + 1 }}</div>
                                <div>
                                    <label class="form-label">Step</label>
                                    <input class="form-control project-stage-step" name="stages[{{ $stageIndex }}][txtProjectStageStep]" value="{{ $stage['txtProjectStageStep'] ?? '' }}">
                                </div>
                                <div>
                                    <label class="form-label">Weight (%)</label>
                                    <input class="form-control project-stage-weight" type="number" min="0" max="100" step="0.01" name="stages[{{ $stageIndex }}][floatProjectStageWeight]" value="{{ $stage['floatProjectStageWeight'] ?? '' }}">
                                </div>
                                <button class="btn-icon btn-delete project-stage-remove" type="button" title="Remove stage"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-save" type="submit">{{ isset($editingProject) ? 'Save Changes' : 'Save Project' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Project Name</th><th>Type</th><th>Skill Set</th><th>Stages</th><th>Start</th><th>End</th><th>Intern</th><th>PIC / Mentor</th><th>Progress</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        @php
                            $rowAssignment = $project->assignments->first();
                        @endphp
                        <tr>
                            <td><a class="auth-link" href="{{ route('projects.show', $project->intProject_ID) }}"><strong>{{ $project->txtProjectName }}</strong></a><br><span style="color:var(--text-gray); font-size:11px;">{{ $project->txtDescription }}</span></td>
                            <td>{{ $project->txtProjectType }}</td>
                            <td>{{ $project->skillSet?->txtSkillSetName ?? '-' }}</td>
                            <td>{{ $project->stages->count() ? $project->stages->count() . ' Tahap / ' . number_format((float) $project->stages->sum('floatProjectStageWeight'), 0) . '%' : '-' }}</td>
                            <td>{{ $project->dtmProjectStartDate?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $project->dtmProjectEndDate?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $rowAssignment?->intern?->txtInternName ?? '-' }}</td>
                            <td>{{ $rowAssignment?->mentor?->txtMentorName ?? '-' }}</td>
                            <td>
                                @php
                                    $progress = (float) ($rowAssignment?->floatProgress ?? 0);
                                @endphp
                                <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                    <div style="background:var(--border); border-radius:3px; height:8px; flex:1;"><div style="background:var(--secondary); width:{{ $progress }}%; height:100%; border-radius:3px;"></div></div>
                                    <strong>{{ number_format($progress, 0) }}%</strong>
                                </div>
                            </td>
                            <td><span class="status-badge {{ $project->bitActive ? 'status-active' : 'status-inactive' }}">{{ $project->bitActive ? ($rowAssignment?->txtStatus ?? 'Active') : 'Inactive' }}</span></td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-icon btn-edit" href="{{ route('projects.edit', $project->intProject_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                    <form action="{{ route('projects.destroy', $project->intProject_ID) }}" method="POST" onsubmit="return confirm('Deactivate this project?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="center">No project data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
