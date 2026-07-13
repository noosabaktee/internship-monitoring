@extends('layouts.app', [
    'title' => 'Mentors - Kalbe Internship Dashboard',
    'pageTitle' => 'MENTORS',
    'pageSubtitle' => 'Manage Kalbe mentor data.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingMentor)
        ? route('mentors.update', $editingMentor->intMentor_ID)
        : route('mentors.store');
    $isMentorUser = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Mentor Data</h2>
            <p>Manage mentors by department and mentoring status.</p>
        </div>
        @if ($isMentorUser)
            <a class="btn btn-primary btn-add" href="{{ route('mentors.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Add Mentor</a>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Gender</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($mentors as $mentor)
                        @php
                            $mentorName = $mentor->txtMentorName ?: 'Mentor';
                            $mentorPhotoUrl = $mentor->user?->txtProfilePhoto
                                ? asset('storage/' . $mentor->user->txtProfilePhoto)
                                : 'https://ui-avatars.com/api/?name=' . urlencode($mentorName) . '&background=006838&color=fff&bold=true';
                        @endphp
                        <tr>
                            <td>MTR-{{ str_pad((string) $mentor->intMentor_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <a class="table-person table-person-link" href="{{ route('profile.mentor.show', $mentor->intMentor_ID) }}">
                                    <img class="table-person-avatar" src="{{ $mentorPhotoUrl }}" alt="{{ $mentorName }}" loading="lazy">
                                    <span class="table-person-name">{{ $mentor->txtMentorName ?: '-' }}</span>
                                </a>
                            </td>
                            <td>{{ $mentor->txtMentorGender ?: '-' }}</td>
                            <td>{{ $mentor->user->txtEmail ?? '-' }}</td>
                            <td>{{ $mentor->txtDepartment ?: '-' }}</td>
                            <td>{{ $mentor->txtRole ?: '-' }}</td>
                            <td><span class="status-badge {{ $mentor->bitActive ? 'status-active' : 'status-inactive' }}">{{ $mentor->bitActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                @if ($isMentorUser)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('mentors.edit', $mentor->intMentor_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <button
                                            class="btn-icon btn-delete"
                                            type="button"
                                            data-delete-modal-trigger
                                            data-delete-action="{{ route('mentors.destroy', $mentor->intMentor_ID) }}"
                                            data-delete-title="Deactivate Mentor?"
                                            data-delete-message="{{ $mentor->txtMentorName ?: 'This mentor' }} will be marked inactive along with the linked user account."
                                            data-delete-submit="Deactivate"
                                        ><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @else
                                    <a class="auth-link" href="{{ route('profile.mentor.show', $mentor->intMentor_ID) }}">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="center">No mentor data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@if ($isFormOpen)
    @push('modals')
        <x-crud-modal
            id="mentorFormModal"
            :active="$isFormOpen"
            :title="isset($editingMentor) ? 'Edit Mentor' : 'Add New Mentor'"
            subtitle="Email and password are stored in mUser. Mentor profiles are stored in mMentor."
            :close-url="route('mentors.index')"
            size="lg"
        >
            <form id="mentorForm" class="row g-3" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingMentor)
                    @method('PUT')
                @endisset

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingMentor->bitActive ?? true)) === '1')>Active</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingMentor->bitActive ?? true)) === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" name="txtMentorName" value="{{ old('txtMentorName', $editingMentor->txtMentorName ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-control" name="txtMentorGender">
                        <option value="">Select gender</option>
                        @foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)
                            <option value="{{ $genderValue }}" @selected(old('txtMentorGender', $editingMentor->txtMentorGender ?? '') === $genderValue)>{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $editingMentor->user->txtEmail ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password {{ isset($editingMentor) ? '(leave blank to keep current)' : '' }}</label>
                    <input class="form-control" type="password" name="txtPassword" placeholder="{{ isset($editingMentor) ? 'New password' : 'Default: password' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="txtDepartment" value="{{ old('txtDepartment', $editingMentor->txtDepartment ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role / Position</label>
                    <input class="form-control" name="txtRole" value="{{ old('txtRole', $editingMentor->txtRole ?? 'Mentor') }}">
                </div>
            </form>

            <x-slot:footer>
                <a href="{{ route('mentors.index') }}" class="btn-cancel" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit" form="mentorForm">{{ isset($editingMentor) ? 'Save Changes' : 'Save Mentor' }}</button>
            </x-slot:footer>
        </x-crud-modal>
    @endpush
@endif
