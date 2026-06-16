@extends('layouts.app', [
    'title' => 'Settings - Kalbe Internship Dashboard',
    'pageTitle' => 'SETTINGS',
    'pageSubtitle' => 'Manajemen data Settings Kalbe.',
])

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h2>System Settings</h2>
    </div>

    <div class="card">
        <div class="mb-3"><label class="form-label">Nama Administrator</label><input type="text" class="form-control" value="Admin HR Development"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="admin@kalbe.co.id"></div>
        <button class="btn btn-primary btn-add mt-2 w-100">Simpan Perubahan</button>
    </div>
@endsection
