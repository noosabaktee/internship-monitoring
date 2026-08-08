<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title ?? 'Kalbe Internship Dashboard' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Judul yang muncul di preview -->
    <meta property="og:title" content="KMI - Internship Monitoring" />

    <!-- Deskripsi singkat (opsional) -->
    <meta property="og:description" content="Sign in to monitor interns, projects, mentors, exposure scores, and program progress reports in one dashboard." />

    <!-- Gambar preview yang ingin kamu tampilkan -->
    <meta property="og:image" content="{{ asset('images/KDC.png') }}" />

    <!-- URL website kamu -->
    <meta property="og:url" content="https://im.todays.id" />
    
    <meta property="og:type" content="website" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    @yield('content')
</body>
</html>
