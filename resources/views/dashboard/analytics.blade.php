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

<!-- 
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-title" style="margin-bottom: 10px;">EXPOSURE PROGRESSION</div>
        <div style="height: 260px; position: relative; width: 100%;">
            <canvas id="lineChart"></canvas>
        </div>
    </div> -->

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
                                        <button
                                            class="btn-icon btn-delete"
                                            type="button"
                                            data-delete-modal-trigger
                                            data-delete-action="{{ route('analytics.destroy', $evaluation->intEvaluation_ID) }}"
                                            data-delete-title="Deactivate Evaluation?"
                                            data-delete-message="Evaluation for {{ $evaluation->intern->txtInternName ?? 'this intern' }} in {{ $evaluation->dtmPeriod?->format('M Y') ?? 'this period' }} will be marked inactive."
                                            data-delete-submit="Deactivate"
                                        ><i class="fa-solid fa-trash"></i></button>
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

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="evaluationFormModal"
            :active="$isFormOpen"
            :title="isset($editingEvaluation) ? 'Edit Evaluation' : 'Add Evaluation'"
            subtitle="Exposure score is calculated automatically from the average of four evaluation scores."
            :close-url="route('analytics.index')"
            size="lg"
        >
            <form id="evaluationForm" class="row g-3" action="{{ $formAction }}" method="POST">
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
            </form>

            <x-slot:footer>
                <a href="{{ route('analytics.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="evaluationForm">{{ isset($editingEvaluation) ? 'Save Changes' : 'Save Evaluation' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
