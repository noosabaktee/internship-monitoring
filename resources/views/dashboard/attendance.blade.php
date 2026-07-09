@extends('layouts.app', [
    'title' => 'Absensi - Kalbe Internship Dashboard',
    'pageTitle' => 'ABSENSI',
    'pageSubtitle' => 'Face ID, lokasi, dan rangkuman kehadiran.',
])

@php
    $isLegacyFaceEnrollment = (bool) $enrollment && ! str_starts_with((string) $enrollment->txtFaceEnrollmentAlgorithm, 'insightface');
    $hasFaceEnrollment = (bool) $enrollment && ! $isLegacyFaceEnrollment;
    $canAttemptAttendance = $hasFaceEnrollment && ! $todayAttendance && $windowState === 'open';
    $todayStatus = $todayAttendance ? 'Hadir' : ($windowState === 'closed' ? 'Tidak Masuk' : 'Menunggu');
    $windowStatusText = match ($windowState) {
        'before' => 'Belum dibuka',
        'closed' => 'Ditutup',
        default => 'Dibuka',
    };
    $disabledReason = $isLegacyFaceEnrollment
        ? 'Perbarui Face ID untuk mode Python face recognition.'
        : (! $hasFaceEnrollment
        ? 'Daftarkan wajah terlebih dahulu.'
        : ($todayAttendance
            ? 'Absensi hari ini sudah tercatat.'
            : ($windowState !== 'open' ? 'Absensi belum berada dalam jam aktif.' : '')));
@endphp

@section('content')
    <div
        class="attendance-shell"
        data-attendance-page
        data-attendance-mode="python"
        data-face-threshold="{{ number_format((float) ($setting->floatAttendanceSettingFaceThreshold ?? 0.38), 2, '.', '') }}"
    >
        <section class="attendance-hero">
            <div class="attendance-hero-main">
                <span class="attendance-eyebrow"><i class="fa-solid fa-user-check"></i> {{ $displayName }}</span>
                <h2>{{ $todayStatus }}</h2>
                <div class="attendance-hero-meta">
                    <span><i class="fa-regular fa-clock"></i> {{ $windowStart->format('H:i') }} - {{ $windowEnd->format('H:i') }} WIB</span>
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
                        <h3>Face ID Absensi</h3>
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

                <div class="attendance-actions">
                    <form action="{{ route('attendance.face-enrollment.store') }}" method="POST" data-face-enrollment-form>
                        @csrf
                        <input type="hidden" name="txtFaceEnrollmentImages" data-face-enrollment-images>
                        <input type="hidden" name="intFaceEnrollmentSampleCount" data-face-enrollment-sample-count value="3">
                        <input type="hidden" name="floatFaceEnrollmentQuality" data-face-enrollment-quality>
                        <button class="btn btn-outline-primary attendance-action-button" type="button" data-face-enroll>
                            <i class="fa-solid fa-user-plus"></i>
                            <span>{{ $hasFaceEnrollment ? 'Perbarui Face ID' : 'Daftarkan Wajah' }}</span>
                        </button>
                    </form>

                    @if ($enrollment)
                        <form action="{{ route('attendance.face-enrollment.destroy') }}" method="POST" onsubmit="return confirm('Reset Face ID absensi?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger attendance-action-button" type="submit">
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>Reset</span>
                            </button>
                        </form>
                    @endif
                </div>

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
                        <p>14 hari terakhir.</p>
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

        @if ($isMentor)
            <div class="attendance-mentor-grid">
                <section class="attendance-panel">
                    <div class="attendance-panel-head">
                        <div>
                            <h3>Setting Absensi</h3>
                            <p>Hanya mentor.</p>
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
                            <h3>Absensi Hari Ini</h3>
                            <p>{{ $teamTodayRows->count() }} user aktif.</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table attendance-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
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
                                        <td>{{ $row['role'] }}</td>
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
                                    <tr><td colspan="6" class="center">Belum ada user aktif.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </div>
@endsection
