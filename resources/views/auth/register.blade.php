@extends('layouts.auth', ['title' => 'Register - Kalbe Internship Dashboard'])

@section('content')
    <main class="auth-shell">
        <section class="auth-visual">
            <div class="auth-brand">
                <i class="fa-solid fa-seedling"></i>
                <span>KALBE INTERN</span>
            </div>

            <div class="auth-hero">
                <h1>Create access for internship collaboration.</h1>
                <p>Register your internship access so attendance, project, and monitoring data can be managed more easily.</p>
            </div>

            <div class="auth-stats">
                <div class="auth-stat"><strong>4</strong><span>Roles</span></div>
                <div class="auth-stat"><strong>4</strong><span>Project Types</span></div>
                <div class="auth-stat"><strong>1</strong><span>Dashboard</span></div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Create Account</h2>
                    <p>Enter your account details to start using the Kalbe internship dashboard.</p>
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
                        <label class="form-label">Full Name</label>
                        <div class="auth-input-wrap">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter full name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-user-tag"></i>
                            <select name="txtRole" class="form-control" required>
                                <option value="">Select role</option>
                                <option value="Intern" @selected(old('txtRole') === 'Intern')>Intern</option>
                                <option value="Mentor" @selected(old('txtRole') === 'Mentor')>Mentor</option>
                                <option value="HRD" @selected(old('txtRole') === 'HRD')>HRD</option>
                                <option value="Headmaster" @selected(old('txtRole') === 'Headmaster')>Headmaster</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-venus-mars"></i>
                            <select name="txtGender" class="form-control">
                                <option value="">Select gender</option>
                                @foreach (['Laki-laki' => 'Male', 'Perempuan' => 'Female'] as $genderValue => $genderLabel)
                                    <option value="{{ $genderValue }}" @selected(old('txtGender') === $genderValue)>{{ $genderLabel }}</option>
                                @endforeach
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
                            <input type="password" name="txtPassword" class="form-control" placeholder="Create password" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="auth-input-wrap">
                            <i class="fa-solid fa-shield-halved"></i>
                            <input type="password" name="txtPassword_confirmation" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">
                        Register
                        <i class="fa-solid fa-user-plus"></i>
                    </button>
                </form>

                <div class="auth-mini-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Your role determines your initial dashboard access.</span>
                </div>

                <div class="auth-switch">
                    Already have an account? <a href="{{ route('login') }}" class="auth-link">Login</a>
                </div>
            </div>
        </section>
    </main>
@endsection
