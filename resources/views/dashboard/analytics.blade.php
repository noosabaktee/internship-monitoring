@extends('layouts.app', [
    'title' => 'Analytics - Kalbe Internship Dashboard',
    'pageTitle' => 'ANALYTICS',
    'pageSubtitle' => 'Manajemen data Analytics Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Analytics</h2>
            <p>Statistik dan laporan performa mendalam.</p>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div><div class="kpi-data"><h4>Exposure Growth</h4><h2>+8.5%</h2><p>vs last month</p></div></div>
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-people-arrows"></i></div><div class="kpi-data"><h4>Collaboration Avg</h4><h2>84</h2><p>Program score</p></div></div>
        <div class="kpi-card"><div class="kpi-icon"><i class="fa-solid fa-book-open"></i></div><div class="kpi-data"><h4>Sharing Avg</h4><h2>79</h2><p>Knowledge activity</p></div></div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom: 10px;">EXPOSURE PROGRESSION</div>
        <div style="height: 260px; position: relative; width: 100%;">
            <canvas id="lineChart"></canvas>
        </div>
    </div>
@endsection
