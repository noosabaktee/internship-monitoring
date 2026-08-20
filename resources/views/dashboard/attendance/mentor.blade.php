@php
    $attendanceExportQuery = [
        'from' => $attendanceDetailFilters['from'] ?? now('Asia/Jakarta')->subDays(30)->toDateString(),
        'to' => $attendanceDetailFilters['to'] ?? now('Asia/Jakarta')->toDateString(),
    ];

    if (($attendanceDetailFilters['intUser_ID'] ?? '0') !== '0') {
        $attendanceExportQuery['intUser_ID'] = $attendanceDetailFilters['intUser_ID'];
    }

    $locationFormHasErrors = collect([
        'txtAttendanceLocationCode',
        'txtAttendanceLocationName',
        'txtAttendanceLocationAddress',
        'floatAttendanceLocationLatitude',
        'floatAttendanceLocationLongitude',
        'intAttendanceLocationRadiusMeter',
        'intAttendanceLocationToleranceMeter',
        'intAttendanceLocationMaximumAccuracyMeter',
    ])->contains(fn ($field) => $errors->has($field));
    $salarySlipFormHasErrors = collect([
        'dtmSalarySlipPeriodStart',
        'dtmSalarySlipPeriodEnd',
        'intIntern_ID',
    ])->contains(fn ($field) => $errors->has($field));
    $attendanceTab = $locationFormHasErrors
        ? 'settings'
        : ($salarySlipFormHasErrors
            ? 'detail'
            : (in_array(request('tab'), ['today', 'detail', 'settings'], true) ? request('tab') : 'today'));
    $locationLatitude = old('floatAttendanceLocationLatitude', $attendanceLocation?->floatAttendanceLocationLatitude);
    $locationLongitude = old('floatAttendanceLocationLongitude', $attendanceLocation?->floatAttendanceLocationLongitude);
    $locationMapsUrl = is_numeric($locationLatitude) && is_numeric($locationLongitude)
        ? 'https://www.google.com/maps?q='.$locationLatitude.','.$locationLongitude
        : 'https://www.google.com/maps';
@endphp

