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
    $authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
    $canManageSkillSets = $authUser && \App\Support\RoleAccess::can($authUser, 'master-data');
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Skill Set</h2>
            <p>Master data for project skill categories.</p>
        </div>
        @if ($canManageSkillSets)
            <a class="btn btn-primary btn-add" href="{{ route('skill-sets.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Skill Set</a>
        @endif
    </div>

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
                                @if ($canManageSkillSets)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('skill-sets.edit', $skillSet->intSkillSet_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <button
                                            class="btn-icon btn-delete"
                                            type="button"
                                            data-delete-modal-trigger
                                            data-delete-action="{{ route('skill-sets.destroy', $skillSet->intSkillSet_ID) }}"
                                            data-delete-title="Deactivate Skill Set?"
                                            data-delete-message="Skill set {{ $skillSet->txtSkillSetName }} will be marked inactive and hidden from active project forms."
                                            data-delete-submit="Deactivate"
                                        ><i class="fa-solid fa-trash"></i></button>
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

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="skillSetFormModal"
            :active="$isFormOpen"
            :title="isset($editingSkillSet) ? 'Edit Skill Set' : 'Add Skill Set'"
            subtitle="Isi kategori skill yang akan digunakan pada form project."
            :close-url="route('skill-sets.index')"
            size="md"
        >
            <form id="skillSetForm" class="row g-3" action="{{ $formAction }}" method="POST">
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
            </form>

            <x-slot:footer>
                <a href="{{ route('skill-sets.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="skillSetForm">{{ isset($editingSkillSet) ? 'Save Changes' : 'Save Skill Set' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
