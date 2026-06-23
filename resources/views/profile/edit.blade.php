@extends('layouts.app', [
    'title' => 'Edit Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'EDIT PROFILE',
    'pageSubtitle' => 'Update your account data and profile information.',
    'bodyClass' => 'profile-page',
])

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Edit Profile Data</h2>
            <p>Update the main information for the signed-in account.</p>
        </div>
        <a href="{{ route('profile.show') }}" class="btn-outline"><i class="fa-solid fa-arrow-left"></i> Back to Profile</a>
    </div>

    @if (! $intern && ! $mentor)
        <div class="card" style="text-align:center; padding: 40px;">
            <i class="fa-regular fa-user" style="font-size: 40px; color: var(--text-gray);"></i>
            <h3 style="margin: 15px 0;">No profile is available to edit</h3>
        </div>
    @else
        <form action="{{ route('profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <section class="profile-card">
                <div class="profile-card-header">
                    <div class="profile-card-title">
                        <h2>{{ $intern ? 'Intern Information' : 'Mentor Information' }}</h2>
                        <p>This data appears on the profile page and dashboard.</p>
                    </div>
                    <span class="status-badge {{ ($intern?->bitActive ?? $mentor?->bitActive) ? 'status-active' : 'status-inactive' }}">{{ ($intern?->bitActive ?? $mentor?->bitActive) ? 'Active' : 'Inactive' }}</span>
                </div>

                @if ($intern)
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">ID</label><input class="form-control" name="txtInternNo" value="{{ old('txtInternNo', $intern->txtInternNo) }}"></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select class="form-control" name="bitActive"><option value="1" @selected(old('bitActive', (int) $intern->bitActive) == 1)>Active</option><option value="0" @selected(old('bitActive', (int) $intern->bitActive) == 0)>Inactive</option></select></div>
                        <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="txtInternName" value="{{ old('txtInternName', $intern->txtInternName) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $intern->user->txtEmail ?? '') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select class="form-control" name="txtInternGender"><option value="">Select gender</option>@foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)<option value="{{ $genderValue }}" @selected(old('txtInternGender', $intern->txtInternGender) === $genderValue)>{{ $genderLabel }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="txtPassword" placeholder="Leave blank to keep current"></div>
                        <div class="col-md-6"><label class="form-label">University</label><input class="form-control" name="txtUniversity" value="{{ old('txtUniversity', $intern->txtUniversity) }}"></div>
                        <div class="col-md-6"><label class="form-label">Dept</label><input class="form-control" name="txtDept" value="{{ old('txtDept', $intern->txtDept) }}"></div>
                        <div class="col-md-6"><label class="form-label">Join Date</label><input class="form-control" type="date" name="dtmInserted" value="{{ old('dtmInserted', $intern->dtmInserted?->format('Y-m-d')) }}"></div>
                        <div class="col-md-6"><label class="form-label">End Date</label><input class="form-control" type="date" name="dtmEndDate" value="{{ old('dtmEndDate', $intern->dtmEndDate?->format('Y-m-d')) }}"></div>
                        <div class="col-12"><label class="form-label">Short Bio</label><textarea class="form-control" name="txtBio" rows="4">{{ old('txtBio', $intern->txtBio) }}</textarea></div>
                    </div>
                @endif

                @if ($mentor)
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Status</label><select class="form-control" name="bitActive"><option value="1" @selected(old('bitActive', (int) $mentor->bitActive) == 1)>Active</option><option value="0" @selected(old('bitActive', (int) $mentor->bitActive) == 0)>Inactive</option></select></div>
                        <div class="col-md-8"><label class="form-label">Full Name</label><input class="form-control" name="txtMentorName" value="{{ old('txtMentorName', $mentor->txtMentorName) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $mentor->user->txtEmail ?? '') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select class="form-control" name="txtMentorGender"><option value="">Select gender</option>@foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)<option value="{{ $genderValue }}" @selected(old('txtMentorGender', $mentor->txtMentorGender) === $genderValue)>{{ $genderLabel }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="txtPassword" placeholder="Leave blank to keep current"></div>
                        <div class="col-md-6"><label class="form-label">Department</label><input class="form-control" name="txtDepartment" value="{{ old('txtDepartment', $mentor->txtDepartment) }}"></div>
                        <div class="col-md-6"><label class="form-label">Role / Position</label><input class="form-control" name="txtRole" value="{{ old('txtRole', $mentor->txtRole) }}"></div>
                    </div>
                @endif
            </section>

            <div class="modal-footer mt-4" style="border: 1px solid var(--border); border-radius: var(--radius);">
                <a href="{{ route('profile.show') }}" class="btn btn-light btn-cancel" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary btn-save">Save Changes</button>
            </div>
        </form>
    @endif
@endsection