<div class="attendance-shell">
    <section class="attendance-hero">
        <div class="attendance-hero-main">
            <span class="attendance-eyebrow"><i class="fa-solid fa-user-shield"></i> Headmaster / HRD</span>
            <h2>Absensi Intern</h2>
            <p>Pantau status harian, atur jam dan lokasi absensi, lalu export laporan atau payroll dari satu tempat.</p>
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

    <nav class="attendance-admin-tabs" aria-label="Navigasi absensi" data-attendance-admin-tabs data-default-tab="{{ $attendanceTab }}">
        <button class="attendance-admin-tab {{ $attendanceTab === 'today' ? 'is-active' : '' }}" type="button" data-attendance-admin-tab="today">
            <i class="fa-solid fa-calendar-day"></i><span>Hari Ini</span><small>{{ $teamTodayRows->count() }} intern</small>
        </button>
        <button class="attendance-admin-tab {{ $attendanceTab === 'detail' ? 'is-active' : '' }}" type="button" data-attendance-admin-tab="detail">
            <i class="fa-solid fa-chart-column"></i><span>Detail & Payroll</span><small>Filter dan export</small>
        </button>
        <button class="attendance-admin-tab {{ $attendanceTab === 'settings' ? 'is-active' : '' }}" type="button" data-attendance-admin-tab="settings">
            <i class="fa-solid fa-sliders"></i><span>Pengaturan</span><small>Jam dan lokasi</small>
        </button>
    </nav>

    <div class="attendance-admin-tab-panel" data-attendance-admin-panel="settings" @hidden($attendanceTab !== 'settings')>
        <section class="attendance-panel">
            <div class="attendance-panel-head">
                <div>
                    <h3>Setting Absensi</h3>
                    <p>Rentang Clock In, Clock Out, dan threshold face recognition.</p>
                </div>
                <span class="attendance-map-chip"><i class="fa-solid fa-lock"></i> Admin</span>
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
    </div>

    <div class="attendance-admin-tab-panel" data-attendance-admin-panel="today" @hidden($attendanceTab !== 'today')>
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
                            <th>Mode</th>
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
                                    'Sakit' => 'attendance-status-sick',
                                    'Izin' => 'attendance-status-permission',
                                    default => 'attendance-status-waiting',
                                };
                                $teamClockInStatusClass = ($row['clockInStatus'] ?? null) === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
                            @endphp
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['faceRegistered'] ? 'Aktif' : 'Belum' }}</td>
                                <td><span class="attendance-status {{ $teamStatusClass }}">{{ $row['status'] }}</span></td>
                                <td><span class="work-mode-chip {{ match ($row['workMode'] ?? 'Office') { 'WFH' => 'wfh', 'Sakit' => 'sick', 'Izin' => 'permission', default => 'office' } }}">{{ match ($row['workMode'] ?? 'Office') { 'WFH' => 'WFH', 'Sakit' => 'Sakit', 'Izin' => 'Izin', default => 'WFO' } }}</span></td>
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
                            <tr><td colspan="7" class="center">Belum ada intern aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="attendance-panel attendance-location-panel attendance-admin-tab-panel" data-attendance-admin-panel="settings" @hidden($attendanceTab !== 'settings')>
        <div class="attendance-panel-head">
            <div><h3>Pengaturan Lokasi Absensi</h3><p>Tentukan satu lokasi kantor untuk absensi normal beserta radius dan toleransi GPS-nya.</p></div>
            <div class="attendance-location-head-actions">
                <span class="attendance-map-chip"><i class="fa-solid {{ $attendanceLocation?->bitActive ? 'fa-circle-check' : 'fa-circle-pause' }}"></i> {{ $attendanceLocation?->bitActive ? 'Lokasi aktif' : 'Lokasi belum aktif' }}</span>
            </div>
        </div>

        <form id="attendanceLocationSettingForm" class="attendance-location-setting-form" action="{{ $attendanceLocation ? route('attendance-locations.update', $attendanceLocation->intAttendanceLocation_ID) : route('attendance-locations.store') }}" method="POST" data-location-form>
            @csrf
            @if ($attendanceLocation)
                @method('PUT')
            @endif

            <div class="attendance-location-setting-layout">
                <div class="location-setting-fields form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationCode">Kode Lokasi <span class="required">*</span></label>
                        <input id="attendanceLocationCode" class="form-control" name="txtAttendanceLocationCode" value="{{ old('txtAttendanceLocationCode', $attendanceLocation?->txtAttendanceLocationCode) }}" placeholder="KDC-JKT" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationName">Nama Lokasi <span class="required">*</span></label>
                        <input id="attendanceLocationName" class="form-control" name="txtAttendanceLocationName" value="{{ old('txtAttendanceLocationName', $attendanceLocation?->txtAttendanceLocationName) }}" placeholder="Kantor Jakarta" required>
                    </div>
                    <div class="form-group form-span-full">
                        <label class="form-label" for="attendanceLocationAddress">Alamat <span class="required">*</span></label>
                        <textarea id="attendanceLocationAddress" class="form-control" name="txtAttendanceLocationAddress" rows="3" placeholder="Alamat akan terisi setelah titik lokasi dipilih" required>{{ old('txtAttendanceLocationAddress', $attendanceLocation?->txtAttendanceLocationAddress) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationLat">Latitude <span class="required">*</span></label>
                        <input id="attendanceLocationLat" class="form-control" type="number" step="0.0000001" name="floatAttendanceLocationLatitude" value="{{ $locationLatitude }}" placeholder="-6.2000000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationLng">Longitude <span class="required">*</span></label>
                        <input id="attendanceLocationLng" class="form-control" type="number" step="0.0000001" name="floatAttendanceLocationLongitude" value="{{ $locationLongitude }}" placeholder="106.8166660" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationRadius">Radius (meter) <span class="required">*</span></label>
                        <input id="attendanceLocationRadius" class="form-control" type="number" min="10" max="10000" name="intAttendanceLocationRadiusMeter" value="{{ old('intAttendanceLocationRadiusMeter', $attendanceLocation?->intAttendanceLocationRadiusMeter ?? 100) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="attendanceLocationTolerance">Toleransi GPS (meter) <span class="required">*</span></label>
                        <input id="attendanceLocationTolerance" class="form-control" type="number" min="0" max="10000" name="intAttendanceLocationToleranceMeter" value="{{ old('intAttendanceLocationToleranceMeter', $attendanceLocation?->intAttendanceLocationToleranceMeter ?? 50) }}" required>
                    </div>
                    <div class="form-group form-span-full">
                        <label class="form-label" for="attendanceLocationAccuracy">Akurasi GPS Maksimum (meter)</label>
                        <input id="attendanceLocationAccuracy" class="form-control" type="number" min="1" max="10000" name="intAttendanceLocationMaximumAccuracyMeter" value="{{ old('intAttendanceLocationMaximumAccuracyMeter', $attendanceLocation?->intAttendanceLocationMaximumAccuracyMeter ?? 200) }}">
                    </div>
                    <label class="check-control form-group form-span-full">
                        <input type="hidden" name="bitActive" value="0">
                        <input type="checkbox" name="bitActive" value="1" @checked((bool) old('bitActive', $attendanceLocation?->bitActive ?? true))>
                        <span>Aktifkan lokasi untuk absensi normal</span>
                    </label>
                </div>

                <div class="location-setting-map-column">
                    <div class="location-picker-actions">
                        <button class="btn btn-outline-primary" type="button" data-use-current-location data-lat-target="#attendanceLocationLat" data-lng-target="#attendanceLocationLng" data-address-target="#attendanceLocationAddress">
                            <i class="fa-solid fa-location-crosshairs"></i> Gunakan Lokasi Saat Ini
                        </button>
                        <span data-location-message>Cari alamat, klik peta, atau geser pin untuk menentukan titik kantor.</span>
                    </div>

                    <div class="location-picker-panel" data-location-picker data-lat-target="#attendanceLocationLat" data-lng-target="#attendanceLocationLng" data-address-target="#attendanceLocationAddress" data-map-id="attendanceLocationMap">
                        <div class="location-picker-head">
                            <div><strong>Pilih Titik Lokasi</strong><small>Pin pada peta menjadi pusat radius absensi.</small></div>
                            <a class="btn btn-outline-primary btn-sm" href="{{ $locationMapsUrl }}" target="_blank" rel="noopener" data-google-maps-link><i class="fa-brands fa-google"></i> Buka Google Maps</a>
                        </div>
                        <div class="location-picker-search">
                            <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" data-location-map-query placeholder="Cari alamat atau nama kantor"></div>
                            <button class="btn btn-outline-primary" type="button" data-location-map-search><i class="fa-solid fa-map-location-dot"></i> Cari</button>
                        </div>
                        <div id="attendanceLocationMap" class="location-map-canvas" data-initial-lat="{{ $locationLatitude }}" data-initial-lng="{{ $locationLongitude }}"></div>
                    </div>
                </div>
            </div>

            <div class="location-setting-footer">
                <span><i class="fa-solid fa-circle-info"></i> Perubahan ini berlaku untuk seluruh absensi normal.</span>
                <button class="btn btn-primary btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan Lokasi</button>
            </div>
        </form>
    </section>

    <section class="attendance-panel attendance-detail-panel attendance-admin-tab-panel" data-attendance-admin-panel="detail" @hidden($attendanceTab !== 'detail')>
        <div class="attendance-panel-head">
            <div>
                <h3>Detail Absensi</h3>
                <p>Summary absensi berdasarkan tanggal dan intern.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('attendance.export.excel', $attendanceExportQuery) }}">
                    <i class="fa-solid fa-file-excel"></i>
                    Export Excel
                </a>
                <!-- <a class="btn btn-outline-primary btn-sm" href="{{ route('attendance.report.pdf', $attendanceExportQuery) }}">
                    <i class="fa-solid fa-file-pdf"></i>
                    Report PDF
                </a> -->
                <button class="btn btn-primary btn-sm" type="button" onclick="openModal('sendSalarySlipModal')" @disabled($salarySlipInterns->isEmpty())>
                    <i class="fa-solid fa-paper-plane"></i>
                    Kirim Slip Gaji
                </button>
            </div>
        </div>

        <form class="attendance-detail-filter" action="{{ route('attendance.index') }}" method="GET">
            <input type="hidden" name="tab" value="detail">
            <div>
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="from" value="{{ $attendanceDetailFilters['from'] ?? now('Asia/Jakarta')->subDays(30)->toDateString() }}">
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
            <span><strong>{{ $attendanceDetailSummary['total'] ?? 0 }}</strong> Absensi</span>
            <span><strong>{{ $attendanceDetailSummary['present'] ?? 0 }}</strong> Hadir</span>
            <span><strong>{{ $attendanceDetailSummary['late'] ?? 0 }}</strong> Terlambat</span>
            <span><strong>{{ $attendanceDetailSummary['absent'] ?? 0 }}</strong> Tidak Masuk</span>
            <span><strong>{{ $attendanceDetailSummary['sick'] ?? 0 }}</strong> Sakit</span>
            <span><strong>{{ $attendanceDetailSummary['permission'] ?? 0 }}</strong> Izin</span>
            <span><strong>{{ $attendanceDetailSummary['pending'] ?? 0 }}</strong> Belum Clock In</span>
            <span><strong>{{ $attendanceDetailSummary['clockOutWarnings'] ?? 0 }}</strong> Warning Clock Out</span>
        </div>

        @if ($attendancePayrollSummary)
            <div class="attendance-detail-stats">
                <span><strong>Rp {{ number_format((float) $attendancePayrollSummary['dailySalary'], 0, ',', '.') }}</strong> Salary / Hari</span>
                <span><strong>{{ $attendancePayrollSummary['workdays'] }}</strong> Hari Kerja</span>
                <span><strong>{{ $attendancePayrollSummary['absentDays'] }}</strong> Hari Dipotong</span>
                <span><strong>Rp {{ number_format((float) $attendancePayrollSummary['grossSalary'], 0, ',', '.') }}</strong> Gross</span>
                <span><strong>Rp {{ number_format((float) $attendancePayrollSummary['deduction'], 0, ',', '.') }}</strong> Potongan</span>
                <span><strong>Rp {{ number_format((float) $attendancePayrollSummary['netSalary'], 0, ',', '.') }}</strong> Net Salary</span>
            </div>
        @endif

        <div class="table-responsive">
            <table class="data-table attendance-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Intern</th>
                        <th>Type</th>
                        <th>Salary / Day</th>
                        <th>Status</th>
                        <th>Mode</th>
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
                                'Sakit' => 'attendance-status-sick',
                                'Izin' => 'attendance-status-permission',
                                default => 'attendance-status-waiting',
                            };
                            $detailClockInStatusClass = ($row['clockInStatus'] ?? null) === 'Terlambat' ? 'attendance-status-late' : 'attendance-status-present';
                        @endphp
                        <tr data-attendance-detail-intern="{{ $row['intUser_ID'] }}">
                            <td>{{ $row['date']->format('d M Y') }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['internTypeLabel'] ?? 'Digitalisasi' }}</td>
                            <td>Rp {{ number_format((float) ($row['dailySalary'] ?? 0), 0, ',', '.') }}</td>
                            <td><span class="attendance-status {{ $detailStatusClass }}">{{ $row['status'] }}</span></td>
                            <td><span class="work-mode-chip {{ match ($row['workMode'] ?? '') { 'WFH' => 'wfh', 'Sakit' => 'sick', 'Izin' => 'permission', 'Office' => 'office', default => 'office' } }}">{{ match ($row['workMode'] ?? '') { 'WFH' => 'WFH', 'Sakit' => 'Sakit', 'Izin' => 'Izin', 'Office' => 'WFO', default => '-' } }}</span></td>
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
                        <tr><td colspan="9" class="center">Tidak ada data pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@push('modals')
    <x-crud-modal
        id="sendSalarySlipModal"
        title="Kirim Slip Gaji"
        subtitle="Tentukan periode dan intern, lalu kirim ke profil atau download file slip gaji."
        :active="$salarySlipFormHasErrors"
    >
        <form id="sendSalarySlipForm" action="{{ route('attendance.salary-slips.store') }}" method="POST">
            @csrf

            <div class="salary-slip-modal-note">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Pilih Kirim atau Download</strong>
                    <span>Kirim akan menyimpan slip ke profil intern. Download hanya membuat file PDF atau ZIP tanpa menyimpannya ke profil.</span>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="salarySlipPeriodStart">Dari Tanggal <span class="required">*</span></label>
                    <input
                        id="salarySlipPeriodStart"
                        class="form-control"
                        type="date"
                        name="dtmSalarySlipPeriodStart"
                        value="{{ old('dtmSalarySlipPeriodStart', $attendanceDetailFilters['from'] ?? now('Asia/Jakarta')->subDays(30)->toDateString()) }}"
                        max="{{ now('Asia/Jakarta')->toDateString() }}"
                        required
                    >
                    @error('dtmSalarySlipPeriodStart')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="salarySlipPeriodEnd">Sampai Tanggal <span class="required">*</span></label>
                    <input
                        id="salarySlipPeriodEnd"
                        class="form-control"
                        type="date"
                        name="dtmSalarySlipPeriodEnd"
                        value="{{ old('dtmSalarySlipPeriodEnd', $attendanceDetailFilters['to'] ?? now('Asia/Jakarta')->toDateString()) }}"
                        max="{{ now('Asia/Jakarta')->toDateString() }}"
                        required
                    >
                    @error('dtmSalarySlipPeriodEnd')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group form-span-full">
                    <label class="form-label" for="salarySlipIntern">Bagikan Slip Kepada <span class="required">*</span></label>
                    <select id="salarySlipIntern" class="form-control" name="intIntern_ID" required>
                        <option value="0" @selected((string) old('intIntern_ID', $attendanceSelectedIntern?->intern?->intIntern_ID ?? 0) === '0')>All Intern Periode Ini ({{ $salarySlipInterns->count() }})</option>
                        @foreach ($salarySlipInterns as $internUser)
                            <option value="{{ $internUser->intern->intIntern_ID }}" @selected((string) old('intIntern_ID', $attendanceSelectedIntern?->intern?->intIntern_ID ?? 0) === (string) $internUser->intern->intIntern_ID)>
                                {{ $internUser->intern->txtInternName }}{{ $internUser->intern->txtInternNo ? ' - '.$internUser->intern->txtInternNo : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field-help">Pilihan All akan membuat file terpisah untuk setiap intern yang aktif pada periode slip dan mengemas hasil download dalam ZIP.</span>
                    @error('intIntern_ID')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </form>

        <x-slot:footer>
            <button class="btn-cancel" type="button" data-modal-dismiss="sendSalarySlipModal">Batal</button>
            <button
                class="btn btn-outline-primary btn-save"
                type="submit"
                form="sendSalarySlipForm"
                formaction="{{ route('attendance.salary-slips.download') }}"
                @disabled($salarySlipInterns->isEmpty())
            >
                <i class="fa-solid fa-download"></i>
                Download Slip Gaji
            </button>
            <button class="btn btn-primary btn-save" type="submit" form="sendSalarySlipForm" @disabled($salarySlipInterns->isEmpty())>
                <i class="fa-solid fa-paper-plane"></i>
                Kirim Slip Gaji
            </button>
        </x-slot:footer>
    </x-crud-modal>
@endpush
