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

                <form class="auth-form" action="{{ route('login') }}">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" class="form-control" placeholder="Masukkan nama lengkap" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-user-tag"></i>
                            <select class="form-control" required>
                                <option value="">Pilih role</option>
                                <option value="Intern">Intern</option>
                                <option value="Mentor">Mentor</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" class="form-control" placeholder="nama@kalbe.co.id" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" class="form-control" placeholder="Buat password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-shield-halved"></i>
                            <input type="password" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit">
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
