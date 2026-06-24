@extends('layouts.app', [
    'title' => 'Kalbe Internship Management Dashboard',
    'pageTitle' => 'INTERNSHIP DASHBOARD',
    'pageSubtitle' => '<span>Expose</span> &bull; <span>Learn</span> &bull; <span>Grow</span>',
    'bodyClass' => 'dashboard-index',
])

@section('content')
    <div class="page-view active">
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="kpi-data">
                    <h4>Total Interns</h4>
                    <h2>{{ $totalInterns }}</h2>
                    <p>Active interns</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-star"></i></div>
                <div class="kpi-data">
                    <h4>Average Score</h4>
                    <h2>{{ $averageScore }}</h2>
                    <p>Weighted project score</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-people-arrows"></i></div>
                <div class="kpi-data">
                    <h4>Collaboration</h4>
                    <h2>{{ $collaborationCount }}</h2>
                    <p>Active projects</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-book-open"></i></div>
                <div class="kpi-data">
                    <h4>Sharing Activities</h4>
                    <h2>{{ $sharingCount }}</h2>
                    <p>Knowledge shared</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="column col-left">
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-num">1</div>
                        <div class="card-title">EXPOSURE</div>
                    </div>
                    <ul class="exposure-list ps-1">
                        <li>
                            <div class="level score-a"><div class="dot" style="background:var(--primary)"></div> A (High)</div>
                            <div class="interns">{{ $latestEvaluations->where('floatExposureScore', '>=', 85)->pluck('intern.txtInternName')->filter()->join(', ') ?: '-' }}</div>
                        </li>
                        <li>
                            <div class="level score-b"><div class="dot" style="background:var(--secondary)"></div> B (Medium)</div>
                            <div class="interns">{{ $latestEvaluations->whereBetween('floatExposureScore', [70, 84.99])->pluck('intern.txtInternName')->filter()->join(', ') ?: '-' }}</div>
                        </li>
                        <li>
                            <div class="level score-c"><div class="dot" style="background:var(--warning)"></div> C (Low)</div>
                            <div class="interns">{{ $latestEvaluations->whereBetween('floatExposureScore', [50, 69.99])->pluck('intern.txtInternName')->filter()->join(', ') ?: '-' }}</div>
                        </li>
                        <li>
                            <div class="level score-d"><div class="dot" style="background:#EA580C"></div> D (Very Low)</div>
                            <div class="interns">{{ $latestEvaluations->where('floatExposureScore', '<', 50)->pluck('intern.txtInternName')->filter()->join(', ') ?: '-' }}</div>
                        </li>
                    </ul>
                </div>

                <x-s-curve-chart
                    :payload="$mainSCurvePayload"
                    title="S Curve Project"
                    subtitle="Weighted planned vs actual by project type."
                    mode="main"
                    height="190px"
                    compact
                    class="dashboard-s-curve-card"
                />

                <div class="card" style="border: none; box-shadow: none; padding: 0; background: transparent; margin-bottom: 0;">
                    <div class="card-title" style="font-size: 13px; margin-bottom: 5px;">TOP PERFORMERS</div>
                    <div class="podium-section">
                        @forelse ($topPerformers as $index => $evaluation)
                            @php
                                $rank = $index + 1;
                            @endphp
                            <div class="podium p-{{ $rank }}">
                                <div class="badge">{{ $rank }}</div>
                                <img src="{{ $evaluation['intern']->user?->txtProfilePhoto ? asset('storage/' . $evaluation['intern']->user->txtProfilePhoto) : 'https://ui-avatars.com/api/?name=' . urlencode($evaluation['intern']->txtInternName ?? 'Intern') . '&background=random' }}" alt="{{ $evaluation['intern']->txtInternName ?? 'Intern' }}">
                                <h5>{{ $evaluation['intern']->txtInternName ?? '-' }}</h5>
                                <div class="stars">*****</div>
                                <div class="score">{{ number_format((float) $evaluation['score'], 0) }}</div>
                            </div>
                        @empty
                            <div class="card" style="box-shadow:none; margin-bottom:0;">No evaluations yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="column col-mid">
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-num">2</div>
                        <div class="card-title">EXPOSURE MATRIX</div>
                    </div>
                    <div class="table-responsive interns-leaderboard-scroll">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>PROJECT / AREA</th>
                                    @foreach ($interns as $intern)
                                        <th class="center">{{ \Illuminate\Support\Str::of($intern->txtInternName)->trim()->before(' ') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['Collaboration', 'Main', 'Satellite', 'Sharing'] as $type)
                                    <tr>
                                        <td>{{ $type }}</td>
                                        @foreach ($interns as $intern)
                                            @php
                                                $matchingAssignments = $assignments->filter(fn ($assignment) => $assignment->bitActive && $assignment->project?->bitActive && $assignment->intIntern_ID === $intern->intIntern_ID && $assignment->project?->txtProjectType === $type);
                                                $totalProject = $matchingAssignments->count();
                                                $completedProject = $matchingAssignments->filter(fn ($assignment) => (float) $assignment->floatProgress >= 100 || $assignment->txtStatus === 'Completed')->count();
                                            @endphp
                                            <td class="center {{ $totalProject > 10 ? 'score-a' : ($totalProject >= 5 ? 'score-b' : ($totalProject >= 2 ? 'score-c' : ($totalProject === 1 ? 'score-d' : ''))) }}">{{ $totalProject ? $completedProject . '/' . $totalProject : '-' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- <div style="margin-top:15px; font-size:11px; display:flex; gap:10px; justify-content:center; font-weight: 600; flex-wrap: wrap;">
                        <span><span class="dot" style="display:inline-block;background:var(--primary);"></span> &gt;10 Total</span>
                        <span><span class="dot" style="display:inline-block;background:var(--secondary);"></span> 5-10 Total</span>
                        <span><span class="dot" style="display:inline-block;background:var(--warning);"></span> 2-4 Total</span>
                        <span><span class="dot" style="display:inline-block;background:#EA580C;"></span> 1 Total</span>
                    </div> -->
                </div>

                <div class="card" style="flex-grow: 1; margin-bottom: 0;">
                    <div class="card-title" style="font-size:13px; margin-bottom:15px;">INTERNS LEADERBOARD</div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th>RANK</th><th>INTERN</th><th>SCORE</th><th>PERIOD</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($leaderboardRows as $index => $row)
                                    <tr>
                                        <td><span class="card-num" style="background:transparent;border:1px solid var(--border);border-radius:50%;width:20px;height:20px;color:var(--text-dark);">{{ $index + 1 }}</span></td>
                                        <td>{{ $row['intern']->txtInternName ?? '-' }}</td>
                                        <td class="score-a">{{ number_format((float) $row['score'], 0) }}</td>
                                        <td>{{ $row['period'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No leaderboard data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="column col-right">
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-num">3</div>
                        <div class="card-title">SKILL SET</div>
                    </div>

                    <div class="skill-pie-wrap">
                        <canvas id="skillSetPieChart"></canvas>
                    </div>
                    <script type="application/json" id="skillSetPieData">{!! json_encode([
                        'labels' => $skillSetProjects->keys()->values(),
                        'values' => $skillSetProjects->values(),
                    ]) !!}</script>
                    <!-- <div class="skill-set-list">
                        @forelse ($skillSetProjects as $skillSetName => $total)
                            <div class="skill-set-row"><span>{{ $skillSetName }}</span><strong>{{ $total }}</strong></div>
                        @empty
                            <div class="skill-set-row"><span>No mapped skill set projects yet.</span><strong>0</strong></div>
                        @endforelse
                    </div> -->
                </div>

                <div class="card" style="flex-grow:1; margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-num">4</div>
                        <div class="card-title">CALENDAR SHARING</div>
                    </div>
                    <ul class="gov-list ps-1">
                        @forelse ($upcomingCalendarSharings as $sharing)
                            @php
                                $creatorName = $sharing->creator?->intern?->txtInternName
                                    ?? $sharing->creator?->mentor?->txtMentorName
                                    ?? $sharing->creator?->txtEmail
                                    ?? '-';
                            @endphp
                            <li>
                                <div class="gov-info">
                                    <div class="gov-icon"><i class="{{ $sharing->txtCalendarSharingIcon ?: 'fa-regular fa-calendar-days' }}"></i></div>
                                    <div class="gov-text">
                                        <h5>{{ $sharing->txtCalendarSharingTheme }}</h5>
                                        <p>{{ $sharing->dtmCalendarSharingDate?->format('d M Y') ?? '-' }} - {{ $sharing->txtCalendarSharingStatus ?: 'Open' }} - {{ $creatorName }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('calendar-sharing.index', ['month' => $sharing->dtmCalendarSharingDate?->format('Y-m') ?? now()->format('Y-m')]) }}" class="gov-link-icon" aria-label="Open Calendar Sharing"><i class="fa-solid fa-chevron-right"></i></a>
                            </li>
                        @empty
                            <li style="border-bottom:none;"><div class="gov-info"><div class="gov-icon"><i class="fa-regular fa-calendar-days"></i></div><div class="gov-text"><h5>No upcoming sharing</h5><p>New sharing schedules will appear here.</p></div></div></li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
