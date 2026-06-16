@extends('layouts.app', [
    'title' => 'Achievements - Kalbe Internship Dashboard',
    'pageTitle' => 'ACHIEVEMENTS',
    'pageSubtitle' => 'Manajemen data Achievements Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Achievements</h2>
            <p>Penghargaan dan milestone untuk intern.</p>
        </div>
    </div>

    <div class="profile-section-grid">
        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Achievement List</h2>
                    <p>Catatan pencapaian program internship.</p>
                </div>
            </div>
            <ul class="achievement-list">
                <li><div class="achievement-icon"><i class="fa-solid fa-award"></i></div><div><h4>Top Performer</h4><p>Christopher Rey W. menempati peringkat 1 leaderboard exposure.</p></div></li>
                <li><div class="achievement-icon"><i class="fa-solid fa-lightbulb"></i></div><div><h4>Best Improvement Idea</h4><p>Ide optimasi CFD masuk review implementasi.</p></div></li>
                <li><div class="achievement-icon"><i class="fa-solid fa-users"></i></div><div><h4>Collaboration Champion</h4><p>Aktif membantu sync antar intern dan mentor.</p></div></li>
            </ul>
        </section>
        <aside class="profile-card">
            <div class="profile-card-title">
                <h2>Summary</h2>
                <p>Total award aktif bulan ini.</p>
            </div>
            <div class="kpi-card" style="margin-top: 18px;">
                <div class="kpi-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="kpi-data"><h4>Total Awards</h4><h2>12</h2><p>Across all interns</p></div>
            </div>
        </aside>
    </div>
@endsection
