@extends('layouts.app', [
    'title' => 'Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'PROFILE',
    'pageSubtitle' => 'Intern, project, mentor, and achievement details.',
    'bodyClass' => 'profile-page',
])

@php
    $latestEvaluation = $intern?->evaluations?->sortByDesc('dtmEvaluationCompleted')->first();
    $score = round((float) ($latestEvaluation?->floatExposureScore ?? 0));
    $activeProjects = $intern?->projects?->where('bitActive', true) ?? collect();
    $groupedProjects = $activeProjects->groupBy(fn ($assignment) => $assignment->project?->txtProjectType ?: 'Other');
    $mentors = $activeProjects
        ->flatMap(function ($assignment) {
            $projectMentors = $assignment->project?->projectMentors ?? collect();

            return $projectMentors->where('bitActive', true)->pluck('mentor');
        })
        ->filter()
        ->unique('intMentor_ID')
        ->values();
    if ($mentors->isEmpty()) {
        $mentors = $activeProjects->pluck('mentor')->filter()->unique('intMentor_ID')->values();
    }
    $achievements = $intern?->achievements?->sortByDesc('dtmAwarded')->take(5) ?? collect();
    $profilePhotoUrl = $intern?->user?->txtProfilePhoto
        ? asset('storage/' . $intern->user->txtProfilePhoto)
        : 'https://ui-avatars.com/api/?name=' . urlencode($intern?->txtInternName ?? 'Intern') . '&background=8CC63F&color=fff&size=160';
    $canUpdatePhoto = $intern && session('auth_user_id') === $intern?->user?->intUser_ID;
    $faceEnrollment = $intern?->user?->faceEnrollment?->bitActive ? $intern->user->faceEnrollment : null;
    $isLegacyFaceEnrollment = (bool) $faceEnrollment && ! str_starts_with((string) $faceEnrollment->txtFaceEnrollmentAlgorithm, 'insightface');
    $hasFaceEnrollment = (bool) $faceEnrollment && ! $isLegacyFaceEnrollment;
    $canManageFaceId = $canUpdatePhoto && ($intern?->user?->txtRole ?? 'Intern') === 'Intern';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Intern Profile</h2>
            <p>Summary of data, performance, projects, mentors, and achievements.</p>
        </div>
        @if ($intern)
            <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Data</a>
        @endif
    </div>

    @if (! $intern)
        <div class="card" style="text-align:center; padding: 40px;">
            <i class="fa-regular fa-user" style="font-size: 40px; color: var(--text-gray);"></i>
            <h3 style="margin: 15px 0;">No intern profile yet</h3>
            <a href="{{ route('interns.create') }}" class="btn-add" style="margin: 0 auto; text-decoration:none;">Add Intern</a>
        </div>
    @else
        <div class="profile-page-wrap">
            <section class="profile-hero">
                <div class="profile-identity">
                    <div class="profile-avatar-wrap">
                        <img class="profile-avatar" src="{{ $profilePhotoUrl }}" alt="{{ $intern->txtInternName }}">
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
                        <h1>{{ $intern->txtInternName }}</h1>
                        <p>{{ $intern->txtBio ?: 'This intern profile does not have a bio yet. Add a short summary of project focus and program progress from the edit profile page.' }}</p>
                        <div class="profile-meta">
                            <span class="profile-pill"><i class="fa-solid fa-id-card"></i> {{ $intern->txtInternNo ?: 'INT-' . str_pad((string) $intern->intIntern_ID, 3, '0', STR_PAD_LEFT) }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-venus-mars"></i> {{ $intern->txtInternGender ?: '-' }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-graduation-cap"></i> {{ $intern->txtUniversity ?: '-' }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-building"></i> {{ $intern->txtDept ?: '-' }}</span>
                            <span class="profile-pill"><i class="fa-regular fa-calendar"></i> {{ $intern->dtmInserted?->format('d M Y') ?? '-' }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-calendar-check"></i> {{ $intern->dtmEndDate?->format('d M Y') ?? '-' }}</span>
                            <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> {{ $intern->bitActive ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>
                </div>

                <div class="profile-score-card">
                    <div class="profile-score-ring"><span>{{ $score }}</span></div>
                    <div class="profile-score-copy">
                        <h3>Exposure Score</h3>
                        <p>The latest exposure score is calculated from intern evaluation data. Completing evaluations makes this summary more accurate.</p>
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
                            <p>Projects currently handled, grouped by type.</p>
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
                        <div class="card" style="box-shadow:none; margin-bottom:0;">No project assignment for this intern yet.</div>
                    @endforelse
                </section>

                <aside>
                    @if ($canManageFaceId)
                        <section
                            class="profile-card profile-face-card"
                            data-attendance-page
                            data-attendance-mode="python"
                            data-face-detection-url="{{ route('face.detection.store') }}"
                        >
                            <div class="profile-card-header">
                                <div class="profile-card-title">
                                    <h2>Face ID Absensi</h2>
                                    <p>{{ $isLegacyFaceEnrollment ? 'Face ID perlu diperbarui.' : ($hasFaceEnrollment ? 'Face ID sudah aktif.' : 'Face ID belum aktif.') }}</p>
                                </div>
                                <span class="status-badge {{ $hasFaceEnrollment ? 'status-active' : 'status-inactive' }}">{{ $hasFaceEnrollment ? 'Aktif' : 'Belum' }}</span>
                            </div>

                            <div class="attendance-camera-frame profile-face-camera">
                                <video data-attendance-video autoplay playsinline muted></video>
                                <canvas data-attendance-canvas hidden></canvas>
                                <div class="attendance-camera-overlay">
                                    <div class="attendance-scan-ring"></div>
                                </div>
                            </div>

                            <div class="attendance-status-line" data-attendance-message>
                                <i class="fa-solid fa-circle-info"></i>
                                <span>Siap memulai kamera.</span>
                            </div>

                            <div class="attendance-progress" aria-hidden="true">
                                <span data-attendance-progress></span>
                            </div>

                            <div class="profile-face-actions">
                                <button class="btn btn-outline-primary attendance-action-button" type="button" data-attendance-camera title="Aktifkan kamera">
                                    <i class="fa-solid fa-camera"></i>
                                    <span>Kamera</span>
                                </button>

                                <form action="{{ route('profile.face-enrollment.store') }}" method="POST" data-face-enrollment-form>
                                    @csrf
                                    <input type="hidden" name="txtFaceEnrollmentImages" data-face-enrollment-images>
                                    <input type="hidden" name="intFaceEnrollmentSampleCount" data-face-enrollment-sample-count value="3">
                                    <input type="hidden" name="floatFaceEnrollmentQuality" data-face-enrollment-quality>
                                    <button class="btn btn-primary attendance-action-button" type="button" data-face-enroll>
                                        <i class="fa-solid fa-user-plus"></i>
                                        <span>{{ $hasFaceEnrollment ? 'Perbarui Face ID' : 'Daftar Face ID' }}</span>
                                    </button>
                                </form>

                                @if ($faceEnrollment)
                                    <button
                                        class="btn btn-outline-danger attendance-action-button"
                                        type="button"
                                        data-delete-modal-trigger
                                        data-delete-action="{{ route('profile.face-enrollment.destroy') }}"
                                        data-delete-title="Reset Face ID?"
                                        data-delete-message="Face ID absensi yang tersimpan akan dihapus. Kamu bisa mendaftarkan ulang setelah reset."
                                        data-delete-submit="Reset"
                                    >
                                        <i class="fa-solid fa-rotate-left"></i>
                                        <span>Reset</span>
                                    </button>
                                @endif
                            </div>
                        </section>
                    @endif

                    <section class="profile-card">
                        <div class="profile-card-header">
                            <div class="profile-card-title">
                                <h2>Mentors</h2>
                                <p>Main mentors during the program.</p>
                            </div>
                        </div>
                        <div class="mentor-list">
                            @forelse ($mentors as $mentor)
                                <div class="mentor-item">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($mentor->txtMentorName) }}&background=006838&color=fff" alt="{{ $mentor->txtMentorName }}">
                                    <div><h4>{{ $mentor->txtMentorName }}</h4><p>{{ $mentor->txtDepartment ?: '-' }} - {{ $mentor->txtRole ?: 'Mentor' }}</p></div>
                                </div>
                            @empty
                                <div class="mentor-item"><div><h4>No mentor yet</h4><p>Add a project assignment to connect a mentor.</p></div></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="profile-card">
                        <div class="profile-card-header">
                            <div class="profile-card-title">
                                <h2>Score</h2>
                                <p>Latest evaluation breakdown.</p>
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
                                <p>Program milestones and recognition.</p>
                            </div>
                        </div>
                        <ul class="achievement-list">
                            @forelse ($achievements as $achievement)
                                <li><div class="achievement-icon"><i class="{{ $achievement->txtIcon ?: 'fa-solid fa-award' }}"></i></div><div><h4>{{ $achievement->txtAchievementTitle }}</h4><p>{{ $achievement->txtDescription }}</p></div></li>
                            @empty
                                <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>No achievement yet</h4><p>Achievements will appear here after they are added.</p></div></li>
                            @endforelse
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    @endif
@endsection
