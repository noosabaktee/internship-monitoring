@php
    $authUser = \App\Models\MUser::with(['intern', 'mentor'])->find(session('auth_user_id'));
    $displayName = $authUser?->intern?->txtInternName
        ?? $authUser?->mentor?->txtMentorName
        ?? 'Admin';
    $displayRole = $authUser?->txtRole ?? 'HR Development';
@endphp

<header class="topbar">
    <div class="header-left">
        <i class="fa-solid fa-bars menu-toggle" onclick="toggleSidebar()"></i>
        <div class="page-title" id="dynamic-title">
            <h1>{{ $pageTitle }}</h1>
            <p>{!! $pageSubtitle !!}</p>
        </div>
    </div>

    <div class="header-right">
        <div class="date-picker" onclick="openDatePicker()">
            <i class="fa-regular fa-calendar"></i>
            <input type="month" id="topbarDate" aria-label="Pilih bulan">
        </div>
        <button class="btn-export"><i class="fa-solid fa-download"></i> Export</button>
        <div class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Light/Dark Mode">
            <i class="fa-solid fa-moon"></i>
        </div>
        <div class="user-profile">
            <i class="fa-regular fa-bell" style="font-size: 20px; color: var(--text-gray); margin-right: 5px; cursor: pointer;"></i>
            <button class="profile-trigger" id="profileTrigger" type="button" aria-haspopup="true" aria-expanded="false">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($displayName) }}&background=8CC63F&color=fff" alt="{{ $displayName }}">
                <div class="user-info">
                    <div class="name">{{ $displayName }}</div>
                    <div class="role">{{ $displayRole }}</div>
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
