@extends('layouts.app', [
    'title' => 'Analytics - Kalbe Internship Dashboard',
    'pageTitle' => 'ANALYTICS',
    'pageSubtitle' => 'Manage Kalbe analytics data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingEvaluation)
        ? route('analytics.update', $editingEvaluation->intEvaluation_ID)
        : route('analytics.store');
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Analytics</h2>
            <p>Statistics, evaluations, and detailed performance reports.</p>
        </div>
        @if ($isMentor)
            <a class="btn btn-primary btn-add" href="{{ route('analytics.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Evaluation</a>
        @endif
    </div>

    <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div><div class="kpi-data"><h4>Average Exposure</h4><h2>{{ $averageExposure }}</h2><p>{{ $activeInterns }} active interns</p></div></div>
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-people-arrows"></i></div><div class="kpi-data"><h4>Collaboration Avg</h4><h2>{{ $averageCollaboration }}</h2><p>{{ $activeAssignments }} active assignments</p></div></div>
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-book-open"></i></div><div class="kpi-data"><h4>Sharing Avg</h4><h2>{{ $averageSharing }}</h2><p>Knowledge activity</p></div></div>
    </div>

    @if ($isFormOpen)
        <section class="profile-card mb-4">
            <div class="profile-card-header d-flex align-items-start justify-content-between gap-3">
                <div class="profile-card-title">
                    <h2>{{ isset($editingEvaluation) ? 'Edit Evaluation' : 'Add Evaluation' }}</h2>
                    <p>Exposure score is calculated automatically from the average of four evaluation scores.</p>
                </div>
                <a href="{{ route('analytics.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>

            <form class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingEvaluation)
                    @method('PUT')
                @endisset

                <div class="col-md-6">
                    <label class="form-label">Intern</label>
                    <select class="form-control" name="intIntern_ID" required>
                        <option value="">Select intern</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $editingEvaluation->intIntern_ID ?? '') === (string) $intern->intIntern_ID)>{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Period</label><input class="form-control" type="month" name="dtmPeriod" value="{{ old('dtmPeriod', isset($editingEvaluation) && $editingEvaluation->dtmPeriod ? $editingEvaluation->dtmPeriod->format('Y-m') : now()->format('Y-m')) }}" required></div>
                <div class="col-md-3"><label class="form-label">Hard Skill</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="floatHardSkill" value="{{ old('floatHardSkill', $editingEvaluation->floatHardSkill ?? 0) }}" required></div>
                <div class="col-md-3"><label class="form-label">Collaboration</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="floatCollaboration" value="{{ old('floatCollaboration', $editingEvaluation->floatCollaboration ?? 0) }}" required></div>
                <div class="col-md-3"><label class="form-label">Ownership</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="floatOwnership" value="{{ old('floatOwnership', $editingEvaluation->floatOwnership ?? 0) }}" required></div>
                <div class="col-md-3"><label class="form-label">Sharing</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="floatSharing" value="{{ old('floatSharing', $editingEvaluation->floatSharing ?? 0) }}" required></div>
                <div class="col-12"><button class="btn btn-primary btn-save" type="submit">{{ isset($editingEvaluation) ? 'Save Changes' : 'Save Evaluation' }}</button></div>
            </form>
        </section>
    @endif

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-title" style="margin-bottom: 10px;">EXPOSURE PROGRESSION</div>
        <div style="height: 260px; position: relative; width: 100%;">
            <canvas id="lineChart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Intern</th><th>Period</th><th>Hard</th><th>Collab</th><th>Ownership</th><th>Sharing</th><th>Exposure</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($evaluations->sortByDesc('dtmPeriod') as $evaluation)
                        <tr>
                            <td>{{ $evaluation->intern->txtInternName ?? '-' }}</td>
                            <td>{{ $evaluation->dtmPeriod?->format('M Y') ?? '-' }}</td>
                            <td>{{ number_format((float) $evaluation->floatHardSkill, 1) }}</td>
                            <td>{{ number_format((float) $evaluation->floatCollaboration, 1) }}</td>
                            <td>{{ number_format((float) $evaluation->floatOwnership, 1) }}</td>
                            <td>{{ number_format((float) $evaluation->floatSharing, 1) }}</td>
                            <td class="score-a">{{ number_format((float) $evaluation->floatExposureScore, 1) }}</td>
                            <td>
                                @if ($isMentor)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('analytics.edit', $evaluation->intEvaluation_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('analytics.destroy', $evaluation->intEvaluation_ID) }}" method="POST" onsubmit="return confirm('Deactivate this evaluation?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="center">No evaluation data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
