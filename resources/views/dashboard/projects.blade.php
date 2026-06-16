@extends('layouts.app', [
    'title' => 'Projects - Kalbe Internship Dashboard',
    'pageTitle' => 'PROJECTS',
    'pageSubtitle' => 'Manajemen data Projects Kalbe.',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Projects</h2>
            <p>Pantau status dan PIC untuk Collaboration, Main, Satellite, dan Sharing.</p>
        </div>
        <button class="btn-add" onclick="openCrudModal('project', 'Tambah Project Baru')"><i class="fa-solid fa-plus"></i> Tambah Project</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Nama Project</th><th>Tipe</th><th>PIC / Mentor</th><th>Progress</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CFD Optimization</strong></td><td>Main</td><td>Wahyu Agus</td>
                        <td><div style="background:var(--border); border-radius:3px; height:8px; min-width:80px;"><div style="background:var(--secondary); width:80%; height:100%; border-radius:3px;"></div></div></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('project', 'Edit Project')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                    <tr>
                        <td><strong>Digital Twin Dashboard</strong></td><td>Satellite</td><td>Rina Prameswari</td>
                        <td><div style="background:var(--border); border-radius:3px; height:8px; min-width:80px;"><div style="background:var(--secondary); width:74%; height:100%; border-radius:3px;"></div></div></td>
                        <td><div class="action-btns"><button class="btn-icon btn-edit" onclick="openCrudModal('project', 'Edit Project')"><i class="fa-solid fa-pen"></i></button><button class="btn-icon btn-delete"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    @include('components.crud-modal')
@endpush
