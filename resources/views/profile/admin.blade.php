@extends('layouts.app', [
    'title' => 'Profile - Kalbe Internship Dashboard',
    'pageTitle' => 'PROFILE',
    'pageSubtitle' => 'Account and role details.',
    'bodyClass' => 'profile-page',
])

@php
    $adminProfile = $user->adminProfile;
    $displayName = $adminProfile?->txtAdminProfileName ?? $user->txtEmail ?? 'Admin';
    $profilePhotoUrl = $user->txtProfilePhoto
        ? asset('storage/' . $user->txtProfilePhoto)
        : 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=006838&color=fff&size=160';
@endphp

@section('content')
    <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Admin Profile</h2>
            <p>Role access for internship monitoring administration.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-add" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Edit Profile</a>
    </div>

    <div class="profile-page-wrap">
        <section class="profile-hero profile-hero-admin">
            <div class="profile-identity">
                <div class="profile-avatar-wrap">
                    <img class="profile-avatar" src="{{ $profilePhotoUrl }}" alt="{{ $displayName }}">
                    <form class="profile-photo-form" action="{{ route('profile.photo.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label class="profile-photo-button" title="Upload profile photo">
                            <i class="fa-solid fa-camera"></i>
                            <input type="file" name="txtProfilePhoto" accept="image/*" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
                <div class="profile-name">
                    <h1>{{ $displayName }}</h1>
                    <p>{{ $adminProfile?->txtAdminProfileBio ?: $user->txtRole . ' account for monitoring internship attendance, master data, and reporting access.' }}</p>
                    <div class="profile-meta">
                        <span class="profile-pill"><i class="fa-regular fa-envelope"></i> {{ $user->txtEmail }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-user-shield"></i> {{ $user->txtRole }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-venus-mars"></i> {{ $adminProfile?->txtAdminProfileGender ?: '-' }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-building"></i> {{ $adminProfile?->txtAdminProfileDepartment ?: '-' }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-id-badge"></i> {{ $adminProfile?->txtAdminProfilePosition ?: $user->txtRole }}</span>
                        <span class="profile-pill"><i class="fa-solid fa-circle-check"></i> {{ $user->bitActive ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="profile-metrics">
            <div class="profile-metric"><i class="fa-solid fa-user-shield"></i><strong>{{ $user->txtRole }}</strong><span>Role</span></div>
            <div class="profile-metric"><i class="fa-solid fa-building"></i><strong>{{ $adminProfile?->txtAdminProfileDepartment ?: '-' }}</strong><span>Department</span></div>
            <div class="profile-metric"><i class="fa-solid fa-id-badge"></i><strong>{{ $adminProfile?->txtAdminProfilePosition ?: '-' }}</strong><span>Position</span></div>
            <div class="profile-metric"><i class="fa-solid fa-phone"></i><strong>{{ $adminProfile?->txtAdminProfilePhone ?: '-' }}</strong><span>Phone</span></div>
        </section>

        <section class="profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title">
                    <h2>Account Details</h2>
                    <p>Primary profile data for monitoring administration.</p>
                </div>
                <span class="status-badge {{ ($adminProfile?->bitActive ?? $user->bitActive) ? 'status-active' : 'status-inactive' }}">{{ ($adminProfile?->bitActive ?? $user->bitActive) ? 'Active' : 'Inactive' }}</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><span class="profile-pill"><i class="fa-regular fa-user"></i> {{ $displayName }}</span></div>
                <div class="col-md-4"><span class="profile-pill"><i class="fa-regular fa-envelope"></i> {{ $user->txtEmail }}</span></div>
                <div class="col-md-4"><span class="profile-pill"><i class="fa-solid fa-phone"></i> {{ $adminProfile?->txtAdminProfilePhone ?: '-' }}</span></div>
            </div>
        </section>
    </div>
@endsection
