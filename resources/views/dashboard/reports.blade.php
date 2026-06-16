@extends('layouts.app', [
    'title' => 'Reports - Kalbe Internship Dashboard',
    'pageTitle' => 'REPORTS',
    'pageSubtitle' => 'Manajemen data Reports Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <h2>Reporting Center</h2>
    </div>

    <div class="card" style="text-align: center; padding: 40px;">
        <i class="fa-solid fa-file-pdf" style="font-size: 40px; color: var(--text-gray);"></i>
        <h3 style="margin: 15px 0;">Generate Laporan PDF/Excel</h3>
        <button class="btn-add" style="margin: 0 auto;" onclick="alert('Fungsi download berjalan...')"><i class="fa-solid fa-download"></i> Download File</button>
    </div>
@endsection
