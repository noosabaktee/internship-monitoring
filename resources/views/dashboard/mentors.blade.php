@extends('layouts.app', [
    'title' => 'Mentors - Kalbe Internship Dashboard',
    'pageTitle' => 'MENTORS',
    'pageSubtitle' => 'Manajemen data Mentors Kalbe.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingMentor)
        ? route('mentors.update', $editingMentor->intMentor_ID)
        : route('mentors.store');
    $isMentorUser = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Mentors</h2>
            <p>Kelola data mentor berdasarkan departemen dan status pendampingan.</p>
        </div>
        @if ($isMentorUser)
            <a class="btn-add" href="{{ route('mentors.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Tambah Mentor</a>
        @endif
    </div>

    @if ($isFormOpen)
        <section class="profile-card" style="margin-bottom: 20px;">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>{{ isset($editingMentor) ? 'Edit Mentor' : 'Tambah Mentor Baru' }}</h2>
                    <p>Email dan password disimpan di mUser, profil mentor disimpan di mMentor.</p>
                </div>
                <a href="{{ route('mentors.index') }}" class="btn-outline"><i class="fa-solid fa-xmark"></i> Tutup</a>
            </div>

            <form class="edit-profile-grid" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingMentor)
                    @method('PUT')
                @endisset

                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingMentor->bitActive ?? true)) === '1')>Aktif</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingMentor->bitActive ?? true)) === '0')>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input class="form-control" name="txtMentorName" value="{{ old('txtMentorName', $editingMentor->txtMentorName ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $editingMentor->user->txtEmail ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Password {{ isset($editingMentor) ? '(kosongkan jika tidak diganti)' : '' }}</label>
                    <input class="form-control" type="password" name="txtPassword" placeholder="{{ isset($editingMentor) ? 'Password baru' : 'Default: password' }}">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input class="form-control" name="txtDepartment" value="{{ old('txtDepartment', $editingMentor->txtDepartment ?? '') }}">
                </div>
                <div class="form-group">
                    <label>Role / Jabatan</label>
                    <input class="form-control" name="txtRole" value="{{ old('txtRole', $editingMentor->txtRole ?? 'Mentor') }}">
                </div>
                <div class="form-group full">
                    <button class="btn-save" type="submit">{{ isset($editingMentor) ? 'Simpan Perubahan' : 'Simpan Mentor' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Nama Lengkap</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($mentors as $mentor)
                        <tr>
                            <td>MTR-{{ str_pad((string) $mentor->intMentor_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td><a class="auth-link" href="{{ route('profile.mentor.show', $mentor->intMentor_ID) }}">{{ $mentor->txtMentorName }}</a></td>
                            <td>{{ $mentor->user->txtEmail ?? '-' }}</td>
                            <td>{{ $mentor->txtDepartment ?: '-' }}</td>
                            <td>{{ $mentor->txtRole ?: '-' }}</td>
                            <td><span class="status-badge {{ $mentor->bitActive ? 'status-active' : 'status-inactive' }}">{{ $mentor->bitActive ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                @if ($isMentorUser)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('mentors.edit', $mentor->intMentor_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('mentors.destroy', $mentor->intMentor_ID) }}" method="POST" onsubmit="return confirm('Nonaktifkan mentor ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <a class="auth-link" href="{{ route('profile.mentor.show', $mentor->intMentor_ID) }}">Lihat</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="center">Belum ada data mentor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
