@extends('layouts.app', [
    'title' => 'Project Handle - Kalbe Internship Dashboard',
    'pageTitle' => 'PROJECT HANDLE',
    'pageSubtitle' => 'Manage intern project capacity and project type weights.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingProjectHandle)
        ? route('project-handles.update', $editingProjectHandle->intProjectHandle_ID)
        : route('project-handles.store');
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Project Handle</h2>
            <p>Capacity by internship duration and score weights by project type.</p>
        </div>
        @if ($isMentor)
            <a class="btn btn-primary btn-add" href="{{ route('project-handles.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Capacity</a>
        @endif
    </div>

   

    @if ($isFormOpen)
        <section class="profile-card mb-4">
            <div class="profile-card-header d-flex align-items-start justify-content-between gap-3">
                <div class="profile-card-title">
                    <h2>{{ isset($editingProjectHandle) ? 'Edit Capacity' : 'Add Capacity' }}</h2>
                    <p>Capacity data is stored in mProjectHandle.</p>
                </div>
                <a href="{{ route('project-handles.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>

            <form class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingProjectHandle)
                    @method('PUT')
                @endisset

                <div class="col-md-4">
                    <label class="form-label">Duration</label>
                    <input class="form-control" name="txtProjectHandleDuration" value="{{ old('txtProjectHandleDuration', $editingProjectHandle->txtProjectHandleDuration ?? '') }}" placeholder="Example: 3 months" required>
                </div>
                <div class="col-md-2"><label class="form-label">Main</label><input class="form-control" type="number" min="0" name="intProjectHandleMain" value="{{ old('intProjectHandleMain', $editingProjectHandle->intProjectHandleMain ?? 0) }}" required></div>
                <div class="col-md-2"><label class="form-label">Collaboration</label><input class="form-control" type="number" min="0" name="intProjectHandleCollaboration" value="{{ old('intProjectHandleCollaboration', $editingProjectHandle->intProjectHandleCollaboration ?? 0) }}" required></div>
                <div class="col-md-2"><label class="form-label">Satellite</label><input class="form-control" type="number" min="0" name="intProjectHandleSatellite" value="{{ old('intProjectHandleSatellite', $editingProjectHandle->intProjectHandleSatellite ?? 0) }}" required></div>
                <div class="col-md-2"><label class="form-label">Sharing</label><input class="form-control" type="number" min="0" name="intProjectHandleSharing" value="{{ old('intProjectHandleSharing', $editingProjectHandle->intProjectHandleSharing ?? 0) }}" required></div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingProjectHandle->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingProjectHandle->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-save" type="submit">{{ isset($editingProjectHandle) ? 'Save Changes' : 'Save Capacity' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Duration</th><th>Main</th><th>Collaboration</th><th>Satellite</th><th>Sharing</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($projectHandles as $projectHandle)
                        <tr>
                            <td><strong>{{ $projectHandle->txtProjectHandleDuration }}</strong></td>
                            <td>{{ $projectHandle->intProjectHandleMain }}</td>
                            <td>{{ $projectHandle->intProjectHandleCollaboration }}</td>
                            <td>{{ $projectHandle->intProjectHandleSatellite }}</td>
                            <td>{{ $projectHandle->intProjectHandleSharing }}</td>
                            <td><span class="status-badge {{ $projectHandle->bitActive ? 'status-active' : 'status-inactive' }}">{{ $projectHandle->bitActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($isMentor)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('project-handles.edit', $projectHandle->intProjectHandle_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('project-handles.destroy', $projectHandle->intProjectHandle_ID) }}" method="POST" onsubmit="return confirm('Deactivate this capacity?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <span class="auth-link">View</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="center">No project handle data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

     <section class="profile-card mb-4">
        <div class="profile-card-header">
            <div class="profile-card-title">
                <h2>Project Weight</h2>
                <p>These weights are used to calculate intern leaderboard scores.</p>
            </div>
        </div>
        <form class="row g-3" action="{{ route('project-handles.weights.update') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="col-md-3"><label class="form-label">Main</label><input class="form-control" type="number" min="0" name="intProjectWeightMain" value="{{ old('intProjectWeightMain', $projectWeight->intProjectWeightMain) }}" @disabled(! $isMentor) required></div>
            <div class="col-md-3"><label class="form-label">Collaboration</label><input class="form-control" type="number" min="0" name="intProjectWeightCollaboration" value="{{ old('intProjectWeightCollaboration', $projectWeight->intProjectWeightCollaboration) }}" @disabled(! $isMentor) required></div>
            <div class="col-md-3"><label class="form-label">Satellite</label><input class="form-control" type="number" min="0" name="intProjectWeightSatellite" value="{{ old('intProjectWeightSatellite', $projectWeight->intProjectWeightSatellite) }}" @disabled(! $isMentor) required></div>
            <div class="col-md-3"><label class="form-label">Sharing</label><input class="form-control" type="number" min="0" name="intProjectWeightSharing" value="{{ old('intProjectWeightSharing', $projectWeight->intProjectWeightSharing) }}" @disabled(! $isMentor) required></div>
            @if ($isMentor)
                <div class="col-12"><button class="btn btn-primary btn-save" type="submit">Save Weight</button></div>
            @endif
        </form>
    </section>
@endsection
