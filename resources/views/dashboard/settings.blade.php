@extends('layouts.app', [
    'title' => 'Settings - Kalbe Internship Dashboard',
    'pageTitle' => 'SETTINGS',
    'pageSubtitle' => 'Manajemen data Settings Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <h2>System Settings</h2>
    </div>

    <div class="card">
        <div class="form-group"><label>Nama Administrator</label><input type="text" class="form-control" value="Admin HR Development"></div>
        <div class="form-group"><label>Email</label><input type="email" class="form-control" value="admin@kalbe.co.id"></div>
        <button class="btn-add" style="margin-top: 20px; width: 100%;">Simpan Perubahan</button>
    </div>
@endsection
