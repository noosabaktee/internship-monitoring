@extends('layouts.app', [
    'title' => 'Mentor Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'MENTOR PROFILE',
    'pageSubtitle' => 'Detail mentor, department, dan project assignment.',
    'bodyClass' => 'profile-page',
])

@php
    $assignments = $mentor->internProjects->where('bitActive', true);
    $interns = $assignments->pluck('intern')->filter()->unique('intIntern_ID');
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Profile Mentor</h2>
            <p>Ringkasan data mentor dan project assignment.</p>
        </div>
        @if (session('auth_user_id') === $mentor->intUser_ID)
            <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Data</a>
        @endif
    </div>

    <div class="profile-page-wrap">
        <section class="profile-hero">
            <div class="profile-identity">
                <img class="profile-avatar" src="https://ui-avatars.com/api/?name={{ urlencode($mentor->txtMentorName) }}&background=006838&color=fff&size=160" alt="{{ $mentor->txtMentorName }}">
                <div class="profile-name">
                    <h1>{{ $mentor->txtMentorName }}</h1>
                    <p>{{ $mentor->txtDepartment ?: 'Department mentor belum diisi.' }}</p>
                    <div class="profile-meta">
                        <span class="profile-pill"><i class="fa-solid fa-id-card"></i> MTR-{{ str_pad((string) $mentor->intMentor_ID, 3, '0', STR_PAD_LEFT) }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-user-tie"></i> {{ $mentor->txtRole ?: 'Mentor' }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> {{ $mentor->bitActive ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-score-card">
                <div class="profile-score-ring"><span>{{ $assignments->count() }}</span></div>
                <div class="profile-score-copy">
                    <h3>Active Assignments</h3>
                    <p>Total project assignment aktif yang sedang didampingi mentor ini.</p>
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
                    <p>Project dan intern yang sedang didampingi.</p>
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
                                <td><span class="status-badge status-active">{{ $assignment->txtStatus ?: 'Aktif' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="center">Belum ada assignment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
