@extends('layouts.app', [
    'title' => 'Kalbe Internship Management Dashboard',
    'pageTitle' => 'INTERNSHIP DASHBOARD',
    'pageSubtitle' => '<span>Expose</span> &bull; <span>Learn</span> &bull; <span>Grow</span>',
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
                    <h2>{{ $averageScore }} <span>/100</span></h2>
                    <p><span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 8.5%</span> vs last month</p>
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
                    <ul class="exposure-list">
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

                <div class="card" style="margin-bottom: 0;">
                    <div class="card-title" style="margin-bottom: 10px; font-size:12px;">EXPOSURE PROGRESSION</div>
                    <div style="height: 150px; position: relative; width: 100%;">
                        <canvas id="lineChart"></canvas>
                    </div>
                </div>

                <div class="card" style="border: none; box-shadow: none; padding: 0; background: transparent; margin-bottom: 0;">
                    <div class="card-title" style="font-size: 13px; margin-bottom: 5px;">TOP PERFORMERS</div>
                    <div class="podium-section">
                        @forelse ($topPerformers as $index => $evaluation)
                            @php($rank = $index + 1)
                            <div class="podium p-{{ $rank }}">
                                <div class="badge">{{ $rank }}</div>
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($evaluation->intern->txtInternName ?? 'Intern') }}&background=random" alt="{{ $evaluation->intern->txtInternName ?? 'Intern' }}">
                                <h5>{{ $evaluation->intern->txtInternName ?? '-' }}</h5>
                                <div class="stars">★★★★★</div>
                                <div class="score">{{ number_format((float) $evaluation->floatExposureScore, 0) }}</div>
                            </div>
                        @empty
                            <div class="card" style="box-shadow:none; margin-bottom:0;">Belum ada evaluasi.</div>
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
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>PROJECT / AREA</th>
                                    @foreach ($interns->take(5) as $intern)
                                        <th class="center">{{ \Illuminate\Support\Str::limit($intern->txtInternName, 8, '') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['Collaboration', 'Main', 'Satellite', 'Sharing'] as $type)
                                    <tr>
                                        <td>{{ $type }}</td>
                                        @foreach ($interns->take(5) as $intern)
                                            @php($qty = $assignments->filter(fn ($assignment) => $assignment->intIntern_ID === $intern->intIntern_ID && $assignment->project?->txtProjectType === $type)->count())
                                            <td class="center {{ $qty > 10 ? 'score-a' : ($qty >= 5 ? 'score-b' : ($qty >= 2 ? 'score-c' : ($qty === 1 ? 'score-d' : ''))) }}">{{ $qty ?: '-' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:15px; font-size:11px; display:flex; gap:10px; justify-content:center; font-weight: 600; flex-wrap: wrap;">
                        <span><span class="dot" style="display:inline-block;background:var(--primary);"></span> &gt;10 Qty</span>
                        <span><span class="dot" style="display:inline-block;background:var(--secondary);"></span> 5-10 Qty</span>
                        <span><span class="dot" style="display:inline-block;background:var(--warning);"></span> 2-4 Qty</span>
                        <span><span class="dot" style="display:inline-block;background:#EA580C;"></span> 1 Qty</span>
                    </div>
                </div>

                <div class="card" style="flex-grow: 1; margin-bottom: 0;">
                    <div class="card-title" style="font-size:13px; margin-bottom:15px;">INTERNS LEADERBOARD</div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th>RANK</th><th>INTERN</th><th>MAIN PROJECT</th><th>SCORE</th><th>TREND</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($topPerformers as $index => $evaluation)
                                    <tr>
                                        <td><span class="card-num" style="background:transparent;border:1px solid var(--border);border-radius:50%;width:20px;height:20px;color:var(--text-dark);">{{ $index + 1 }}</span></td>
                                        <td>{{ $evaluation->intern->txtInternName ?? '-' }}</td>
                                        <td>{{ $evaluation->intern?->projects?->first()?->project?->txtProjectName ?? '-' }}</td>
                                        <td class="score-a">{{ number_format((float) $evaluation->floatExposureScore, 0) }}</td>
                                        <td class="score-a"><i class="fa-solid fa-arrow-up"></i></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">Belum ada data leaderboard.</td></tr>
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
                        <div class="card-title">GAP / RISK ANALYSIS</div>
                    </div>

                    <div class="risk-header">
                        <div><i class="fa-solid fa-graduation-cap" style="font-size:20px; color:var(--primary);"></i> <div>Mahasiswa<br><span style="font-size:10px;font-weight:500;">(We Have)</span></div></div>
                        <div><i class="fa-regular fa-building" style="font-size:20px; color:var(--secondary);"></i> <div style="text-align:right;">Industri<br><span style="font-size:10px;font-weight:500;">(We Need)</span></div></div>
                    </div>

                    <div class="risk-row"><div style="flex:1;"><i class="fa-solid fa-check" style="color:var(--success);margin-right:8px;"></i> Hard Skills</div><div class="check"><i class="fa-solid fa-check" style="color:var(--success);"></i></div><div class="check"><i class="fa-solid fa-check" style="color:var(--success);"></i></div></div>
                    <div class="risk-row"><div style="flex:1;"><i class="fa-solid fa-check" style="color:var(--success);margin-right:8px;"></i> Project Exposure</div><div class="check"><i class="fa-solid fa-check" style="color:var(--success);"></i></div><div class="check"><i class="fa-solid fa-check" style="color:var(--success);"></i></div></div>
                    <div class="risk-row"><div style="flex:1;"><i class="fa-solid fa-check" style="color:var(--success);margin-right:8px;"></i> Industry Knowledge</div><div class="check"><i class="fa-solid fa-xmark" style="color:var(--danger);font-size:16px;"></i></div><div class="check"><i class="fa-solid fa-check" style="color:var(--success);"></i></div></div>

                    <div class="alert-box">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div>
                            <p>Gap utama: <span>Industry Knowledge</span></p>
                            <p style="font-weight: 500; font-size:11px; margin-top:2px;">Risk level: <span style="color:var(--danger); font-weight:700;">Medium</span></p>
                        </div>
                    </div>
                </div>

                <div class="card" style="flex-grow:1; margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-num">4</div>
                        <div class="card-title">GOVERNANCE</div>
                    </div>
                    <ul class="gov-list">
                        <li><div class="gov-info"><div class="gov-icon"><i class="fa-regular fa-comments"></i></div><div class="gov-text"><h5>Meeting / Review</h5><p>Daily - Weekly - Monthly</p></div></div><i class="fa-solid fa-chevron-right" style="color:var(--text-gray); font-size:12px;"></i></li>
                        <li><div class="gov-info"><div class="gov-icon"><i class="fa-solid fa-newspaper"></i></div><div class="gov-text"><h5>Weekly Teknis</h5><p>User Company Review</p></div></div><i class="fa-solid fa-chevron-right" style="color:var(--text-gray); font-size:12px;"></i></li>
                        <li style="border-bottom:none;"><div class="gov-info"><div class="gov-icon"><i class="fa-solid fa-graduation-cap"></i></div><div class="gov-text"><h5>Graduation</h5><p>Final Evaluation</p></div></div><i class="fa-solid fa-chevron-right" style="color:var(--text-gray); font-size:12px;"></i></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
