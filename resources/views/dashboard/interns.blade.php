@extends('layouts.app', [
    'title' => 'Interns - Kalbe Internship Dashboard',
    'pageTitle' => 'INTERNS',
    'pageSubtitle' => 'Manajemen data Interns Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Interns</h2>
            <p>Kelola data profil, universitas, dan status peserta magang.</p>
        </div>
        <button class="btn-add" onclick="openCrudModal('intern', 'Tambah Intern Baru')"><i class="fa-solid fa-plus"></i> Tambah Data</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Nama Lengkap</th><th>Universitas</th><th>Jurusan</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>INT-001</td><td>Christopher Rey W.</td><td>Universitas Indonesia</td><td>Teknik Industri</td>
                        <td><span class="status-badge status-active">Aktif</span></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('intern', 'Edit Data Intern')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                    <tr>
                        <td>INT-002</td><td>Humaira Zeanova</td><td>Institut Teknologi Bandung</td><td>Teknik Kimia</td>
                        <td><span class="status-badge status-active">Aktif</span></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('intern', 'Edit Data Intern')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    @include('components.crud-modal')
@endpush
