@extends('layouts.app', [
    'title' => 'Reports - Kalbe Internship Dashboard',
    'pageTitle' => 'REPORTS',
    'pageSubtitle' => 'Manage Kalbe report data.',
])

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h2>Reporting Center</h2>
    </div>

    <div class="card" style="text-align: center; padding: 40px;">
        <i class="fa-solid fa-file-pdf" style="font-size: 40px; color: var(--text-gray);"></i>
        <h3 style="margin: 15px 0;">Generate PDF/Excel Reports</h3>
        <button class="btn btn-primary btn-add mx-auto" onclick="alert('Download is running...')"><i class="fa-solid fa-download"></i> Download File</button>
    </div>
@endsection
