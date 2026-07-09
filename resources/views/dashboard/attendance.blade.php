@extends('layouts.app', [
    'title' => 'Absensi - Kalbe Internship Dashboard',
    'pageTitle' => 'ABSENSI',
    'pageSubtitle' => $isMentor ? 'Setting dan monitoring absensi intern.' : 'Face ID, lokasi, dan rangkuman kehadiran.',
])

@php
    $isLegacyFaceEnrollment = (bool) $enrollment && ! str_starts_with((string) $enrollment->txtFaceEnrollmentAlgorithm, 'insightface');
    $hasFaceEnrollment = (bool) $enrollment && ! $isLegacyFaceEnrollment;
    $canAttemptAttendance = ! $isMentor && $isWorkday && $hasFaceEnrollment && ! $todayAttendance && $windowState === 'open';
    $todayStatus = match (true) {
        (bool) $todayAttendance => 'Hadir',
        ! $isWorkday => 'Tidak Ada Absensi',
        $windowState === 'closed' => 'Tidak Masuk',
        default => 'Belum Absensi',
    };
    $windowStatusText = match ($windowState) {
        'before' => 'Belum dibuka',
        'closed' => 'Ditutup',
        'offday' => 'Libur',
        default => 'Dibuka',
    };
    $disabledReason = match (true) {
        ! $isWorkday => 'Absensi hanya tersedia pada hari kerja Senin-Jumat.',
        $isLegacyFaceEnrollment => 'Perbarui Face ID di halaman Profile.',
        ! $hasFaceEnrollment => 'Daftarkan Face ID di halaman Profile terlebih dahulu.',
        (bool) $todayAttendance => 'Absensi hari ini sudah tercatat.',
        $windowState !== 'open' => 'Absensi belum berada dalam jam aktif.',
        default => '',
    };
@endphp

