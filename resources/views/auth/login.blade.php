@extends('layouts.auth', ['title' => 'Login - Kalbe Internship Dashboard'])

@section('content')
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-brand">
                <i class="fa-solid fa-seedling"></i>
                <span>KALBE INTERN</span>
            </div>

            <div class="auth-hero">
                <h1>Kelola internship dengan ritme yang lebih rapi.</h1>
                <p>Masuk untuk memantau interns, project, mentor, exposure score, dan laporan perkembangan program dalam satu dashboard.</p>
            </div>

            <div class="auth-stats">
                <div class="auth-stat"><strong>10</strong><span>Active Interns</span></div>
                <div class="auth-stat"><strong>6</strong><span>Projects</span></div>
                <div class="auth-stat"><strong>80.7</strong><span>Avg Score</span></div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Welcome Back</h2>
                    <p>Login menggunakan akun yang terdaftar untuk membuka dashboard internship.</p>
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

                <form class="auth-form" action="{{ route('login.authenticate') }}" method="POST">
                    @csrf
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
                            <input type="password" name="txtPassword" class="form-control" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <div class="auth-options d-flex align-items-center justify-content-between gap-3">
                        <label><input type="checkbox"> Ingat saya</label>
                        <a href="#" class="auth-link">Lupa password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">
                        Login
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <div class="auth-switch">
                    Belum punya akun? <a href="{{ route('register') }}" class="auth-link">Daftar sekarang</a>
                </div>
            </div>
        </section>
    </main>
@endsection
