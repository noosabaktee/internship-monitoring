@extends('layouts.app', [
    'title' => 'Skill Set - Kalbe Internship Dashboard',
    'pageTitle' => 'SKILL SET',
    'pageSubtitle' => 'Manage project skill set master data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingSkillSet)
        ? route('skill-sets.update', $editingSkillSet->intSkillSet_ID)
        : route('skill-sets.store');
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Skill Set</h2>
            <p>Master data for project skill categories.</p>
        </div>
        @if ($isMentor)
            <a class="btn btn-primary btn-add" href="{{ route('skill-sets.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Skill Set</a>
        @endif
    </div>

    @if ($isFormOpen)
        <section class="profile-card mb-4">
            <div class="profile-card-header d-flex align-items-start justify-content-between gap-3">
                <div class="profile-card-title">
                    <h2>{{ isset($editingSkillSet) ? 'Edit Skill Set' : 'Add Skill Set' }}</h2>
                    <p>Skill set data is stored in mSkillSet and used by project forms.</p>
                </div>
                <a href="{{ route('skill-sets.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-xmark"></i> Close</a>
            </div>

            <form class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingSkillSet)
                    @method('PUT')
                @endisset

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingSkillSet->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingSkillSet->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="txtSkillSetName" value="{{ old('txtSkillSetName', $editingSkillSet->txtSkillSetName ?? '') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="txtSkillSetDescription" rows="3">{{ old('txtSkillSetDescription', $editingSkillSet->txtSkillSetDescription ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-save" type="submit">{{ isset($editingSkillSet) ? 'Save Changes' : 'Save Skill Set' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>Description</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($skillSets as $skillSet)
                        <tr>
                            <td><strong>{{ $skillSet->txtSkillSetName }}</strong></td>
                            <td>{{ $skillSet->txtSkillSetDescription ?: '-' }}</td>
                            <td><span class="status-badge {{ $skillSet->bitActive ? 'status-active' : 'status-inactive' }}">{{ $skillSet->bitActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($isMentor)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('skill-sets.edit', $skillSet->intSkillSet_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('skill-sets.destroy', $skillSet->intSkillSet_ID) }}" method="POST" onsubmit="return confirm('Deactivate this skill set?')">
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
                        <tr><td colspan="4" class="center">No skill set data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
