@extends('layouts.auth', ['title' => 'Register - Kalbe Internship Dashboard'])

@section('content')
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-brand">
                <i class="fa-solid fa-seedling"></i>
                <span>KALBE INTERN</span>
            </div>

            <div class="auth-hero">
                <h1>Bangun akses untuk kolaborasi internship.</h1>
                <p>Daftarkan akun sebagai intern atau mentor agar data program, project, dan evaluasi bisa dikelola dengan lebih mudah.</p>
            </div>

            <div class="auth-stats">
                <div class="auth-stat"><strong>2</strong><span>Roles</span></div>
                <div class="auth-stat"><strong>4</strong><span>Project Types</span></div>
                <div class="auth-stat"><strong>1</strong><span>Dashboard</span></div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Create Account</h2>
                    <p>Isi data akun baru untuk mulai menggunakan dashboard internship Kalbe.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex gap-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form class="auth-form" action="{{ route('register.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Masukkan nama lengkap" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-user-tag"></i>
                            <select name="txtRole" class="form-control" required>
                                <option value="">Pilih role</option>
                                <option value="Intern" @selected(old('txtRole') === 'Intern')>Intern</option>
                                <option value="Mentor" @selected(old('txtRole') === 'Mentor')>Mentor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" name="txtEmail" class="form-control" value="{{ old('txtEmail') }}" placeholder="nama@kalbe.co.id" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="txtPassword" class="form-control" placeholder="Buat password" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-shield-halved"></i>
                            <input type="password" name="txtPassword_confirmation" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">
                        Register
                        <i class="fa-solid fa-user-plus"></i>
                    </button>
                </form>

                <div class="auth-mini-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Role menentukan akses awal akun pada dashboard internship.</span>
                </div>

                <div class="auth-switch">
                    Sudah punya akun? <a href="{{ route('login') }}" class="auth-link">Login</a>
                </div>
            </div>
        </section>
    </main>
@endsection
