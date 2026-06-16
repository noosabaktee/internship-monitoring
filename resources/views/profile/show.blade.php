@extends('layouts.app', [
    'title' => 'Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'PROFILE',
    'pageSubtitle' => 'Detail intern, project, mentor, dan pencapaian.',
    'bodyClass' => 'profile-page',
])

@php
    $latestEvaluation = $intern?->evaluations?->sortByDesc('dtmPeriod')->first();
    $score = round((float) ($latestEvaluation?->floatExposureScore ?? 0));
    $activeProjects = $intern?->projects?->where('bitActive', true) ?? collect();
    $groupedProjects = $activeProjects->groupBy(fn ($assignment) => $assignment->project?->txtProjectType ?: 'Other');
    $mentors = $activeProjects->pluck('mentor')->filter()->unique('intMentor_ID');
    $achievements = $intern?->achievements?->sortByDesc('dtmAwarded')->take(5) ?? collect();
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Profile Intern</h2>
            <p>Ringkasan data, performa, project, mentor, dan achievement.</p>
        </div>
        @if ($intern)
            <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Data</a>
        @endif
    </div>

    @if (! $intern)
        <div class="card" style="text-align:center; padding: 40px;">
            <i class="fa-regular fa-user" style="font-size: 40px; color: var(--text-gray);"></i>
            <h3 style="margin: 15px 0;">Belum ada profile intern</h3>
            <a href="{{ route('interns.create') }}" class="btn-add" style="margin: 0 auto; text-decoration:none;">Tambah Intern</a>
        </div>
    @else
        <div class="profile-page-wrap">
            <section class="profile-hero">
                <div class="profile-identity">
                    <img class="profile-avatar" src="https://ui-avatars.com/api/?name={{ urlencode($intern->txtInternName) }}&background=8CC63F&color=fff&size=160" alt="{{ $intern->txtInternName }}">
                    <div class="profile-name">
                        <h1>{{ $intern->txtInternName }}</h1>
                        <p>{{ $intern->txtBio ?: 'Profil intern belum memiliki bio. Tambahkan ringkasan fokus project dan perkembangan program melalui halaman edit profile.' }}</p>
                        <div class="profile-meta">
                            <span class="profile-pill"><i class="fa-solid fa-id-card"></i> {{ $intern->txtInternNo ?: 'INT-' . str_pad((string) $intern->intIntern_ID, 3, '0', STR_PAD_LEFT) }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-graduation-cap"></i> {{ $intern->txtUniversity ?: '-' }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> {{ $intern->bitActive ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                    </div>
                </div>

                <div class="profile-score-card">
                    <div class="profile-score-ring"><span>{{ $score }}</span></div>
                    <div class="profile-score-copy">
                        <h3>Exposure Score</h3>
                        <p>Nilai exposure terakhir dihitung dari data evaluasi intern. Isi evaluasi akan membuat ringkasan ini lebih akurat.</p>
                    </div>
                </div>
            </section>

            <section class="profile-metrics">
                <div class="profile-metric"><i class="fa-solid fa-diagram-project"></i><strong>{{ $activeProjects->count() }}</strong><span>Total Projects</span></div>
                <div class="profile-metric"><i class="fa-solid fa-user-tie"></i><strong>{{ $mentors->count() }}</strong><span>Mentors</span></div>
                <div class="profile-metric"><i class="fa-solid fa-trophy"></i><strong>{{ $achievements->count() }}</strong><span>Achievements</span></div>
                <div class="profile-metric"><i class="fa-solid fa-arrow-trend-up"></i><strong>{{ $score }}</strong><span>Latest Score</span></div>
            </section>

            <div class="profile-section-grid">
                <section class="profile-card">
                    <div class="profile-card-header">
                        <div class="profile-card-title">
                            <h2>Projects</h2>
                            <p>Daftar project yang dikerjakan, dipisahkan berdasarkan tipe.</p>
                        </div>
                        <span class="status-badge {{ $activeProjects->count() ? 'status-active' : 'status-inactive' }}">{{ $activeProjects->count() ? 'On Track' : 'No Project' }}</span>
                    </div>

                    @forelse ($groupedProjects as $type => $assignments)
                        <div class="project-type-group">
                            <div class="project-type-head">
                                <h3><i class="fa-solid fa-diagram-project"></i> {{ $type }}</h3>
                                <span class="status-badge status-active">{{ $assignments->count() }} Project</span>
                            </div>
                            <ul class="project-list">
                                @foreach ($assignments as $assignment)
                                    <li>
                                        <div>
                                            <h4>{{ $assignment->project->txtProjectName ?? '-' }}</h4>
                                            <p>{{ $assignment->project->txtDescription ?: $assignment->txtStatus }}</p>
                                        </div>
                                        <div class="mini-progress">
                                            <span>{{ number_format((float) $assignment->floatProgress, 0) }}%</span>
                                            <div class="progress-track"><div class="progress-fill" style="width:{{ (float) $assignment->floatProgress }}%"></div></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <div class="card" style="box-shadow:none; margin-bottom:0;">Belum ada project assignment untuk intern ini.</div>
                    @endforelse
                </section>

                <aside>
                    <section class="profile-card">
                        <div class="profile-card-header">
                            <div class="profile-card-title">
                                <h2>Mentors</h2>
                                <p>Pendamping utama selama program.</p>
                            </div>
                        </div>
                        <div class="mentor-list">
                            @forelse ($mentors as $mentor)
                                <div class="mentor-item">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($mentor->txtMentorName) }}&background=006838&color=fff" alt="{{ $mentor->txtMentorName }}">
                                    <div><h4>{{ $mentor->txtMentorName }}</h4><p>{{ $mentor->txtDepartment ?: '-' }} - {{ $mentor->txtRole ?: 'Mentor' }}</p></div>
                                </div>
                            @empty
                                <div class="mentor-item"><div><h4>Belum ada mentor</h4><p>Tambahkan assignment project untuk menghubungkan mentor.</p></div></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="profile-card">
                        <div class="profile-card-header">
                            <div class="profile-card-title">
                                <h2>Score</h2>
                                <p>Breakdown evaluasi terakhir.</p>
                            </div>
                        </div>
                        <div class="score-breakdown">
                            @foreach ([
                                'Hard Skills' => $latestEvaluation?->floatHardSkill ?? 0,
                                'Collaboration' => $latestEvaluation?->floatCollaboration ?? 0,
                                'Ownership' => $latestEvaluation?->floatOwnership ?? 0,
                                'Sharing' => $latestEvaluation?->floatSharing ?? 0,
                            ] as $label => $value)
                                <div class="score-row"><span>{{ $label }}</span><div class="progress-track"><div class="progress-fill" style="width:{{ (float) $value }}%"></div></div><strong>{{ number_format((float) $value, 0) }}</strong></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="profile-card">
                        <div class="profile-card-header">
                            <div class="profile-card-title">
                                <h2>Achievements</h2>
                                <p>Milestone dan pengakuan program.</p>
                            </div>
                        </div>
                        <ul class="achievement-list">
                            @forelse ($achievements as $achievement)
                                <li><div class="achievement-icon"><i class="{{ $achievement->txtIcon ?: 'fa-solid fa-award' }}"></i></div><div><h4>{{ $achievement->txtAchievementTitle }}</h4><p>{{ $achievement->txtDescription }}</p></div></li>
                            @empty
                                <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>Belum ada achievement</h4><p>Achievement akan tampil di sini setelah ditambahkan.</p></div></li>
                            @endforelse
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    @endif
@endsection
