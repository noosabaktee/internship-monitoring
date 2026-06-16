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
                <img src="https://ui-avatars.com/api/?name=Admin+Kalbe&background=8CC63F&color=fff" alt="Admin">
                <div class="user-info">
                    <div class="name">Admin</div>
                    <div class="role">HR Development</div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--text-gray);"></i>
            </button>
            <div class="profile-dropdown" id="profileDropdown">
                <button type="button" onclick="window.location.href='{{ route('profile.show') }}'"><i class="fa-regular fa-user"></i> Profile</button>
                <button type="button" class="logout" onclick="window.location.href='{{ route('login') }}'"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
            </div>
        </div>
    </div>
</header>
