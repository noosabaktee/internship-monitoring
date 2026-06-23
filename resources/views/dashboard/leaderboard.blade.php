@extends('layouts.app', [
    'title' => 'Leaderboard - Kalbe Internship Dashboard',
    'pageTitle' => 'LEADERBOARD',
    'pageSubtitle' => 'Manage Kalbe leaderboard data.',
])

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Leaderboard</h2>
            <p>Ranking is calculated from handled projects multiplied by project type weights.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Rank</th><th>Intern</th><th>Main Project</th><th>Main</th><th>Collaboration</th><th>Satellite</th><th>Sharing</th><th>Score</th><th>Period</th></tr>
                </thead>
                <tbody>
                    @forelse ($leaderboard as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row['intern']->txtInternName ?? '-' }}</td>
                            <td>{{ $row['main_project'] }}</td>
                            <td>{{ $row['main'] }}</td>
                            <td>{{ $row['collaboration'] }}</td>
                            <td>{{ $row['satellite'] }}</td>
                            <td>{{ $row['sharing'] }}</td>
                            <td class="score-a">{{ number_format((float) $row['score'], 0) }}</td>
                            <td>{{ $row['period'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="center">No project data for the leaderboard yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom: 12px;">ACTIVE WEIGHTS</div>
        <div class="weight-summary">
            <span>Main: <strong>{{ $weights['main'] }}</strong></span>
            <span>Collaboration: <strong>{{ $weights['collaboration'] }}</strong></span>
            <span>Satellite: <strong>{{ $weights['satellite'] }}</strong></span>
            <span>Sharing: <strong>{{ $weights['sharing'] }}</strong></span>
        </div>
    </div>
@endsection
