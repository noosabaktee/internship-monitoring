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
                <span><i class="fa-solid fa-right-to-bracket"></i> In {{ $clockInStart->format('H:i') }} - {{ $clockInEnd->format('H:i') }} WIB</span>
                <span><i class="fa-solid fa-right-from-bracket"></i> Out {{ $clockOutStart->format('H:i') }} - {{ $clockOutEnd->format('H:i') }} WIB</span>
                <span><i class="fa-solid fa-location-dot"></i> Lokasi wajib aktif</span>
                <span><i class="fa-solid fa-shield-halved"></i> {{ $hasFaceEnrollment ? 'Face ID aktif' : 'Face ID belum aktif' }}</span>
            </div>
        </div>
        <div class="attendance-window-state attendance-window-{{ $windowState }}">
            <strong>{{ $windowStatusText }}</strong>
            <span>{{ $pageNow->format('d M Y, H:i') }} WIB</span>
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
            <i class="fa-solid fa-right-to-bracket"></i>
            <div>
                <span>Clock In</span>
                <strong>{{ $clockInLabel }}</strong>
                @if ($clockInWarning)
                    <span class="attendance-status attendance-kpi-status attendance-status-warning">Belum Clock In</span>
                @elseif ($todayClockInStatus)
                    <span class="attendance-status attendance-kpi-status {{ $todayClockInStatusClass }}">{{ $todayClockInStatus }}</span>
                @endif
            </div>
        </div>
        <div class="attendance-kpi">
            <i class="fa-solid fa-right-from-bracket"></i>
            <div>
                <span>Clock Out</span>
                <strong>{{ $clockOutLabel }}</strong>
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

    @if ($clockInWarning || $clockOutWarning)
        <div class="attendance-warning-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ $clockInWarning ? $clockInWarningText : $clockOutWarningText }}</span>
        </div>
    @endif

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

            <div class="attendance-actions">
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
                        data-attendance-action-name="Clock In"
                        data-disabled-reason="{{ $clockInDisabledReason }}"
                        title="{{ $clockInDisabledReason }}"
                        @disabled(! $canClockIn)
                    >
                        <i class="fa-solid fa-fingerprint"></i>
                        <span>{{ $clockInButtonText }}</span>
                    </button>
                </form>

                <form action="{{ route('attendance.check-out.store') }}" method="POST" data-attendance-form>
                    @csrf
                    <input type="hidden" name="txtAttendanceCapturedImage" data-attendance-captured-image>
                    <input type="hidden" name="floatAttendanceLatitude" data-attendance-latitude>
                    <input type="hidden" name="floatAttendanceLongitude" data-attendance-longitude>
                    <input type="hidden" name="floatAttendanceLocationAccuracy" data-attendance-accuracy>
                    <input type="hidden" name="txtAttendanceDevice" data-attendance-device>

                    <button
                        class="btn btn-outline-primary attendance-submit"
                        type="button"
                        data-attendance-submit
                        data-attendance-action-name="Clock Out"
                        data-disabled-reason="{{ $clockOutDisabledReason }}"
                        title="{{ $clockOutDisabledReason }}"
                        @disabled(! $canClockOut)
                    >
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                        <span>{{ $clockOutButtonText }}</span>
                    </button>
                </form>
            </div>
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
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                            <th>Lokasi</th>
                            <!-- <th>Match</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaryRows as $row)
                            @php
                                $statusClass = match ($row['status']) {
                                    'Hadir' => 'attendance-status-present',
                                    'Terlambat' => 'attendance-status-late',
                                    'Tidak Masuk' => 'attendance-status-absent',
                                    default => 'attendance-status-waiting',
                                };
                                $summaryClockInStatusClass = ($row['clockInStatus'] ?? null) === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
                            @endphp
                            <tr>
                                <td>{{ $row['date']->format('d M Y') }}</td>
                                <td>
                                    @if ($row['clockInWarning'] ?? false)
                                        <span class="attendance-status attendance-status-warning">Belum Clock In</span>
                                    @else
                                        <div class="attendance-match-stack">
                                            <span class="text-center">{{ $row['clockIn'] }}</span>
                                            @if ($row['clockInStatus'] ?? null)
                                                <span class="attendance-status {{ $summaryClockInStatusClass }}">{{ $row['clockInStatus'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['clockOutWarning'])
                                        <span class="attendance-status attendance-status-warning">Belum Clock Out</span>
                                    @else
                                        <div class="attendance-match-stack">
                                            <span class="text-center">{{ $row['clockOut'] }}</span>
                                            @if ($row['clockOutStatus'])
                                                <span class="attendance-status {{ $row['clockOutStatus'] === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present' }}">{{ $row['clockOutStatus'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td><span class="attendance-status {{ $statusClass }}">{{ $row['status'] }}</span></td>
                                <td>
                                    <div class="attendance-location-stack">
                                        @if ($row['locationInUrl'])
                                            <a class="attendance-location-link" href="{{ $row['locationInUrl'] }}" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-location-dot"></i>
                                                In: {{ $row['locationIn'] }}
                                            </a>
                                        @else
                                            <span>In: {{ $row['locationIn'] }}</span>
                                        @endif
                                        @if ($row['locationOutUrl'])
                                            <a class="attendance-location-link" href="{{ $row['locationOutUrl'] }}" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-location-dot"></i>
                                                Out: {{ $row['locationOut'] }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <!-- <td>
                                    <div class="attendance-match-stack">
                                        <span>In {{ is_numeric($row['faceDistance']) ? number_format((float) $row['faceDistance'], 3) : '-' }}</span>
                                        <span>Out {{ is_numeric($row['clockOutFaceDistance']) ? number_format((float) $row['clockOutFaceDistance'], 3) : '-' }}</span>
                                    </div>
                                </td> -->
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
