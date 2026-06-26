@extends('layouts.app', [
    'title' => 'Reports - Kalbe Internship Dashboard',
    'pageTitle' => 'REPORTS',
    'pageSubtitle' => 'SharePoint report workspace.',
])

@php
    $sharePointReportUrl = 'https://useful-partner-98b.notion.site/ebd//38a7861b550e80cf8fb3f509fdc28ec8?v=38a7861b550e80a98cf8000c308ef3dc';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Report</h2>
            <p>Embedded SharePoint folder for internship report files.</p>
        </div>
        <a class="btn btn-primary btn-add" href="{{ $sharePointReportUrl }}" target="_blank" rel="noopener" style="text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Notion</a>
    </div>

    <div class="card report-embed-card">
        <iframe class="report-embed-frame" src="{{ $sharePointReportUrl }}" title="SharePoint Report"></iframe>
    </div>
@endsection
