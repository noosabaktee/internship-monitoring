@extends('layouts.app', [
    'title' => 'Edit Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'EDIT PROFILE',
    'pageSubtitle' => 'Perbarui data akun dan informasi profile sendiri.',
    'bodyClass' => 'profile-page',
])

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Edit Data Profile</h2>
            <p>Perbarui informasi utama untuk akun yang sedang login.</p>
        </div>
        <a href="{{ route('profile.show') }}" class="btn-outline"><i class="fa-solid fa-arrow-left"></i> Kembali ke Profile</a>
    </div>

    @if (! $intern && ! $mentor)
        <div class="card" style="text-align:center; padding: 40px;">
            <i class="fa-regular fa-user" style="font-size: 40px; color: var(--text-gray);"></i>
            <h3 style="margin: 15px 0;">Belum ada profile untuk diedit</h3>
        </div>
    @else
        <form action="{{ route('profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <section class="profile-card">
                <div class="profile-card-header">
                    <div class="profile-card-title">
                        <h2>{{ $intern ? 'Informasi Intern' : 'Informasi Mentor' }}</h2>
                        <p>Data ini akan tampil pada halaman profile dan dashboard.</p>
                    </div>
                    <span class="status-badge {{ ($intern?->bitActive ?? $mentor?->bitActive) ? 'status-active' : 'status-inactive' }}">{{ ($intern?->bitActive ?? $mentor?->bitActive) ? 'Aktif' : 'Nonaktif' }}</span>
                </div>

                @if ($intern)
                    <div class="edit-profile-grid">
                        <div class="form-group"><label>ID</label><input class="form-control" name="txtInternNo" value="{{ old('txtInternNo', $intern->txtInternNo) }}"></div>
                        <div class="form-group"><label>Status</label><select class="form-control" name="bitActive"><option value="1" @selected(old('bitActive', (int) $intern->bitActive) == 1)>Aktif</option><option value="0" @selected(old('bitActive', (int) $intern->bitActive) == 0)>Nonaktif</option></select></div>
                        <div class="form-group"><label>Nama Lengkap</label><input class="form-control" name="txtInternName" value="{{ old('txtInternName', $intern->txtInternName) }}" required></div>
                        <div class="form-group"><label>Email</label><input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $intern->user->txtEmail ?? '') }}" required></div>
                        <div class="form-group"><label>Password Baru</label><input class="form-control" type="password" name="txtPassword" placeholder="Kosongkan jika tidak diganti"></div>
                        <div class="form-group"><label>Universitas</label><input class="form-control" name="txtUniversity" value="{{ old('txtUniversity', $intern->txtUniversity) }}"></div>
                        <div class="form-group"><label>Jurusan</label><input class="form-control" name="txtMajor" value="{{ old('txtMajor', $intern->txtMajor) }}"></div>
                        <div class="form-group full"><label>Bio Singkat</label><textarea class="form-control" name="txtBio" rows="4">{{ old('txtBio', $intern->txtBio) }}</textarea></div>
                    </div>
                @endif

                @if ($mentor)
                    <div class="edit-profile-grid">
                        <div class="form-group"><label>Status</label><select class="form-control" name="bitActive"><option value="1" @selected(old('bitActive', (int) $mentor->bitActive) == 1)>Aktif</option><option value="0" @selected(old('bitActive', (int) $mentor->bitActive) == 0)>Nonaktif</option></select></div>
                        <div class="form-group"><label>Nama Lengkap</label><input class="form-control" name="txtMentorName" value="{{ old('txtMentorName', $mentor->txtMentorName) }}" required></div>
                        <div class="form-group"><label>Email</label><input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $mentor->user->txtEmail ?? '') }}" required></div>
                        <div class="form-group"><label>Password Baru</label><input class="form-control" type="password" name="txtPassword" placeholder="Kosongkan jika tidak diganti"></div>
                        <div class="form-group"><label>Department</label><input class="form-control" name="txtDepartment" value="{{ old('txtDepartment', $mentor->txtDepartment) }}"></div>
                        <div class="form-group"><label>Role / Jabatan</label><input class="form-control" name="txtRole" value="{{ old('txtRole', $mentor->txtRole) }}"></div>
                    </div>
                @endif
            </section>

            <div class="modal-footer" style="margin-top: 20px; border: 1px solid var(--border); border-radius: var(--radius);">
                <a href="{{ route('profile.show') }}" class="btn-cancel" style="text-decoration:none;">Batal</a>
                <button type="submit" class="btn-save">Simpan Perubahan</button>
            </div>
        </form>
    @endif
@endsection
