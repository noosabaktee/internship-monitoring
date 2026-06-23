@extends('layouts.app', [
    'title' => 'Mentor Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'MENTOR PROFILE',
    'pageSubtitle' => 'Mentor, department, and project assignment details.',
    'bodyClass' => 'profile-page',
])

@php
    $assignments = $mentor->internProjects->where('bitActive', true);
    $interns = $assignments->pluck('intern')->filter()->unique('intIntern_ID');
    $profilePhotoUrl = $mentor->user?->txtProfilePhoto
        ? asset('storage/' . $mentor->user->txtProfilePhoto)
        : 'https://ui-avatars.com/api/?name=' . urlencode($mentor->txtMentorName) . '&background=006838&color=fff&size=160';
    $canUpdatePhoto = session('auth_user_id') === $mentor->intUser_ID;
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Mentor Profile</h2>
            <p>Summary of mentor data and project assignments.</p>
        </div>
        @if (session('auth_user_id') === $mentor->intUser_ID)
            <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Data</a>
        @endif
    </div>

    <div class="profile-page-wrap">
        <section class="profile-hero">
            <div class="profile-identity">
                <div class="profile-avatar-wrap">
                    <img class="profile-avatar" src="{{ $profilePhotoUrl }}" alt="{{ $mentor->txtMentorName }}">
                    @if ($canUpdatePhoto)
                        <form class="profile-photo-form" action="{{ route('profile.photo.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <label class="profile-photo-button" title="Upload profile photo">
                                <i class="fa-solid fa-camera"></i>
                                <input type="file" name="txtProfilePhoto" accept="image/*" onchange="this.form.submit()">
                            </label>
                        </form>
                    @endif
                </div>
                <div class="profile-name">
                    <h1>{{ $mentor->txtMentorName }}</h1>
                    <p>{{ $mentor->txtDepartment ?: 'Mentor department has not been filled in.' }}</p>
                    <div class="profile-meta">
                        <span class="profile-pill"><i class="fa-solid fa-id-card"></i> MTR-{{ str_pad((string) $mentor->intMentor_ID, 3, '0', STR_PAD_LEFT) }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-venus-mars"></i> {{ $mentor->txtMentorGender ?: '-' }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-user-tie"></i> {{ $mentor->txtRole ?: 'Mentor' }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> {{ $mentor->bitActive ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-score-card">
                <div class="profile-score-ring"><span>{{ $assignments->count() }}</span></div>
                <div class="profile-score-copy">
                    <h3>Active Assignments</h3>
                    <p>Total active project assignments currently guided by this mentor.</p>
                </div>
            </div>
        </section>

        <section class="profile-metrics">
            <div class="profile-metric"><i class="fa-solid fa-diagram-project"></i><strong>{{ $assignments->count() }}</strong><span>Projects</span></div>
            <div class="profile-metric"><i class="fa-solid fa-users"></i><strong>{{ $interns->count() }}</strong><span>Interns</span></div>
            <div class="profile-metric"><i class="fa-solid fa-building"></i><strong>{{ $mentor->txtDepartment ?: '-' }}</strong><span>Department</span></div>
            <div class="profile-metric"><i class="fa-solid fa-user-tie"></i><strong>{{ $mentor->txtRole ?: 'Mentor' }}</strong><span>Role</span></div>
        </section>

        <section class="profile-card" style="margin-top: 20px;">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Project Assignments</h2>
                    <p>Projects and interns currently being guided.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Project</th><th>Type</th><th>Intern</th><th>Progress</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->project->txtProjectName ?? '-' }}</td>
                                <td>{{ $assignment->project->txtProjectType ?? '-' }}</td>
                                <td>{{ $assignment->intern->txtInternName ?? '-' }}</td>
                                <td>{{ number_format((float) $assignment->floatProgress, 0) }}%</td>
                                <td><span class="status-badge status-active">{{ $assignment->txtStatus ?: 'Active' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="center">No assignments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
