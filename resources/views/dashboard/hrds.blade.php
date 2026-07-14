@extends('layouts.app', [
    'title' => 'HRD & Headmaster - Kalbe Internship Dashboard',
    'pageTitle' => 'HRD & HEADMASTER',
    'pageSubtitle' => 'Manage HRD and headmaster account data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingHrd)
        ? route('hrds.update', $editingHrd->intAdminProfile_ID)
        : route('hrds.store');
    $authUser = \App\Models\MUser::with('adminProfile')->find(session('auth_user_id'));
    $canManageHrds = $authUser && \App\Support\RoleAccess::can($authUser, 'hrd-data');
    $roleOptions = [
        \App\Support\RoleAccess::ROLE_MENTOR => 'Mentor',
        \App\Support\RoleAccess::ROLE_HRD => 'HRD',
        \App\Support\RoleAccess::ROLE_HEADMASTER => 'Headmaster',
    ];
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>HRD & Headmaster Data</h2>
            <p>Manage HRD and headmaster profiles, roles, and account status.</p>
        </div>
        @if ($canManageHrds)
            <a class="btn btn-primary btn-add" href="{{ route('hrds.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add HRD / Headmaster</a>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Gender</th><th>Email</th><th>Role</th><th>Department</th><th>Phone</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($hrds as $hrd)
                        @php
                            $hrdName = $hrd->txtAdminProfileName ?: 'HRD';
                            $hrdPhotoUrl = $hrd->user?->txtProfilePhoto
                                ? asset('storage/' . $hrd->user->txtProfilePhoto)
                                : 'https://ui-avatars.com/api/?name=' . urlencode($hrdName) . '&background=006838&color=fff&bold=true';
                        @endphp
                        <tr>
                            <td>HRD-{{ str_pad((string) $hrd->intAdminProfile_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div class="table-person">
                                    <img class="table-person-avatar" src="{{ $hrdPhotoUrl }}" alt="{{ $hrdName }}" loading="lazy">
                                    <span class="table-person-name">{{ $hrd->txtAdminProfileName ?: '-' }}</span>
                                </div>
                            </td>
                            <td>{{ $hrd->txtAdminProfileGender ?: '-' }}</td>
                            <td>{{ $hrd->user->txtEmail ?? '-' }}</td>
                            <td>{{ $hrd->user?->txtRole ?? ($hrd->txtAdminProfilePosition ?: '-') }}</td>
                            <td>{{ $hrd->txtAdminProfileDepartment ?: '-' }}</td>
                            <td>{{ $hrd->txtAdminProfilePhone ?: '-' }}</td>
                            <td><span class="status-badge {{ ($hrd->bitActive && ($hrd->user?->bitActive ?? false)) ? 'status-active' : 'status-inactive' }}">{{ ($hrd->bitActive && ($hrd->user?->bitActive ?? false)) ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($canManageHrds)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('hrds.edit', $hrd->intAdminProfile_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <button
                                            class="btn-icon btn-delete"
                                            type="button"
                                            data-delete-modal-trigger
                                            data-delete-action="{{ route('hrds.destroy', $hrd->intAdminProfile_ID) }}"
                                            data-delete-title="Deactivate HRD?"
                                            data-delete-message="{{ $hrd->txtAdminProfileName ?: 'This HRD' }} will be marked inactive along with the linked user account."
                                            data-delete-submit="Deactivate"
                                        ><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @else
                                    <span class="auth-link">View</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="center">No HRD or headmaster data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="hrdFormModal"
            :active="$isFormOpen"
            :title="isset($editingHrd) ? 'Edit HRD / Headmaster' : 'Add New HRD / Headmaster'"
            subtitle="Isi data akun, kontak administrasi, dan role pengguna."
            :close-url="route('hrds.index')"
            size="lg"
        >
            <form id="hrdForm" class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingHrd)
                    @method('PUT')
                @endisset

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingHrd->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingHrd->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-control" name="txtRole" required>
                        @foreach ($roleOptions as $roleValue => $roleLabel)
                            <option value="{{ $roleValue }}" @selected(old('txtRole', $editingHrd->user->txtRole ?? \App\Support\RoleAccess::ROLE_HRD) === $roleValue)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" name="txtAdminProfileName" value="{{ old('txtAdminProfileName', $editingHrd->txtAdminProfileName ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-control" name="txtAdminProfileGender">
                        <option value="">Select gender</option>
                        @foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)
                            <option value="{{ $genderValue }}" @selected(old('txtAdminProfileGender', $editingHrd->txtAdminProfileGender ?? '') === $genderValue)>{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $editingHrd->user->txtEmail ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password {{ isset($editingHrd) ? '(leave blank to keep current)' : '' }}</label>
                    <input class="form-control" type="password" name="txtPassword" placeholder="{{ isset($editingHrd) ? 'New password' : 'Default: password' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="txtAdminProfileDepartment" value="{{ old('txtAdminProfileDepartment', $editingHrd->txtAdminProfileDepartment ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="txtAdminProfilePhone" value="{{ old('txtAdminProfilePhone', $editingHrd->txtAdminProfilePhone ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Short Bio</label>
                    <textarea class="form-control" name="txtAdminProfileBio" rows="3">{{ old('txtAdminProfileBio', $editingHrd->txtAdminProfileBio ?? '') }}</textarea>
                </div>
            </form>

            <x-slot:footer>
                <a href="{{ route('hrds.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="hrdForm">{{ isset($editingHrd) ? 'Save Changes' : 'Save Data' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
