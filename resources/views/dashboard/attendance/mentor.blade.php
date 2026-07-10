<div class="attendance-shell">
    <section class="attendance-hero">
        <div class="attendance-hero-main">
            <span class="attendance-eyebrow"><i class="fa-solid fa-user-tie"></i> Mentor</span>
            <h2>Absensi Intern</h2>
            <div class="attendance-hero-meta">
                <span><i class="fa-solid fa-right-to-bracket"></i> In {{ $clockInStart->format('H:i') }} - {{ $clockInEnd->format('H:i') }} WIB</span>
                <span><i class="fa-solid fa-right-from-bracket"></i> Out {{ $clockOutStart->format('H:i') }} - {{ $clockOutEnd->format('H:i') }} WIB</span>
                <span><i class="fa-solid fa-calendar-week"></i> Senin-Jumat</span>
                <span><i class="fa-solid fa-users"></i> {{ $teamTodayRows->count() }} intern aktif</span>
            </div>
        </div>
        <div class="attendance-window-state attendance-window-{{ $windowState }}">
            <strong>{{ $windowStatusText }}</strong>
            <span>{{ $pageNow->format('d M Y, H:i') }} WIB</span>
        </div>
    </section>

    <div class="attendance-mentor-grid">
        <section class="attendance-panel">
            <div class="attendance-panel-head">
                <div>
                    <h3>Setting Absensi</h3>
                    <p>Rentang Clock In, Clock Out, dan threshold face recognition.</p>
                </div>
                <span class="attendance-map-chip"><i class="fa-solid fa-lock"></i> Mentor</span>
            </div>

            <form class="attendance-setting-form" action="{{ route('attendance.settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">Mulai Clock In</label>
                    <input class="form-control" type="time" name="txtAttendanceSettingClockInStartTime" value="{{ old('txtAttendanceSettingClockInStartTime', $setting->txtAttendanceSettingClockInStartTime ?? '06:30') }}" required>
                </div>
                <div>
                    <label class="form-label">Batas Clock In</label>
                    <input class="form-control" type="time" name="txtAttendanceSettingClockInEndTime" value="{{ old('txtAttendanceSettingClockInEndTime', $setting->txtAttendanceSettingClockInEndTime ?? '09:00') }}" required>
                </div>
                <div>
                    <label class="form-label">Mulai Clock Out</label>
                    <input class="form-control" type="time" name="txtAttendanceSettingClockOutStartTime" value="{{ old('txtAttendanceSettingClockOutStartTime', $setting->txtAttendanceSettingClockOutStartTime ?? '16:00') }}" required>
                </div>
                <div>
                    <label class="form-label">Batas Clock Out</label>
                    <input class="form-control" type="time" name="txtAttendanceSettingClockOutEndTime" value="{{ old('txtAttendanceSettingClockOutEndTime', $setting->txtAttendanceSettingClockOutEndTime ?? '18:30') }}" required>
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
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Lokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teamTodayRows as $row)
                            @php
                                $teamStatusClass = match ($row['status']) {
                                    'Hadir' => 'attendance-status-present',
                                    'Terlambat' => 'attendance-status-late',
                                    'Tidak Masuk' => 'attendance-status-absent',
                                    default => 'attendance-status-waiting',
                                };
                                $teamClockInStatusClass = ($row['clockInStatus'] ?? null) === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
                            @endphp
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['faceRegistered'] ? 'Aktif' : 'Belum' }}</td>
                                <td><span class="attendance-status {{ $teamStatusClass }}">{{ $row['status'] }}</span></td>
                                <td>
                                    @if ($row['clockInWarning'] ?? false)
                                        <span class="attendance-status attendance-status-warning">Belum Clock In</span>
                                    @else
                                        <div class="attendance-match-stack">
                                            <span class="text-center">{{ $row['clockIn'] }}</span>
                                            @if ($row['clockInStatus'] ?? null)
                                                <span class="attendance-status {{ $teamClockInStatusClass }}">{{ $row['clockInStatus'] }}</span>
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
                                <td>
                                    <div class="attendance-location-stack">
                                        @if ($row['locationInUrl'])
                                            <a class="attendance-location-link" href="{{ $row['locationInUrl'] }}" target="_blank" rel="noopener">In: {{ $row['locationIn'] }}</a>
                                        @else
                                            <span>In: {{ $row['locationIn'] }}</span>
                                        @endif
                                        @if ($row['locationOutUrl'])
                                            <a class="attendance-location-link" href="{{ $row['locationOutUrl'] }}" target="_blank" rel="noopener">Out: {{ $row['locationOut'] }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="center">Belum ada intern aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="attendance-panel attendance-detail-panel">
        <div class="attendance-panel-head">
            <div>
                <h3>Detail Absensi</h3>
                <p>Summary absensi berdasarkan tanggal dan intern.</p>
            </div>
            <span class="attendance-map-chip"><i class="fa-solid fa-filter"></i> Filter</span>
        </div>

        <form class="attendance-detail-filter" action="{{ route('attendance.index') }}" method="GET">
            <div>
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="from" value="{{ $attendanceDetailFilters['from'] ?? now('Asia/Jakarta')->startOfMonth()->toDateString() }}">
            </div>
            <div>
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="to" value="{{ $attendanceDetailFilters['to'] ?? now('Asia/Jakarta')->toDateString() }}">
            </div>
            <div>
                <label class="form-label">Intern</label>
                <select class="form-control" name="intUser_ID">
                    <option value="0">All Intern</option>
                    @foreach ($attendanceDetailInterns as $internUser)
                        <option value="{{ $internUser->intUser_ID }}" @selected(($attendanceDetailFilters['intUser_ID'] ?? '0') === (string) $internUser->intUser_ID)>
                            {{ $internUser->intern?->txtInternName ?? $internUser->txtEmail }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary btn-save" type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
                Terapkan Filter
            </button>
        </form>

        <div class="attendance-detail-stats">
            <span><strong>{{ $attendanceDetailSummary['total'] ?? 0 }}</strong> Hari Kerja</span>
            <span><strong>{{ $attendanceDetailSummary['present'] ?? 0 }}</strong> Hadir</span>
            <span><strong>{{ $attendanceDetailSummary['late'] ?? 0 }}</strong> Terlambat</span>
            <span><strong>{{ $attendanceDetailSummary['absent'] ?? 0 }}</strong> Tidak Masuk</span>
            <span><strong>{{ $attendanceDetailSummary['pending'] ?? 0 }}</strong> Belum Clock In</span>
            <span><strong>{{ $attendanceDetailSummary['clockOutWarnings'] ?? 0 }}</strong> Warning Clock Out</span>
        </div>

        <div class="table-responsive">
            <table class="data-table attendance-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Intern</th>
                        <th>Status</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Lokasi</th>
                        <!-- <th>Match</th> -->
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attendanceDetailRows as $row)
                        @php
                            $detailStatusClass = match ($row['status']) {
                                'Hadir' => 'attendance-status-present',
                                'Terlambat' => 'attendance-status-late',
                                'Tidak Masuk' => 'attendance-status-absent',
                                default => 'attendance-status-waiting',
                            };
                            $detailClockInStatusClass = ($row['clockInStatus'] ?? null) === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
                        @endphp
                        <tr data-attendance-detail-intern="{{ $row['intUser_ID'] }}">
                            <td>{{ $row['date']->format('d M Y') }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td><span class="attendance-status {{ $detailStatusClass }}">{{ $row['status'] }}</span></td>
                            <td>
                                @if ($row['clockInWarning'] ?? false)
                                    <span class="attendance-status attendance-status-warning">Belum Clock In</span>
                                @else
                                    <div class="attendance-match-stack">
                                        <span class="text-center">{{ $row['clockIn'] }}</span>
                                        @if ($row['clockInStatus'] ?? null)
                                            <span class="attendance-status {{ $detailClockInStatusClass }}">{{ $row['clockInStatus'] }}</span>
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
                            <td>
                                <div class="attendance-location-stack">
                                    @if ($row['locationInUrl'])
                                        <a class="attendance-location-link" href="{{ $row['locationInUrl'] }}" target="_blank" rel="noopener">In: {{ $row['locationIn'] }}</a>
                                    @else
                                        <span>In: {{ $row['locationIn'] }}</span>
                                    @endif
                                    @if ($row['locationOutUrl'])
                                        <a class="attendance-location-link" href="{{ $row['locationOutUrl'] }}" target="_blank" rel="noopener">Out: {{ $row['locationOut'] }}</a>
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
                    @empty
                        <tr><td colspan="7" class="center">Tidak ada data pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
