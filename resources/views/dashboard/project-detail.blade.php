@extends('layouts.app', [
'title' => $project->txtProjectName . ' - Project Detail',
'pageTitle' => 'PROJECT DETAIL',
'pageSubtitle' => 'Project overview, assignments, plan, and actual progress.',
])

@php
$assignments = $project->assignments->where('bitActive', true);
$projectMentors = $project->projectMentors->where('bitActive', true);
$mentorNames = $projectMentors
    ->map(fn ($projectMentor) => $projectMentor->mentor?->txtMentorName)
    ->filter()
    ->unique()
    ->values();
if ($mentorNames->isEmpty()) {
    $mentorNames = $assignments
        ->map(fn ($assignment) => $assignment->mentor?->txtMentorName)
        ->filter()
        ->unique()
        ->values();
}
$totalStagePlan = (float) $project->stages->sum('floatProjectStagePlan');
$totalStageActual = (float) $project->stages->sum('floatProjectStageActual');
$stageProgress = $totalStagePlan > 0 ? ($totalStageActual / $totalStagePlan) * 100 : 0;
$startDate = $project->dtmProjectStartDate;
$endDate = $project->dtmProjectEndDate;
$durationDays = $startDate && $endDate ? $startDate->diffInDays($endDate) + 1 : null;
$today = now()->startOfDay();
$daysRemaining = $endDate ? $today->diffInDays($endDate, false) : null;
$authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
$canManageProjects = $authUser && \App\Support\RoleAccess::can($authUser, 'crud-projects');
@endphp

@section('content')
<div class="project-detail-actions">
    <a class="btn btn-outline-primary btn-sm" href="{{ route('projects.index') }}"><i class="fa-solid fa-arrow-left"></i> Back to Projects</a>
    @if ($canManageProjects)
    <a class="btn btn-primary btn-sm" href="{{ route('projects.edit', $project->intProject_ID) }}"><i class="fa-solid fa-pen"></i> Edit Project</a>
    @endif
</div>

<section class="project-detail-hero">
    <div class="project-detail-hero-copy">
        <div class="project-detail-kicker">
            <span>{{ $project->txtProjectType }}</span>
            <span>{{ $project->skillSet?->txtSkillSetName ?? 'No Skill Set' }}</span>
            <span>{{ $project->bitActive ? 'Active' : 'Inactive' }}</span>
        </div>
        <h2>{{ $project->txtProjectName }}</h2>
        <p>{{ $project->txtDescription ?: 'No description has been added for this project.' }}</p>
    </div>
    <div class="project-detail-progress">
        <span>Actual vs Plan</span>
        <strong>{{ number_format($stageProgress, 0) }}%</strong>
        <div class="progress-track">
            <div class="progress-fill" style="width: {{ min(100, max(0, $stageProgress)) }}%;"></div>
        </div>
        <small>Actual {{ number_format($totalStageActual, 0) }}% of Plan {{ number_format($totalStagePlan, 0) }}%</small>
    </div>
</section>

<section class="project-detail-metrics">
    <div class="project-detail-metric">
        <i class="fa-solid fa-calendar-day"></i>
        <span>Start</span>
        <strong>{{ $startDate?->format('d M Y') ?? '-' }}</strong>
    </div>
    <div class="project-detail-metric">
        <i class="fa-solid fa-calendar-check"></i>
        <span>End</span>
        <strong>{{ $endDate?->format('d M Y') ?? '-' }}</strong>
    </div>
    <div class="project-detail-metric">
        <i class="fa-solid fa-hourglass-half"></i>
        <span>Duration</span>
        <strong>{{ $durationDays ? $durationDays . ' days' : '-' }}</strong>
    </div>
    <div class="project-detail-metric">
        <i class="fa-solid fa-layer-group"></i>
        <span>Plan / Actual</span>
        <strong>{{ number_format($totalStagePlan, 0) }}% / {{ number_format($totalStageActual, 0) }}%</strong>
    </div>
</section>