@section('content')
    @if ($isMentor)
        <div class="attendance-shell">
            <section class="attendance-hero">
                <div class="attendance-hero-main">
                    <span class="attendance-eyebrow"><i class="fa-solid fa-user-tie"></i> Mentor</span>
                    <h2>Absensi Intern</h2>
                    <div class="attendance-hero-meta">
                        <span><i class="fa-regular fa-clock"></i> {{ $windowStart->format('H:i') }} - {{ $windowEnd->format('H:i') }} WIB</span>
                        <span><i class="fa-solid fa-calendar-week"></i> Senin-Jumat</span>
                        <span><i class="fa-solid fa-users"></i> {{ $teamTodayRows->count() }} intern aktif</span>
                    </div>
                </div>
                <div class="attendance-window-state attendance-window-{{ $windowState }}">
                    <strong>{{ $windowStatusText }}</strong>
                    <span>{{ now('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
                </div>
            </section>

            <div class="attendance-mentor-grid">
                <section class="attendance-panel">
                    <div class="attendance-panel-head">
                        <div>
                            <h3>Setting Absensi</h3>
                            <p>Jam absensi dan threshold face recognition.</p>
                        </div>
                        <span class="attendance-map-chip"><i class="fa-solid fa-lock"></i> Mentor</span>
                    </div>

                    <form class="attendance-setting-form" action="{{ route('attendance.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="form-label">Jam Mulai</label>
                            <input class="form-control" type="time" name="txtAttendanceSettingStartTime" min="06:00" value="{{ old('txtAttendanceSettingStartTime', $setting->txtAttendanceSettingStartTime ?? '06:00') }}" required>
                        </div>
                        <div>
                            <label class="form-label">Jam Terakhir</label>
                            <input class="form-control" type="time" name="txtAttendanceSettingEndTime" value="{{ old('txtAttendanceSettingEndTime', $setting->txtAttendanceSettingEndTime ?? '23:59') }}" required>
                        </div>
                        <div>
                            <label class="form-label">Threshold Face</label>
                            <input class="form-control" type="number" min="0.1" max="1.5" step="0.01" name="floatAttendanceSettingFaceThreshold" value="{{ old('floatAttendanceSettingFaceThreshold', number_format((float) ($setting->floatAttendanceSettingFaceThreshold ?? 0.38), 2, '.', '')) }}" required>
                        </div>
                        <button class="btn btn-primary btn-save" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Simpan Setting
                        </button>
                    </form>
                </section>

                <section class="attendance-panel">
                    <div class="attendance-panel-head">
                        <div>
                            <h3>Absensi Intern Hari Ini</h3>
                            <p>{{ $teamTodayRows->count() }} intern aktif.</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table attendance-table">
                            <thead>
                                <tr>
                                    <th>Intern</th>
                                    <th>Face ID</th>
                                    <th>Status</th>
                                    <th>Jam</th>
                                    <th>Lokasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($teamTodayRows as $row)
                                    @php
                                        $teamStatusClass = match ($row['status']) {
                                            'Hadir' => 'attendance-status-present',
                                            'Tidak Masuk' => 'attendance-status-absent',
                                            default => 'attendance-status-waiting',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['faceRegistered'] ? 'Aktif' : 'Belum' }}</td>
                                        <td><span class="attendance-status {{ $teamStatusClass }}">{{ $row['status'] }}</span></td>
                                        <td>{{ $row['clock'] }}</td>
                                        <td>
                                            @if ($row['locationUrl'])
                                                <a class="attendance-location-link" href="{{ $row['locationUrl'] }}" target="_blank" rel="noopener">{{ $row['location'] }}</a>
                                            @else
                                                {{ $row['location'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="center">Belum ada intern aktif.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    @else
        <div
            class="attendance-shell"
            data-attendance-page
            data-attendance-mode="python"
            data-face-detection-url="{{ route('face.detection.store') }}"
            data-face-threshold="{{ number_format((float) ($setting->floatAttendanceSettingFaceThreshold ?? 0.38), 2, '.', '') }}"
        >
            <section class="attendance-hero">
                <div class="attendance-hero-main">
                    <span class="attendance-eyebrow"><i class="fa-solid fa-user-check"></i> {{ $displayName }}</span>
                    <h2>{{ $todayStatus }}</h2>
                    <div class="attendance-hero-meta">
                        <span><i class="fa-regular fa-clock"></i> {{ $windowStart->format('H:i') }} - {{ $windowEnd->format('H:i') }} WIB</span>
                        <span><i class="fa-solid fa-calendar-week"></i> Senin-Jumat</span>
                        <span><i class="fa-solid fa-location-dot"></i> Lokasi wajib aktif</span>
                        <span><i class="fa-solid fa-shield-halved"></i> {{ $hasFaceEnrollment ? 'Face ID aktif' : 'Face ID belum aktif' }}</span>
                    </div>
                </div>
                <div class="attendance-window-state attendance-window-{{ $windowState }}">
                    <strong>{{ $windowStatusText }}</strong>
                    <span>{{ now('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
                </div>
            </section>

            <div class="attendance-kpi-grid">
                <div class="attendance-kpi">
                    <i class="fa-solid fa-calendar-day"></i>
                    <div>
                        <span>Status Hari Ini</span>
                        <strong>{{ $todayStatus }}</strong>
                    </div>
                </div>
                <div class="attendance-kpi">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <span>Hadir</span>
                        <strong>{{ $presentCount }}</strong>
                    </div>
                </div>
                <div class="attendance-kpi">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <div>
                        <span>Tidak Masuk</span>
                        <strong>{{ $absentCount }}</strong>
                    </div>
                </div>
                <div class="attendance-kpi">
                    <i class="fa-solid fa-face-smile"></i>
                    <div>
                        <span>Face ID</span>
                        <strong>{{ $hasFaceEnrollment ? 'Aktif' : 'Belum' }}</strong>
                    </div>
                </div>
            </div>

            <div class="attendance-main-grid">
                <section class="attendance-panel attendance-camera-panel">
                    <div class="attendance-panel-head">
                        <div>
                            <h3>Absensi Face ID</h3>
                            <p>Kamera browser, verifikasi wajah oleh Python service lokal.</p>
                        </div>
                        <button class="attendance-icon-button" type="button" data-attendance-camera title="Aktifkan kamera">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>

                    <div class="attendance-camera-frame">
                        <video data-attendance-video autoplay playsinline muted></video>
                        <canvas data-attendance-canvas hidden></canvas>
                        <div class="attendance-camera-overlay">
                            <div class="attendance-scan-ring"></div>
                        </div>
                    </div>

                    <div class="attendance-status-line" data-attendance-message>
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Siap memulai kamera.</span>
                    </div>

                    <div class="attendance-progress" aria-hidden="true">
                        <span data-attendance-progress></span>
                    </div>

                    @if (! $hasFaceEnrollment)
                        <a class="btn btn-outline-primary attendance-profile-link" href="{{ route('profile.show') }}">
                            <i class="fa-solid fa-id-card"></i>
                            <span>Daftar Face ID di Profile</span>
                        </a>
                    @endif

                    <form action="{{ route('attendance.check-in.store') }}" method="POST" data-attendance-form>
                        @csrf
                        <input type="hidden" name="txtAttendanceCapturedImage" data-attendance-captured-image>
                        <input type="hidden" name="floatAttendanceLatitude" data-attendance-latitude>
                        <input type="hidden" name="floatAttendanceLongitude" data-attendance-longitude>
                        <input type="hidden" name="floatAttendanceLocationAccuracy" data-attendance-accuracy>
                        <input type="hidden" name="txtAttendanceDevice" data-attendance-device>

                        <button
                            class="btn btn-primary btn-add attendance-submit"
                            type="button"
                            data-attendance-submit
                            data-disabled-reason="{{ $disabledReason }}"
                            title="{{ $disabledReason }}"
                            @disabled(! $canAttemptAttendance)
                        >
                            <i class="fa-solid fa-fingerprint"></i>
                            <span>Absen Sekarang</span>
                        </button>
                    </form>
                </section>

                <section class="attendance-panel">
                    <div class="attendance-panel-head">
                        <div>
                            <h3>Rangkuman Absensi</h3>
                            <p>Hari Senin-Jumat</p>
                        </div>
                        @if ($todayAttendance?->txtAttendanceLocationUrl)
                            <a class="attendance-map-chip" href="{{ $todayAttendance->txtAttendanceLocationUrl }}" target="_blank" rel="noopener">
                                <i class="fa-solid fa-map-location-dot"></i>
                                Lokasi hari ini
                            </a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="data-table attendance-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Status</th>
                                    <th>Lokasi</th>
                                    <th>Match</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summaryRows as $row)
                                    @php
                                        $statusClass = match ($row['status']) {
                                            'Hadir' => 'attendance-status-present',
                                            'Tidak Masuk' => 'attendance-status-absent',
                                            default => 'attendance-status-waiting',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $row['date']->format('d M Y') }}</td>
                                        <td>{{ $row['clock'] }}</td>
                                        <td><span class="attendance-status {{ $statusClass }}">{{ $row['status'] }}</span></td>
                                        <td>
                                            @if ($row['locationUrl'])
                                                <a class="attendance-location-link" href="{{ $row['locationUrl'] }}" target="_blank" rel="noopener">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                    {{ $row['location'] }}
                                                </a>
                                            @else
                                                {{ $row['location'] }}
                                            @endif
                                        </td>
                                        <td>{{ is_numeric($row['faceDistance']) ? number_format((float) $row['faceDistance'], 3) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    @endif
@endsection
