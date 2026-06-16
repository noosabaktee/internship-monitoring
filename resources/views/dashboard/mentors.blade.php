@extends('layouts.app', [
    'title' => 'Mentors - Kalbe Internship Dashboard',
    'pageTitle' => 'MENTORS',
    'pageSubtitle' => 'Manajemen data Mentors Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Mentors</h2>
            <p>Kelola data mentor berdasarkan departemen dan status pendampingan.</p>
        </div>
        <button class="btn-add" onclick="openCrudModal('mentor', 'Tambah Mentor Baru')"><i class="fa-solid fa-plus"></i> Tambah Mentor</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Nama Lengkap</th><th>Department</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>MTR-001</td><td>Wahyu Agus</td><td>IT & Data</td>
                        <td><span class="status-badge status-active">Aktif</span></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('mentor', 'Edit Data Mentor')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                    <tr>
                        <td>MTR-002</td><td>Rina Prameswari</td><td>HR Development</td>
                        <td><span class="status-badge status-active">Aktif</span></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('mentor', 'Edit Data Mentor')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    @include('components.crud-modal')
@endpush
