@extends('layouts.app', [
    'title' => 'Edit Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'EDIT PROFILE',
    'pageSubtitle' => 'Perbarui data intern dan informasi program.',
    'bodyClass' => 'profile-page',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Edit Data Profile</h2>
            <p>Perbarui informasi utama intern, mentor, kontak, dan detail program.</p>
        </div>
        <a href="{{ route('profile.show') }}" class="btn-outline"><i class="fa-solid fa-arrow-left"></i> Kembali ke Profile</a>
    </div>

    <section class="profile-card">
        <div class="profile-card-header">
            <div class="profile-card-title">
                <h2>Informasi Intern</h2>
                <p>Data ini akan tampil pada halaman profile dan dashboard intern.</p>
            </div>
            <span class="status-badge status-active">Aktif</span>
        </div>

        <form class="edit-profile-grid">
            <div class="form-group"><label>ID</label><input class="form-control" type="text" value="INT-001"></div>
            <div class="form-group"><label>Status</label><select class="form-control"><option>Aktif</option><option>Nonaktif</option></select></div>
            <div class="form-group"><label>Nama Lengkap</label><input class="form-control" type="text" value="Christopher Rey W."></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" value="christopher.rey@kalbe.co.id"></div>
            <div class="form-group"><label>Universitas</label><input class="form-control" type="text" value="Universitas Indonesia"></div>
            <div class="form-group"><label>Jurusan</label><input class="form-control" type="text" value="Teknik Industri"></div>
            <div class="form-group"><label>Department</label><input class="form-control" type="text" value="Manufacturing Excellence"></div>
            <div class="form-group"><label>Mentor Utama</label><select class="form-control"><option>Wahyu Agus</option><option>Rina Prameswari</option></select></div>
            <div class="form-group"><label>Exposure Score</label><input class="form-control" type="number" min="0" max="100" value="95"></div>
            <div class="form-group"><label>Current Level</label><select class="form-control"><option>A - High</option><option>B - Medium</option><option>C - Low</option><option>D - Very Low</option></select></div>
            <div class="form-group full"><label>Bio Singkat</label><textarea class="form-control" rows="4">Fokus pada process improvement, simulation workflow, dan kolaborasi project lintas fungsi.</textarea></div>
        </form>
    </section>

    <div class="profile-section-grid">
        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Project Assignment</h2>
                    <p>Update project yang sedang dikerjakan intern.</p>
                </div>
            </div>
            <form class="edit-profile-grid">
                <div class="form-group"><label>Main Project</label><input class="form-control" type="text" value="CFD Optimization"></div>
                <div class="form-group"><label>Main Progress (%)</label><input class="form-control" type="number" min="0" max="100" value="80"></div>
                <div class="form-group"><label>Collaboration Project</label><input class="form-control" type="text" value="Line Balancing Workshop"></div>
                <div class="form-group"><label>Satellite Project</label><input class="form-control" type="text" value="Digital Twin Dashboard"></div>
                <div class="form-group full"><label>Sharing Activity</label><input class="form-control" type="text" value="Process Simulation Sharing"></div>
            </form>
        </section>

        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Achievements</h2>
                    <p>Catatan pencapaian yang tampil di profile.</p>
                </div>
            </div>
            <form>
                <div class="form-group"><label>Achievement 1</label><input class="form-control" type="text" value="Top Performer"></div>
                <div class="form-group"><label>Achievement 2</label><input class="form-control" type="text" value="Best Improvement Idea"></div>
                <div class="form-group"><label>Achievement 3</label><input class="form-control" type="text" value="Collaboration Champion"></div>
            </form>
        </section>
    </div>

    <div class="modal-footer" style="margin-top: 20px; border: 1px solid var(--border); border-radius: var(--radius);">
        <a href="{{ route('profile.show') }}" class="btn-cancel" style="text-decoration:none;">Batal</a>
        <button type="button" class="btn-save" onclick="alert('Data profile berhasil disimpan!')">Simpan Perubahan</button>
    </div>
@endsection
