@extends('layouts.app', [
    'title' => 'Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'PROFILE',
    'pageSubtitle' => 'Detail intern, project, mentor, dan pencapaian.',
    'bodyClass' => 'profile-page',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Profile Intern</h2>
            <p>Ringkasan data, performa, project, mentor, dan achievement.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Data</a>
    </div>

    <div class="profile-page-wrap">
        <section class="profile-hero">
            <div class="profile-identity">
                <img class="profile-avatar" src="https://ui-avatars.com/api/?name=Christopher+Rey&background=8CC63F&color=fff&size=160" alt="Christopher Rey">
                <div class="profile-name">
                    <h1>Christopher Rey W.</h1>
                    <p>Intern Teknik Industri yang sedang berfokus pada process improvement, CFD Optimization, dan kolaborasi lintas fungsi bersama mentor Kalbe.</p>
                    <div class="profile-meta">
                        <span class="profile-pill"><i class="fa-solid fa-id-card"></i> INT-001</span>
                        <span class="profile-pill"><i class="fa-solid fa-graduation-cap"></i> Universitas Indonesia</span>
                        <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> Aktif</span>
                    </div>
                </div>
            </div>

            <div class="profile-score-card">
                <div class="profile-score-ring"><span>95</span></div>
                <div class="profile-score-copy">
                    <h3>Exposure Score</h3>
                    <p>Level A dengan performa konsisten pada project delivery, knowledge sharing, dan kolaborasi mentor.</p>
                </div>
            </div>
        </section>

        <section class="profile-metrics">
            <div class="profile-metric"><i class="fa-solid fa-diagram-project"></i><strong>6</strong><span>Total Projects</span></div>
            <div class="profile-metric"><i class="fa-solid fa-user-tie"></i><strong>1</strong><span>Mentors</span></div>
            <div class="profile-metric"><i class="fa-solid fa-trophy"></i><strong>4</strong><span>Achievements</span></div>
            <div class="profile-metric"><i class="fa-solid fa-arrow-trend-up"></i><strong>+8.5%</strong><span>Growth</span></div>
        </section>

        <div class="profile-section-grid">
            <section class="profile-card">
                <div class="profile-card-header">
                    <div class="profile-card-title">
                        <h2>Projects</h2>
                        <p>Daftar project yang dikerjakan, dipisahkan berdasarkan tipe.</p>
                    </div>
                    <span class="status-badge status-active">On Track</span>
                </div>

                @foreach ([
                    ['icon' => 'fa-solid fa-people-arrows', 'type' => 'Collaboration', 'count' => '2 Projects', 'items' => [
                        ['Line Balancing Workshop', 'Kolaborasi improvement cycle bersama tim Manufacturing Excellence.', 92],
                        ['Exposure Matrix Review', 'Mapping capability intern dan kebutuhan project lintas departemen.', 86],
                    ]],
                    ['icon' => 'fa-solid fa-star', 'type' => 'Main', 'count' => '1 Project', 'items' => [
                        ['CFD Optimization', 'Optimasi aliran proses untuk mendukung efisiensi produksi dan risk reduction.', 80],
                    ]],
                    ['icon' => 'fa-solid fa-satellite-dish', 'type' => 'Satellite', 'count' => '2 Projects', 'items' => [
                        ['Digital Twin Dashboard', 'Prototype monitoring parameter proses untuk analisis harian.', 74],
                        ['QA Sampling Tracker', 'Perapihan data sampling untuk mempercepat review kualitas.', 68],
                    ]],
                    ['icon' => 'fa-solid fa-book-open', 'type' => 'Sharing', 'count' => '1 Activity', 'items' => [
                        ['Process Simulation Sharing', 'Sesi berbagi pembelajaran tentang simulation workflow untuk intern batch berjalan.', 100],
                    ]],
                ] as $group)
                    <div class="project-type-group">
                        <div class="project-type-head">
                            <h3><i class="{{ $group['icon'] }}"></i> {{ $group['type'] }}</h3>
                            <span class="status-badge status-active">{{ $group['count'] }}</span>
                        </div>
                        <ul class="project-list">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <div>
                                        <h4>{{ $item[0] }}</h4>
                                        <p>{{ $item[1] }}</p>
                                    </div>
                                    <div class="mini-progress">
                                        <span>{{ $item[2] }}%</span>
                                        <div class="progress-track"><div class="progress-fill" style="width:{{ $item[2] }}%"></div></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
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
                        <div class="mentor-item">
                            <img src="https://ui-avatars.com/api/?name=Wahyu+Agus&background=006838&color=fff" alt="Wahyu Agus">
                            <div><h4>Wahyu Agus</h4><p>IT & Data - Project Mentor</p></div>
                        </div>
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
                        <div class="score-row"><span>Hard Skills</span><div class="progress-track"><div class="progress-fill" style="width:96%"></div></div><strong>96</strong></div>
                        <div class="score-row"><span>Collaboration</span><div class="progress-track"><div class="progress-fill" style="width:94%"></div></div><strong>94</strong></div>
                        <div class="score-row"><span>Ownership</span><div class="progress-track"><div class="progress-fill" style="width:97%"></div></div><strong>97</strong></div>
                        <div class="score-row"><span>Sharing</span><div class="progress-track"><div class="progress-fill" style="width:91%"></div></div><strong>91</strong></div>
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
                        <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>Top Performer</h4><p>Peringkat 1 leaderboard exposure bulan ini.</p></div></li>
                        <li><div class="achievement-icon"><i class="fa-solid fa-lightbulb"></i></div><div><h4>Best Improvement Idea</h4><p>Usulan optimasi CFD masuk review implementasi.</p></div></li>
                        <li><div class="achievement-icon"><i class="fa-solid fa-users"></i></div><div><h4>Collaboration Champion</h4><p>Aktif membantu sync antar intern dan mentor.</p></div></li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
@endsection
