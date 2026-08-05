<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title ?? 'Kalbe Internship Management Dashboard' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Judul yang muncul di preview -->
    <meta property="og:title" content="KMI - Internship Monitoring" />

    <!-- Deskripsi singkat (opsional) -->
    <meta property="og:description" content="Sign in to monitor interns, projects, mentors, exposure scores, and program progress reports in one dashboard." />

    <!-- Gambar preview yang ingin kamu tampilkan -->
    <meta property="og:image" content="{{ asset('images/KDC.png') }}" />

    <!-- URL website kamu -->
    <meta property="og:url" content="https://im.todays.id" />
    
    <meta property="og:type" content="website" />
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $layoutBodyClass = trim(($bodyClass ?? '') . (request()->routeIs('dashboard.index') ? '' : ' sidebar-is-expanded'));
@endphp
<body class="{{ $layoutBodyClass }}">
    @include('components.sidebar')

    <main class="main-wrapper">
        @include('components.topbar', [
            'pageTitle' => $pageTitle ?? 'INTERNSHIP DASHBOARD',
            'pageSubtitle' => $pageSubtitle ?? '<span>Expose</span> &bull; <span>Learn</span> &bull; <span>Grow</span>',
        ])

        <div class="content-area container-fluid">
            @if (session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                    <i class="fa-solid fa-circle-check"></i>
                    <div><p>{{ session('success') }}</p></div>
                </div>
            @endif

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

            @yield('content')
        </div>
    </main>

    @stack('modals')
    @include('components.delete-modal')
</body>
</html>
