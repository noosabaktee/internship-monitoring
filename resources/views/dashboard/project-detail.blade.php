@extends('layouts.app', [
    'title' => $project->txtProjectName . ' - Project Detail',
    'pageTitle' => 'PROJECT DETAIL',
    'pageSubtitle' => 'Project overview, assignments, and stage plan.',
])

@php
    $assignments = $project->assignments;
    $primaryAssignment = $assignments->first();
    $averageProgress = (float) ($assignments->avg('floatProgress') ?? 0);
    $totalStageWeight = (float) $project->stages->sum('floatProjectStageWeight');
    $startDate = $project->dtmProjectStartDate;
    $endDate = $project->dtmProjectEndDate;
    $durationDays = $startDate && $endDate ? $startDate->diffInDays($endDate) + 1 : null;
    $today = now()->startOfDay();
    $daysRemaining = $endDate ? $today->diffInDays($endDate, false) : null;
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="project-detail-actions">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('projects.index') }}"><i class="fa-solid fa-arrow-left"></i> Back to Projects</a>
        @if ($isMentor)
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
            <span>Progress</span>
            <strong>{{ number_format($averageProgress, 0) }}%</strong>
            <div class="progress-track">
                <div class="progress-fill" style="width: {{ min(100, max(0, $averageProgress)) }}%;"></div>
            </div>
            <small>{{ $primaryAssignment?->txtStatus ?? ($project->bitActive ? 'Active' : 'Inactive') }}</small>
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
            <span>Stages</span>
            <strong>{{ $project->stages->count() }} / {{ number_format($totalStageWeight, 0) }}%</strong>
        </div>
    </section>

    <div class="project-detail-grid">
        <section class="project-detail-panel">
            <div class="project-detail-panel-head">
                <h3>Project Information</h3>
                <span class="status-badge {{ $project->bitActive ? 'status-active' : 'status-inactive' }}">{{ $project->bitActive ? 'Active' : 'Inactive' }}</span>
            </div>
            <dl class="project-detail-list">
                <div><dt>Project ID</dt><dd>{{ $project->intProject_ID }}</dd></div>
                <div><dt>Type</dt><dd>{{ $project->txtProjectType }}</dd></div>
                <div><dt>Skill Set</dt><dd>{{ $project->skillSet?->txtSkillSetName ?? '-' }}</dd></div>
                <div><dt>Timeline</dt><dd>{{ $startDate?->format('d M Y') ?? '-' }} - {{ $endDate?->format('d M Y') ?? '-' }}</dd></div>
                <div><dt>Time Remaining</dt><dd>{{ $daysRemaining === null ? '-' : ($daysRemaining < 0 ? abs($daysRemaining) . ' days overdue' : $daysRemaining . ' days left') }}</dd></div>
                <div><dt>Created</dt><dd>{{ $project->dtmInserted?->format('d M Y H:i') ?? '-' }} by {{ $project->txtInsertedBy ?: '-' }}</dd></div>
                <div><dt>Updated</dt><dd>{{ $project->dtmUpdated?->format('d M Y H:i') ?? '-' }} by {{ $project->txtUpdatedBy ?: '-' }}</dd></div>
            </dl>
        </section>

        <section class="project-detail-panel">
            <div class="project-detail-panel-head">
                <h3>Assignments</h3>
                <span>{{ $assignments->count() }} intern</span>
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
                            <span>{{ $assignment->mentor?->txtMentorName ?? '-' }}</span>
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

    <section class="project-detail-panel project-stage-panel">
        <div class="project-detail-panel-head">
            <h3>Project Stages</h3>
            <span>Total Weight {{ number_format($totalStageWeight, 0) }}%</span>
        </div>
        <div class="project-stage-timeline">
            @forelse ($project->stages as $stage)
                <article class="project-stage-card">
                    <div class="project-stage-badge">{{ $stage->intProjectStageNumber }}</div>
                    <div class="project-stage-card-body">
                        <div class="project-stage-card-head">
                            <h4>{{ $stage->txtProjectStageStep }}</h4>
                            <strong>{{ number_format((float) $stage->floatProjectStageWeight, 0) }}%</strong>
                        </div>
                        <div class="project-stage-card-dates">
                            <span><i class="fa-solid fa-calendar-day"></i> {{ $stage->dtmProjectStageStartDate?->format('d M Y') ?? '-' }} <i class="fa-solid fa-arrow-right"></i> {{ $stage->dtmProjectStageEndDate?->format('d M Y') ?? '-' }}</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ min(100, max(0, (float) $stage->floatProjectStageWeight)) }}%;"></div>
                        </div>
                    </div>
                </article>
            @empty
                <p class="project-detail-empty">No project stages have been added yet.</p>
            @endforelse
        </div>
    </section>
@endsection