<div class="project-detail-grid">
    <section class="project-detail-panel">
        <div class="project-detail-panel-head">
            <h3>Project Information</h3>
            <span class="status-badge {{ $project->bitActive ? 'status-active' : 'status-inactive' }}">{{ $project->bitActive ? 'Active' : 'Inactive' }}</span>
        </div>
        <dl class="project-detail-list">
            <div>
                <dt>Project ID</dt>
                <dd>{{ $project->intProject_ID }}</dd>
            </div>
            <div>
                <dt>Type</dt>
                <dd>{{ $project->txtProjectType }}</dd>
            </div>
            <div>
                <dt>Skill Set</dt>
                <dd>{{ $project->skillSet?->txtSkillSetName ?? '-' }}</dd>
            </div>
            <div>
                <dt>Timeline</dt>
                <dd>{{ $startDate?->format('d M Y') ?? '-' }} - {{ $endDate?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Time Remaining</dt>
                <dd>{{ $daysRemaining === null ? '-' : ($daysRemaining < 0 ? abs($daysRemaining) . ' days overdue' : $daysRemaining . ' days left') }}</dd>
            </div>
            <div>
                <dt>Created</dt>
                <dd>{{ $project->dtmInserted?->format('d M Y H:i') ?? '-' }} by {{ $project->txtInsertedBy ?: '-' }}</dd>
            </div>
            <div>
                <dt>Updated</dt>
                <dd>{{ $project->dtmUpdated?->format('d M Y H:i') ?? '-' }} by {{ $project->txtUpdatedBy ?: '-' }}</dd>
            </div>
        </dl>
    </section>

    <section class="project-detail-panel">
        <div class="project-detail-panel-head">
            <h3>Assignments</h3>
            <span>{{ $assignments->count() }} intern / {{ $mentorNames->count() }} mentor</span>
        </div>
        <div class="project-assignment-list">
            @forelse ($assignments as $assignment)
            <div class="project-assignment-item">
                <div>
                    <strong>{{ $assignment->intern?->txtInternName ?? '-' }}</strong>
                    <p>{{ $assignment->intern?->txtDept ?: 'No department' }}</p>
                    <p>{{ $assignment->intern?->user?->txtEmail ?: '-' }}</p>
                </div>
                <div>
                    <span>{{ $mentorNames->isNotEmpty() ? $mentorNames->join(', ') : ($assignment->mentor?->txtMentorName ?? '-') }}</span>
                    <div class="mini-progress">
                        <span>{{ number_format((float) $assignment->floatProgress, 0) }}%</span>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ min(100, max(0, (float) $assignment->floatProgress)) }}%;"></div>
                        </div>
                    </div>
                    <small>{{ $assignment->txtStatus ?: '-' }}</small>
                </div>
            </div>
            @empty
            <p class="project-detail-empty">No intern has been assigned to this project yet.</p>
            @endforelse
        </div>
    </section>
</div>

<x-s-curve-chart
    :payload="$projectSCurvePayload"
    title="Exposure"
    subtitle="S Curve planned vs actual for this project."
    mode="project"
    height="320px"
    class="project-detail-exposure" />

<section class="project-detail-panel project-stage-panel">
    <div class="project-detail-panel-head">
        <h3>Project Stages</h3>
        <span>{{ $project->stages->count() }} stages | Plan {{ number_format($totalStagePlan, 0) }}% | Actual {{ number_format($totalStageActual, 0) }}%</span>
    </div>
    <div class="project-stage-timeline">
        @forelse ($project->stages as $stage)
        @php
        $stagePlan = (float) $stage->floatProjectStagePlan;
        $stageActual = (float) $stage->floatProjectStageActual;
        $stageCompletion = $stagePlan > 0 ? ($stageActual / $stagePlan) * 100 : 0;
        @endphp
        <article class="project-stage-card">
            <div class="project-stage-badge">{{ $stage->intProjectStageNumber }}</div>
            <div class="project-stage-card-body">
                <div class="project-stage-card-head">
                    <h4>{{ $stage->txtProjectStageStep }}</h4>
                    <strong>{{ number_format($stageCompletion, 0) }}%</strong>
                </div>
                <div class="project-stage-card-dates">
                    <span><i class="fa-solid fa-calendar-day"></i> {{ $stage->dtmProjectStageStartDate?->format('d M Y') ?? '-' }} <i class="fa-solid fa-arrow-right"></i> {{ $stage->dtmProjectStageEndDate?->format('d M Y') ?? '-' }}</span>
                </div>
                <div class="project-stage-card-progress">
                    <span>Plan {{ number_format($stagePlan, 0) }}%</span>
                    <span>Actual {{ number_format($stageActual, 0) }}%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: {{ min(100, max(0, $stageCompletion)) }}%;"></div>
                </div>
            </div>
        </article>
        @empty
        <p class="project-detail-empty">No project stages have been added yet.</p>
        @endforelse
    </div>
</section>
@endsection
