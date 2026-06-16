<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title ?? 'Kalbe Internship Management Dashboard' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass ?? '' }}">
    @include('components.sidebar')

    <main class="main-wrapper">
        @include('components.topbar', [
            'pageTitle' => $pageTitle ?? 'INTERNSHIP DASHBOARD',
            'pageSubtitle' => $pageSubtitle ?? '<span>Expose</span> &bull; <span>Learn</span> &bull; <span>Grow</span>',
        ])

        <div class="content-area">
            @yield('content')
        </div>
    </main>

    @stack('modals')
</body>
</html>
