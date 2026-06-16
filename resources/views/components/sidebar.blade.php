@php
    $navItems = [
        ['route' => 'dashboard.index', 'label' => 'Dashboard', 'icon' => 'fa-solid fa-house'],
        ['route' => 'leaderboard.index', 'active' => 'leaderboard.*', 'label' => 'Leaderboard', 'icon' => 'fa-solid fa-ranking-star'],
        ['route' => 'interns.index', 'active' => 'interns.*', 'label' => 'Interns', 'icon' => 'fa-solid fa-user-group'],
        ['route' => 'projects.index', 'active' => 'projects.*', 'label' => 'Projects', 'icon' => 'fa-regular fa-calendar-check'],
        ['route' => 'mentors.index', 'active' => 'mentors.*', 'label' => 'Mentors', 'icon' => 'fa-solid fa-user-tie'],
        ['route' => 'analytics.index', 'active' => 'analytics.*', 'label' => 'Analytics', 'icon' => 'fa-solid fa-chart-line'],
        ['route' => 'achievements.index', 'active' => 'achievements.*', 'label' => 'Achievements', 'icon' => 'fa-solid fa-trophy'],
        ['route' => 'reports.index', 'active' => 'reports.*', 'label' => 'Reports', 'icon' => 'fa-regular fa-file-lines'],
        ['route' => 'settings.index', 'active' => 'settings.*', 'label' => 'Settings', 'icon' => 'fa-solid fa-gear'],
    ];
@endphp

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <div class="logo-area" onclick="toggleSidebar()">
        <i class="fa-solid fa-dna" style="transform: rotate(45deg);"></i>
        <span class="logo-text">KALBE</span>
    </div>

    <ul class="nav-menu">
        @foreach ($navItems as $item)
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
