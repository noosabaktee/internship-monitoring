@extends('layouts.app', [
    'title' => 'Reports - Kalbe Internship Dashboard',
    'pageTitle' => 'REPORTS',
    'pageSubtitle' => 'SharePoint report workspace.',
])

@php
    $sharePointReportUrl = 'https://docs.google.com/spreadsheets/d/1T0C8mc1QFMEwuZJaQlM8iEPN45vZPa1Kp52X56nPtCA/edit?usp=sharing';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Report</h2>
            <p>Embedded SharePoint folder for internship report files.</p>
        </div>
        <a class="btn btn-primary btn-add" href="https://onekn-my.sharepoint.com/:f:/g/personal/irpan_pamil_kalbenutritionals_com/Ep6fkRRjaqhCq6xy5BnPq8UBhJCG1hsLrAbpi5LhRUa0_w?e=wMiusc" target="_blank" rel="noopener" style="text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Sharepoint</a>
    </div>

    <div class="card report-embed-card">
        <iframe class="report-embed-frame" src="{{ $sharePointReportUrl }}" title="SharePoint Report"></iframe>
    </div>
@endsection
