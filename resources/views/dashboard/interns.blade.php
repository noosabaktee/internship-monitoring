@extends('layouts.app', [
    'title' => 'Interns - Kalbe Internship Dashboard',
    'pageTitle' => 'INTERNS',
    'pageSubtitle' => 'Manajemen data Interns Kalbe.',
])

@php
    $isFormOpen = in_array($mode ?? null, ['create', 'edit'], true);
    $formAction = isset($editingIntern)
        ? route('interns.update', $editingIntern->intIntern_ID)
        : route('interns.store');
    $isMentor = optional(\App\Models\MUser::find(session('auth_user_id')))->txtRole === 'Mentor';
@endphp

@section('content')
    <div class="page-crud-header">
        <div>
            <h2>Data Interns</h2>
            <p>Kelola data profil, universitas, dan status peserta magang.</p>
        </div>
        @if ($isMentor)
            <a class="btn-add" href="{{ route('interns.create') }}" style="text-decoration:none;"><i class="fa-solid fa-plus"></i> Tambah Data</a>
        @endif
    </div>

    @if ($isFormOpen)
        <section class="profile-card" style="margin-bottom: 20px;">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>{{ isset($editingIntern) ? 'Edit Intern' : 'Tambah Intern Baru' }}</h2>
                    <p>Email dan password disimpan di mUser, profil intern disimpan di mIntern.</p>
                </div>
                <a href="{{ route('interns.index') }}" class="btn-outline"><i class="fa-solid fa-xmark"></i> Tutup</a>
            </div>

            <form class="edit-profile-grid" action="{{ $formAction }}" method="POST">
                @csrf
                @isset($editingIntern)
                    @method('PUT')
                @endisset

                <div class="form-group">
                    <label>No Intern</label>
                    <input class="form-control" name="txtInternNo" value="{{ old('txtInternNo', $editingIntern->txtInternNo ?? '') }}" placeholder="Contoh: INT-001">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" name="bitActive">
                        <option value="1" @selected((string) old('bitActive', (int) ($editingIntern->bitActive ?? true)) === '1')>Aktif</option>
                        <option value="0" @selected((string) old('bitActive', (int) ($editingIntern->bitActive ?? true)) === '0')>Nonaktif</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input class="form-control" name="txtInternName" value="{{ old('txtInternName', $editingIntern->txtInternName ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input class="form-control" type="email" name="txtEmail" value="{{ old('txtEmail', $editingIntern->user->txtEmail ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label>Password {{ isset($editingIntern) ? '(kosongkan jika tidak diganti)' : '' }}</label>
                    <input class="form-control" type="password" name="txtPassword" placeholder="{{ isset($editingIntern) ? 'Password baru' : 'Default: password' }}">
                </div>
                <div class="form-group">
                    <label>Universitas</label>
                    <input class="form-control" name="txtUniversity" value="{{ old('txtUniversity', $editingIntern->txtUniversity ?? '') }}">
                </div>
                <div class="form-group">
                    <label>Jurusan</label>
                    <input class="form-control" name="txtMajor" value="{{ old('txtMajor', $editingIntern->txtMajor ?? '') }}">
                </div>
                <div class="form-group full">
                    <label>Bio</label>
                    <textarea class="form-control" name="txtBio" rows="3">{{ old('txtBio', $editingIntern->txtBio ?? '') }}</textarea>
                </div>
                <div class="form-group full">
                    <button class="btn-save" type="submit">{{ isset($editingIntern) ? 'Simpan Perubahan' : 'Simpan Intern' }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Nama Lengkap</th><th>Email</th><th>Universitas</th><th>Jurusan</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($interns as $intern)
                        <tr>
                            <td>{{ $intern->txtInternNo ?: 'INT-' . str_pad((string) $intern->intIntern_ID, 3, '0', STR_PAD_LEFT) }}</td>
                            <td><a class="auth-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">{{ $intern->txtInternName }}</a></td>
                            <td>{{ $intern->user->txtEmail ?? '-' }}</td>
                            <td>{{ $intern->txtUniversity ?: '-' }}</td>
                            <td>{{ $intern->txtMajor ?: '-' }}</td>
                            <td><span class="status-badge {{ $intern->bitActive ? 'status-active' : 'status-inactive' }}">{{ $intern->bitActive ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                @if ($isMentor)
                                    <div class="action-btns">
                                        <a class="btn-icon btn-edit" href="{{ route('interns.edit', $intern->intIntern_ID) }}"><i class="fa-solid fa-pen"></i></a>
                                        <form action="{{ route('interns.destroy', $intern->intIntern_ID) }}" method="POST" onsubmit="return confirm('Nonaktifkan intern ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-icon btn-delete" type="submit"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <a class="auth-link" href="{{ route('profile.intern.show', $intern->intIntern_ID) }}">Lihat</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="center">Belum ada data intern.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
