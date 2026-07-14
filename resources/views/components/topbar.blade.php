@php
    $authUser = \App\Models\MUser::with(['intern', 'mentor', 'adminProfile'])->find(session('auth_user_id'));
    $displayName = $authUser?->intern?->txtInternName
        ?? $authUser?->mentor?->txtMentorName
        ?? $authUser?->adminProfile?->txtAdminProfileName
        ?? $authUser?->txtEmail
        ?? 'Admin';
    $displayRole = $authUser?->txtRole ?? 'HR Development';
    $avatarUrl = $authUser?->txtProfilePhoto
        ? asset('storage/' . $authUser->txtProfilePhoto)
        : 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=8CC63F&color=fff';
@endphp

<header class="topbar d-flex align-items-center justify-content-between">
    <div class="header-left d-flex align-items-center gap-3">
        <i class="fa-solid fa-bars menu-toggle" onclick="toggleSidebar()"></i>
        <div class="page-title" id="dynamic-title">
            <h1>{{ $pageTitle }}</h1>
            <p>{!! $pageSubtitle !!}</p>
        </div>
    </div>

    <div class="header-right d-flex align-items-center gap-3">
        <div class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Light/Dark Mode">
            <i class="fa-solid fa-moon"></i>
        </div>
        <div class="user-profile d-flex align-items-center gap-2">
            <i class="fa-regular fa-bell" style="font-size: 20px; color: var(--text-gray); margin-right: 5px; cursor: pointer;"></i>
            <button class="profile-trigger" id="profileTrigger" type="button" aria-haspopup="true" aria-expanded="false">
                <img src="{{ $avatarUrl }}" alt="{{ $displayName }}">
                <div class="user-info">
                        <div class="name fw-semibold">{{ $displayName }}</div>
                        <div class="role small">{{ $displayRole }}</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--text-gray);"></i>
            </button>
            <div class="profile-dropdown" id="profileDropdown">
                <button type="button" onclick="window.location.href='{{ route('profile.show') }}'"><i class="fa-regular fa-user"></i> Profile</button>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
