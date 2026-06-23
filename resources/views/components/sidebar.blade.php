@php
    $primaryNavItems = [
        ['route' => 'dashboard.index', 'label' => 'Dashboard', 'icon' => 'fa-solid fa-house'],
        ['route' => 'leaderboard.index', 'active' => 'leaderboard.*', 'label' => 'Leaderboard', 'icon' => 'fa-solid fa-ranking-star'],
    ];
    $secondaryNavItems = [
        ['route' => 'projects.index', 'active' => 'projects.*', 'label' => 'Projects', 'icon' => 'fa-regular fa-calendar-check'],
        ['route' => 'calendar-sharing.index', 'active' => 'calendar-sharing.*', 'label' => 'Calendar Sharing', 'icon' => 'fa-regular fa-calendar-days'],
        ['route' => 'project-handles.index', 'active' => 'project-handles.*', 'label' => 'Project Handle', 'icon' => 'fa-solid fa-sliders'],
        ['route' => 'exposure.index', 'active' => 'exposure.*', 'label' => 'Exposure', 'icon' => 'fa-solid fa-chart-area'],
        ['route' => 'analytics.index', 'active' => 'analytics.*', 'label' => 'Analytics', 'icon' => 'fa-solid fa-chart-line'],
        ['route' => 'achievements.index', 'active' => 'achievements.*', 'label' => 'Achievements', 'icon' => 'fa-solid fa-trophy'],
        ['route' => 'reports.index', 'active' => 'reports.*', 'label' => 'Reports', 'icon' => 'fa-regular fa-file-lines'],
        ['route' => 'settings.index', 'active' => 'settings.*', 'label' => 'Settings', 'icon' => 'fa-solid fa-gear'],
    ];
    $masterDataItems = [
        ['route' => 'skill-sets.index', 'active' => 'skill-sets.*', 'label' => 'Skill Set', 'icon' => 'fa-solid fa-layer-group'],
        ['route' => 'mentors.index', 'active' => 'mentors.*', 'label' => 'Mentors', 'icon' => 'fa-solid fa-user-tie'],
        ['route' => 'interns.index', 'active' => 'interns.*', 'label' => 'Interns', 'icon' => 'fa-solid fa-user-group'],
    ];
    $isMasterDataActive = collect($masterDataItems)->contains(fn ($item) => request()->routeIs($item['active']));
@endphp

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <div class="logo-area" onclick="toggleSidebar()">
        <img class="sidebar-logo" src="{{ asset('images/KDC.png') }}" alt="KDC">
    </div>

    <ul class="nav-menu">
        @foreach ($primaryNavItems as $item)
            <li class="nav-item {{ request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}">
                <a class="nav-link d-flex align-items-center" href="{{ route($item['route']) }}">
                    <i class="{{ $item['icon'] }}"></i>
                    <span class="nav-text">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
        <li class="nav-item nav-dropdown {{ $isMasterDataActive ? 'active open' : '' }}" id="masterDataDropdown">
            <button class="nav-link nav-dropdown-toggle d-flex align-items-center" type="button" onclick="toggleNavDropdown('masterDataDropdown')" aria-expanded="{{ $isMasterDataActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-database"></i>
                <span class="nav-text">Master Data</span>
                <i class="fa-solid fa-chevron-down nav-caret"></i>
            </button>
            <ul class="nav-submenu">
                @foreach ($masterDataItems as $item)
                    <li class="nav-subitem {{ request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}">
                        <a class="nav-sublink d-flex align-items-center" href="{{ route($item['route']) }}">
                            <i class="{{ $item['icon'] }}"></i>
                            <span class="nav-text">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </li>
        @foreach ($secondaryNavItems as $item)
            <li class="nav-item {{ request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}">
                <a class="nav-link d-flex align-items-center" href="{{ route($item['route']) }}">
                    <i class="{{ $item['icon'] }}"></i>
                    <span class="nav-text">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div style="flex-grow: 1;"></div>
    <div class="promo-banner">
        <i class="fa-solid fa-quote-left"></i>
        <p>"Great interns today, future leaders tomorrow."</p>
    </div>
</aside>
