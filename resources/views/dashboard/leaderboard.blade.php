@extends('layouts.app', [
    'title' => 'Leaderboard - Kalbe Internship Dashboard',
    'pageTitle' => 'LEADERBOARD',
    'pageSubtitle' => 'Manajemen data Leaderboard Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Leaderboard</h2>
            <p>Peringkat kontribusi dan performa keseluruhan intern.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Rank</th><th>Intern</th><th>Main Project</th><th>Score</th><th>Trend</th></tr>
                </thead>
                <tbody>
                    <tr><td>1</td><td>Christopher Rey W.</td><td>CFD Optimization</td><td class="score-a">95</td><td class="score-a"><i class="fa-solid fa-arrow-up"></i></td></tr>
                    <tr><td>2</td><td>Humaira Zeanova</td><td>Spray Dryer</td><td class="score-a">92</td><td class="score-a"><i class="fa-solid fa-arrow-up"></i></td></tr>
                    <tr><td>3</td><td>Rama Nusa B.</td><td>Digital Twin</td><td class="score-a">88</td><td class="score-a"><i class="fa-solid fa-arrow-up"></i></td></tr>
                    <tr><td>4</td><td>Khansa Aulia</td><td>Line Balancing</td><td class="score-b">78</td><td class="score-b"><i class="fa-solid fa-minus"></i></td></tr>
                    <tr><td>5</td><td>Husain Farhan</td><td>QA Sampling</td><td class="score-b">74</td><td class="score-a"><i class="fa-solid fa-arrow-up"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
