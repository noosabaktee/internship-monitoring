@extends('layouts.app', [
    'title' => 'Leaderboard - Kalbe Internship Dashboard',
    'pageTitle' => 'LEADERBOARD',
    'pageSubtitle' => 'Manajemen data Leaderboard Kalbe.',
])

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Leaderboard</h2>
            <p>Peringkat kontribusi dan performa keseluruhan intern.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Rank</th><th>Intern</th><th>Main Project</th><th>Score</th><th>Period</th><th>Trend</th></tr>
                </thead>
                <tbody>
                    @forelse ($leaderboard as $index => $evaluation)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $evaluation->intern->txtInternName ?? '-' }}</td>
                            <td>{{ $evaluation->intern?->projects?->first()?->project?->txtProjectName ?? '-' }}</td>
                            <td class="{{ $evaluation->floatExposureScore >= 85 ? 'score-a' : ($evaluation->floatExposureScore >= 70 ? 'score-b' : 'score-c') }}">{{ number_format((float) $evaluation->floatExposureScore, 1) }}</td>
                            <td>{{ $evaluation->dtmPeriod?->format('M Y') ?? '-' }}</td>
                            <td class="score-a"><i class="fa-solid fa-arrow-up"></i></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="center">Belum ada data evaluasi untuk leaderboard.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
