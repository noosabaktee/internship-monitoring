@php
    $authUser = \App\Models\MUser::with('intern')->find(session('auth_user_id'));
    $can = fn (string $ability) => $authUser && \App\Support\RoleAccess::can($authUser, $ability);
    $navItems = [
        ['ability' => 'dashboard-sidebar', 'route' => 'dashboard.index', 'label' => 'Dashboard', 'icon' => 'fa-solid fa-house'],
        ['ability' => 'leaderboard', 'route' => 'leaderboard.index', 'active' => 'leaderboard.*', 'label' => 'Leaderboard', 'icon' => 'fa-solid fa-ranking-star'],
        ['ability' => 'projects', 'route' => 'projects.index', 'active' => 'projects.*', 'label' => 'Projects', 'icon' => 'fa-regular fa-calendar-check'],
        ['ability' => 'attendance', 'route' => 'attendance.index', 'active' => 'attendance.*', 'label' => 'Absensi', 'icon' => 'fa-solid fa-user-check'],
        ['ability' => 'work-from-home', 'route' => 'work-from-home.index', 'active' => 'work-from-home.*', 'label' => 'WFH / Izin / Sakit', 'icon' => 'fa-solid fa-house-laptop'],
        ['ability' => 'calendar-sharing', 'route' => 'calendar-sharing.index', 'active' => 'calendar-sharing.*', 'label' => 'Calendar Sharing', 'icon' => 'fa-regular fa-calendar-days'],
        ['ability' => 'project-handles', 'route' => 'project-handles.index', 'active' => 'project-handles.*', 'label' => 'Project Handle', 'icon' => 'fa-solid fa-sliders'],
        ['ability' => 'exposure', 'route' => 'exposure.index', 'active' => 'exposure.*', 'label' => 'Exposure', 'icon' => 'fa-solid fa-chart-area'],
        ['ability' => 'analytics', 'route' => 'analytics.index', 'active' => 'analytics.*', 'label' => 'Rapor Intern', 'icon' => 'fa-solid fa-graduation-cap'],
        ['ability' => 'achievements', 'route' => 'achievements.index', 'active' => 'achievements.*', 'label' => 'Achievements', 'icon' => 'fa-solid fa-trophy'],
        ['ability' => 'reports', 'route' => 'reports.index', 'active' => 'reports.*', 'label' => 'Reports', 'icon' => 'fa-regular fa-file-lines'],
        //['ability' => 'settings', 'route' => 'settings.index', 'active' => 'settings.*', 'label' => 'Settings', 'icon' => 'fa-solid fa-gear'],
    ];
    $masterDataItems = [
        ['ability' => 'master-data', 'route' => 'skill-sets.index', 'active' => 'skill-sets.*', 'label' => 'Skill Set', 'icon' => 'fa-solid fa-layer-group'],
        ['ability' => 'master-data', 'route' => 'mentors.index', 'active' => 'mentors.*', 'label' => 'Mentors', 'icon' => 'fa-solid fa-user-tie'],
        ['ability' => 'master-data', 'route' => 'interns.index', 'active' => 'interns.*', 'label' => 'Interns', 'icon' => 'fa-solid fa-user-group'],
        ['ability' => 'hrd-data', 'route' => 'hrds.index', 'active' => 'hrds.*', 'label' => 'HRD / Headmaster', 'icon' => 'fa-solid fa-user-shield'],
    ];
    $navItems = collect($navItems)->filter(fn ($item) => $can($item['ability']))->values();
    $masterDataItems = collect($masterDataItems)->filter(fn ($item) => $can($item['ability']))->values();
    $masterDataAnchor = 'leaderboard';
    $masterDataInserted = false;
    $menuItems = collect();
    foreach ($navItems as $item) {
        $menuItems->push($item);

        if (! $masterDataInserted && $masterDataItems->isNotEmpty() && $item['ability'] === $masterDataAnchor) {
            $menuItems->push(['type' => 'master-data']);
            $masterDataInserted = true;
        }
    }

    if (! $masterDataInserted && $masterDataItems->isNotEmpty()) {
        $menuItems->push(['type' => 'master-data']);
    }
    $isMasterDataActive = collect($masterDataItems)->contains(fn ($item) => request()->routeIs($item['active']));
@endphp

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <div class="logo-area" onclick="toggleSidebar()">
        <img class="sidebar-logo" src="{{ asset('images/KDC.png') }}" alt="KDC">
    </div>

    <ul class="nav-menu">
        @foreach ($menuItems as $item)
            @if (($item['type'] ?? null) === 'master-data')
                <li class="nav-item nav-dropdown {{ $isMasterDataActive ? 'active open' : '' }}" id="masterDataDropdown">
                    <button class="nav-link nav-dropdown-toggle d-flex align-items-center" type="button" onclick="toggleNavDropdown('masterDataDropdown')" aria-expanded="{{ $isMasterDataActive ? 'true' : 'false' }}">
                        <i class="fa-solid fa-database"></i>
                        <span class="nav-text">Master Data</span>
                        <i class="fa-solid fa-chevron-down nav-caret"></i>
                    </button>
                    <ul class="nav-submenu">
                        @foreach ($masterDataItems as $masterItem)
                            <li class="nav-subitem {{ request()->routeIs($masterItem['active'] ?? $masterItem['route']) ? 'active' : '' }}">
                                <a class="nav-sublink d-flex align-items-center" href="{{ route($masterItem['route']) }}">
                                    <i class="{{ $masterItem['icon'] }}"></i>
                                    <span class="nav-text">{{ $masterItem['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li class="nav-item {{ request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}">
                    <a class="nav-link d-flex align-items-center" href="{{ route($item['route']) }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="nav-text">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>

    <div style="flex-grow: 1;"></div>
    <div class="promo-banner">
        <i class="fa-solid fa-quote-left"></i>
        <p>"Great interns today, future leaders tomorrow."</p>
    </div>
</aside>
