@extends('layouts.app', [
    'title' => 'Absensi - Kalbe Internship Dashboard',
    'pageTitle' => 'ABSENSI',
    'pageSubtitle' => $isMentor ? 'Setting dan monitoring Clock In/Clock Out intern.' : 'Face ID, lokasi, dan rangkuman kehadiran.',
])

@php
    $pageNow = now('Asia/Jakarta');
    $isLegacyFaceEnrollment = (bool) $enrollment && ! str_starts_with((string) $enrollment->txtFaceEnrollmentAlgorithm, 'insightface');
    $hasFaceEnrollment = (bool) $enrollment && ! $isLegacyFaceEnrollment;
    $hasClockIn = (bool) $todayClockInAt;
    $hasClockOut = (bool) $todayClockOutAt;
    $clockInBeforeClockOutLimit = $hasClockIn && $todayClockInAt->lte($clockOutEnd);
    $clockOutLateOpen = $hasClockIn && ! $hasClockOut && $clockInBeforeClockOutLimit && $pageNow->gt($clockOutEnd) && $pageNow->lte($clockOutLateEnd);
    $clockInWarning = $isWorkday && ! $hasClockIn && $pageNow->gt($clockInEnd) && $pageNow->lte($clockInLateEnd);
    $clockOutWarning = $isWorkday && $hasClockIn && ! $hasClockOut && $pageNow->gt($clockOutEnd);
    $isClockInLate = $todayClockInStatus === 'Terlambat' || ($hasClockIn && $todayClockInAt->gt($clockInEnd));
    $todayClockInStatusClass = $todayClockInStatus === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
    $canClockIn = ! $isMentor && $isWorkday && $hasFaceEnrollment && ! $hasClockIn && $pageNow->gte($clockInStart);
    $canClockOut = ! $isMentor && $isWorkday && $hasFaceEnrollment && $hasClockIn && ! $hasClockOut && $clockInBeforeClockOutLimit && $pageNow->gte($clockOutStart) && $pageNow->lte($clockOutLateEnd);
    $todayStatus = match (true) {
        $isClockInLate => 'Terlambat',
        $hasClockIn => 'Hadir',
        (bool) $todayAttendance => $todayAttendance->txtAttendanceStatus ?: 'Hadir',
        ! $isWorkday => 'Tidak Ada Absensi',
        default => 'Belum Clock In',
    };
    $clockInLabel = $hasClockIn
        ? $todayClockInAt->format('H:i') . ' WIB'
        : ($windowState === 'before' ? 'Belum dibuka' : 'Belum');
    $clockOutLabel = $hasClockOut
        ? $todayClockOutAt->format('H:i') . ' WIB'
        : ($clockOutWarning ? 'Belum Clock Out' : 'Belum');
    $windowStatusText = match ($windowState) {
        'before' => 'Belum Dibuka',
        'clock-in' => 'Clock In Dibuka',
        'between' => 'Menunggu Clock Out',
        'clock-out' => 'Clock Out Dibuka',
        'clock-out-late' => $clockOutLateOpen ? 'Clock Out Terlambat' : 'Lewat Jam Kerja',
        'after-clock-out' => 'Lewat Jam Kerja',
        'offday' => 'Libur',
        default => 'Dibuka',
    };
    $clockInDisabledReason = match (true) {
        ! $isWorkday => 'Clock In hanya tersedia pada hari kerja Senin-Jumat.',
        $isLegacyFaceEnrollment => 'Perbarui Face ID di halaman Profile.',
        ! $hasFaceEnrollment => 'Daftarkan Face ID di halaman Profile terlebih dahulu.',
        $hasClockIn => 'Clock In hari ini sudah tercatat.',
        $pageNow->lt($clockInStart) => 'Clock In belum dibuka.',
        default => '',
    };
    $clockOutDisabledReason = match (true) {
        ! $isWorkday => 'Clock Out hanya tersedia pada hari kerja Senin-Jumat.',
        $isLegacyFaceEnrollment => 'Perbarui Face ID di halaman Profile.',
        ! $hasFaceEnrollment => 'Daftarkan Face ID di halaman Profile terlebih dahulu.',
        ! $hasClockIn => 'Clock In terlebih dahulu sebelum Clock Out.',
        $hasClockOut => 'Clock Out hari ini sudah tercatat.',
        $hasClockIn && ! $clockInBeforeClockOutLimit => 'Clock In tercatat setelah batas Clock Out, jadi Clock Out hari ini tidak tersedia.',
        $pageNow->lt($clockOutStart) => 'Clock Out belum dibuka.',
        $pageNow->gt($clockOutLateEnd) => 'Batas Clock Out terlambat hari ini sudah lewat.',
        default => '',
    };
    $clockInButtonText = $pageNow->gt($clockInEnd) ? 'Clock In Terlambat' : 'Clock In';
    $clockOutButtonText = $clockOutLateOpen ? 'Clock Out Terlambat' : 'Clock Out';
    $clockInWarningText = $pageNow->gt($clockOutEnd)
        ? 'Clock In sudah melewati batas Clock Out. Saat disimpan, Clock Out juga otomatis tercatat dengan status Terlambat.'
        : 'Clock In sudah melewati batas. Masih bisa dicatat sampai 23:59 WIB dengan status Terlambat.';
    $clockOutWarningText = match (true) {
        $clockOutLateOpen => 'Clock Out sudah melewati batas. Masih bisa dicatat sampai 23:59 WIB dengan status Terlambat.',
        $hasClockIn && ! $clockInBeforeClockOutLimit => 'Clock Out tidak tersedia karena Clock In tercatat setelah batas Clock Out.',
        default => 'Clock Out belum tercatat. Status kehadiran tetap tersimpan, tetapi mentor akan melihat warning ini.',
    };
@endphp

@section('content')
    @include($isMentor ? 'dashboard.attendance.mentor' : 'dashboard.attendance.intern')
@endsection
