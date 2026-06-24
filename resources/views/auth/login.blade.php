@extends('layouts.auth', ['title' => 'Login - Kalbe Internship Dashboard'])

@section('content')
    @php
        $summary = $loginSummary ?? [];
        $topPerformer = $summary['topPerformer'] ?? [];
        $topName = $topPerformer['name'] ?? 'Christopher Rey Wijaya';
        $topPhoto = ! empty($topPerformer['photo'])
            ? asset('storage/' . $topPerformer['photo'])
            : 'https://ui-avatars.com/api/?name=' . urlencode($topName) . '&background=0B8F4C&color=fff&bold=true';
        $actualProgress = number_format((float) ($summary['actualProgress'] ?? 41.5), 1, '.', '');
        $skillIconMap = [
            'Engineering Modeling & Simulation' => 'fa-solid fa-drafting-compass',
            'Web Development' => 'fa-solid fa-laptop-code',
            'Embedded Systems & IoT Data Acquisition' => 'fa-solid fa-microchip',
            'AI & Computer Vision' => 'fa-solid fa-eye',
            'Robotic Process Automation (RPA)' => 'fa-solid fa-robot',
            'Reverse Engineering' => 'fa-solid fa-screwdriver-wrench',
            'Unmapped' => 'fa-solid fa-layer-group',
        ];
        $skillLabelMap = [
            'Engineering Modeling & Simulation' => 'Engineering Modeling & Simulation',
            'Web Development' => 'Web Development',
            'Embedded Systems & IoT Data Acquisition' => 'Embedded Systems & IoT',
            'AI & Computer Vision' => 'AI & Computer Vision',
            'Robotic Process Automation (RPA)' => 'RPA Automation',
            'Reverse Engineering' => 'Reverse Engineering',
        ];
    @endphp

    <main class="auth-shell" data-auth-page>
        <section class="auth-visual" aria-label="Internship dashboard summary">
            <div class="auth-brand-row">
                <div class="auth-brand">
                    <span class="auth-brand-mark"><i class="fa-solid fa-seedling"></i></span>
                    <span>
                        <strong>KALBE <em>INTERN</em></strong>
                        <small>Expose &bull; Learn &bull; Grow</small>
                    </span>
                </div>
                <span class="auth-live-chip"><i class="fa-solid fa-leaf"></i> Welcome!</span>
            </div>

            <div class="auth-hero">
                <h1>Manage internships<br>with a <span>cleaner rhythm.</span></h1>
                <p>Sign in to monitor interns, projects, mentors, exposure scores, and program progress reports in one dashboard.</p>
            </div>

            <div class="auth-stats">
                <button class="auth-stat is-active" type="button" data-auth-tab="progress">
                    <i class="fa-solid fa-users"></i>
                    <strong>{{ $summary['totalInterns'] ?? 10 }}</strong>
                    <span>Active Interns</span>
                </button>
                <button class="auth-stat" type="button" data-auth-tab="progress">
                    <i class="fa-solid fa-briefcase"></i>
                    <strong>{{ $summary['activeProjects'] ?? 6 }}</strong>
                    <span>Active Projects</span>
                </button>
                <button class="auth-stat" type="button" data-auth-tab="performer">
                    <i class="fa-solid fa-star"></i>
                    <strong>{{ $summary['averageScore'] ?? 80.7 }}</strong>
                    <span>Average Score</span>
                </button>
                <button class="auth-stat" type="button" data-auth-tab="sessions">
                    <i class="fa-solid fa-people-arrows"></i>
                    <strong>{{ $summary['collaborationCount'] ?? 3 }}</strong>
                    <span>Collaboration Projects</span>
                </button>
            </div>

            <div class="auth-preview-grid">
                <article class="auth-preview-card auth-progress-card is-focused" data-auth-card="progress">
                    <div class="auth-preview-title">{{ $summary['monthLabel'] ?? now()->format('F Y') }} Progress</div>
                    <div class="auth-progress-body">
                        <ul class="auth-progress-list">
                            @foreach ($summary['progressItems'] ?? [] as $item)
                                <li>
                                    <span><i style="--item-color: {{ $item['color'] ?? '#21d66f' }}"></i>{{ $item['label'] }}</span>
                                    <strong>{{ $item['display'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                        <div class="auth-progress-ring" style="--auth-progress: {{ $actualProgress }}%">
                            <span>{{ $actualProgress }}%</span>
                            <small>Actual Progress</small>
                        </div>
                    </div>
                </article>

                <article class="auth-preview-card auth-performer-card" data-auth-card="performer">
                    <div class="auth-preview-title"><i class="fa-solid fa-trophy"></i> Top Performer This Month</div>
                    <div class="auth-performer">
                        <div class="auth-performer-avatar">
                            <i class="fa-solid fa-crown"></i>
                            <img src="{{ $topPhoto }}" alt="{{ $topName }}">
                        </div>
                        <h2>{{ $topName }}</h2>
                        <p>{{ $topPerformer['tag'] ?? 'High Exposure' }}</p>
                        <div class="auth-performer-meta">
                            <span><small>Score</small>{{ number_format((float) ($topPerformer['score'] ?? 22), 0) }}</span>
                            <span><small>Focus</small>{{ $topPerformer['focus'] ?? 'Engineering Modeling & Simulation' }}</span>
                        </div>
                        <div class="auth-avatar-stack" aria-label="Leaderboard preview">
                            @foreach ($summary['performerAvatars'] ?? [] as $avatar)
                                @php
                                    $avatarName = $avatar['name'] ?? 'Intern';
                                    $avatarPhoto = ! empty($avatar['photo'])
                                        ? asset('storage/' . $avatar['photo'])
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($avatarName) . '&background=E9F7EF&color=006838&bold=true';
                                @endphp
                                <img src="{{ $avatarPhoto }}" alt="{{ $avatarName }}">
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="auth-preview-card auth-session-card" data-auth-card="sessions">
                    <div class="auth-preview-title">Upcoming Sessions</div>
                    <div class="auth-session-list">
                        @foreach ($summary['sessions'] ?? [] as $session)
                            <div class="auth-session-item">
                                <div class="auth-session-date">
                                    <strong>{{ $session['day'] }}</strong>
                                    <span>{{ $session['month'] }}</span>
                                </div>
                                <div>
                                    <h3><i class="{{ $session['icon'] ?? 'fa-regular fa-calendar-days' }}"></i>{{ $session['title'] }}</h3>
                                    <p>{{ $session['time'] ?? '14:00 - 16:00' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button class="auth-text-button" type="button" data-auth-cycle>
                        View all sessions
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </article>
            </div>

            <div class="auth-skill-section">
                <div class="auth-preview-title">Skill Development Focus</div>
                <div class="auth-skill-strip">
                    @foreach ($summary['skillSets'] ?? [] as $skill)
                        @php
                            $skillName = $skill['name'] ?? 'Skill Focus';
                        @endphp
                        <button class="auth-skill-tile" type="button">
                            <i class="{{ $skillIconMap[$skillName] ?? 'fa-solid fa-layer-group' }}"></i>
                            <span>{{ $skillLabelMap[$skillName] ?? $skillName }}</span>
                            <small>{{ $skill['count'] ?? 0 }} project</small>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="auth-update-bar">
                <strong><i class="fa-solid fa-bullhorn"></i> Program Updates</strong>
                @foreach ($summary['updates'] ?? [] as $update)
                    <span>{{ $update }}</span>
                @endforeach
            </div>

            <div class="auth-visual-footer">
                Empowering Future Engineers Through Real Exposure and Collaboration.
                <i class="fa-solid fa-seedling"></i>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-logo"><i class="fa-solid fa-seedling"></i></div>
                <div class="auth-card-header">
                    <h2>Welcome Back</h2>
                    <p>Log in with your registered account to open the internship dashboard.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex gap-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form class="auth-form" action="{{ route('login.authenticate') }}" method="POST" data-auth-form>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="txtEmail">Email</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-envelope"></i>
                            <input id="txtEmail" type="email" name="txtEmail" class="form-control" value="{{ old('txtEmail') }}" placeholder="nama@kalbe.co.id" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="txtPassword">Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input id="txtPassword" type="password" name="txtPassword" class="form-control" placeholder="Enter password" required>
                            <button class="auth-password-toggle" type="button" data-password-toggle aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-options">
                        <label><input type="checkbox" name="remember"> Remember me</label>
                        <a href="#" class="auth-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit" data-auth-submit>
                        <span>Login</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
<!-- 
                <div class="auth-separator"><span>or</span></div>

                <button class="auth-google" type="button">
                    <span class="auth-google-mark">G</span>
                    Login with Google
                </button> -->

                <!-- <div class="auth-switch">
                    Do not have an account? <a href="{{ route('register') }}" class="auth-link">Register now</a>
                </div> -->
            </div>
        </section>
    </main>
@endsection
