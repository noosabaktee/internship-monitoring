<?php

use App\Models\MAdminProfile;
use App\Models\MAttendanceLocation;
use App\Models\MAttendanceSetting;
use App\Models\MFaceEnrollment;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrSalarySlip;
use App\Services\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

it('renders attendance page for an authenticated user', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 08:00:00', 'Asia/Jakarta'));

    try {
        $user = createAttendanceUser('Intern');

        $this->withSession(['auth_user_id' => $user->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('ABSENSI')
            ->assertSee('Belum Clock In')
            ->assertSee('Clock In')
            ->assertSee('Clock Out')
            ->assertDontSee('Menunggu');
    } finally {
        Carbon::setTestNow();
    }
});

it('shows clock in warning after the clock in deadline before attendance is recorded', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    try {
        $user = createAttendanceUser('Intern');

        $this->withSession(['auth_user_id' => $user->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Clock In sudah melewati batas')
            ->assertSee('Belum Clock In');
    } finally {
        Carbon::setTestNow();
    }
});

it('shows face enrollment controls on profile instead of attendance page', function () {
    $intern = createAttendanceUser('Intern');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertDontSee('data-face-enroll', false);

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('data-face-enroll', false);
});

it('renders headmaster attendance page without personal check in controls', function () {
    $headmaster = createAttendanceUser('Headmaster');

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertSee('Absensi Intern')
        ->assertSee('Mulai Clock In')
        ->assertDontSee('data-attendance-page', false);
});

it('allows HRD to update attendance settings', function () {
    $hrd = createAttendanceUser('HRD');

    $this->withSession(['auth_user_id' => $hrd->intUser_ID])
        ->put(route('attendance.settings.update'), [
            'txtAttendanceSettingClockInStartTime' => '06:15',
            'txtAttendanceSettingClockInEndTime' => '08:45',
            'txtAttendanceSettingClockOutStartTime' => '15:45',
            'txtAttendanceSettingClockOutEndTime' => '18:00',
            'floatAttendanceSettingFaceThreshold' => '0.70',
        ])
        ->assertRedirect(route('attendance.index', ['tab' => 'settings']));

    $setting = MAttendanceSetting::find(1);

    expect($setting->txtAttendanceSettingClockInStartTime)->toBe('06:15')
        ->and($setting->txtAttendanceSettingClockInEndTime)->toBe('08:45')
        ->and($setting->txtAttendanceSettingClockOutStartTime)->toBe('15:45')
        ->and($setting->txtAttendanceSettingClockOutEndTime)->toBe('18:00')
        ->and($setting->txtAttendanceSettingStartTime)->toBe('06:15')
        ->and($setting->txtAttendanceSettingEndTime)->toBe('18:00')
        ->and((float) $setting->floatAttendanceSettingFaceThreshold)->toBe(0.7);
});

it('blocks intern from updating attendance settings', function () {
    $intern = createAttendanceUser('Intern');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->put(route('attendance.settings.update'), [
            'txtAttendanceSettingClockInStartTime' => '06:15',
            'txtAttendanceSettingClockInEndTime' => '08:45',
            'txtAttendanceSettingClockOutStartTime' => '15:45',
            'txtAttendanceSettingClockOutEndTime' => '18:00',
            'floatAttendanceSettingFaceThreshold' => '0.70',
        ])
        ->assertForbidden();
});

it('stores face enrollment from python face service embedding', function () {
    $intern = createAttendanceUser('Intern');
    $embedding = array_fill(0, 512, 0.01);

    $this->mock(FaceRecognitionService::class, function ($mock) use ($embedding) {
        $mock->shouldReceive('enroll')
            ->once()
            ->andReturn([
                'embedding' => $embedding,
                'algorithm' => 'insightface-buffalo_l-v1',
                'sample_count' => 3,
                'quality' => 0.91,
            ]);
    });

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->post(route('profile.face-enrollment.store'), [
            'txtFaceEnrollmentImages' => json_encode([
                attendanceImagePayload(),
                attendanceImagePayload(),
                attendanceImagePayload(),
            ]),
            'intFaceEnrollmentSampleCount' => '3',
            'floatFaceEnrollmentQuality' => '1',
        ])
        ->assertRedirect(route('profile.show'));

    $enrollment = MFaceEnrollment::where('intUser_ID', $intern->intUser_ID)->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->txtFaceEnrollmentAlgorithm)->toBe('insightface-buffalo_l-v1')
        ->and($enrollment->intFaceEnrollmentSampleCount)->toBe(3)
        ->and($enrollment->txtFaceEnrollmentDescriptor)->toHaveCount(512);
});

it('blocks mentor from checking in', function () {
    $mentor = createAttendanceUser('Mentor');

    $this->from(route('attendance.index'))
        ->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->post(route('attendance.check-in.store'))
        ->assertForbidden();

    expect(TrAttendance::count())->toBe(0);
});

it('checks in late using python face service verification result', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'));

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Terlambat');
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->txtAttendanceStatus)->toBe('Terlambat')
        ->and($attendance->txtAttendanceClockInStatus)->toBe('Terlambat')
        ->and($attendance->dtmAttendanceClockOut)->toBeNull()
        ->and((float) $attendance->floatAttendanceFaceDistance)->toBe(0.22)
        ->and($attendance->txtAttendanceFaceAlgorithm)->toBe('insightface-buffalo_l-v1');
});

it('stores on time clock in status using python face service verification result', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'));
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->txtAttendanceStatus)->toBe('Hadir')
        ->and($attendance->txtAttendanceClockInStatus)->toBe('Tepat Waktu');
});

it('keeps disabled clock in button neutral after an on time clock in passes the deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);

        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Tepat Waktu')
            ->assertSee('Clock In')
            ->assertDontSee('Clock In Terlambat');
    } finally {
        Carbon::setTestNow();
    }
});

it('shows late status when stored clock in is past the clock in deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:30:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 10:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Terlambat');
    } finally {
        Carbon::setTestNow();
    }
});

it('checks out inside the clock out window using face id and location', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 17:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-out.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'));
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance->dtmAttendanceClockOut)->not->toBeNull()
        ->and($attendance->txtAttendanceClockOutStatus)->toBe('Tepat Waktu')
        ->and((float) $attendance->floatAttendanceClockOutFaceDistance)->toBe(0.22)
        ->and($attendance->txtAttendanceClockOutLocationUrl)->toContain('google.com/maps');
});

it('allows late clock out until end of day when clock in happened before clock out deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 20:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-out.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'));
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance->dtmAttendanceClockOut)->not->toBeNull()
        ->and($attendance->txtAttendanceStatus)->toBe('Hadir')
        ->and($attendance->txtAttendanceClockOutStatus)->toBe('Terlambat')
        ->and((float) $attendance->floatAttendanceClockOutFaceDistance)->toBe(0.22);
});

it('allows clock out when clock in time is before the clock out deadline on the same attendance date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 20:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 14:20:00', 'UTC'),
            'txtAttendanceStatus' => 'Terlambat',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-out.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'))
            ->assertSessionDoesntHaveErrors();
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance->dtmAttendanceClockOut)->not->toBeNull()
        ->and($attendance->txtAttendanceClockOutStatus)->toBe('Terlambat');
});

it('automatically clocks out when clock in happens after the clock out deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 20:00:00', 'Asia/Jakarta'));

    try {
        $intern = createAttendanceUser('Intern');
        createFaceEnrollment($intern);
        mockFaceVerify();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'));

        $this->from(route('attendance.index'))
            ->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-out.store'), attendancePostPayload())
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasErrors('attendance');
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance->txtAttendanceStatus)->toBe('Terlambat')
        ->and($attendance->txtAttendanceClockInStatus)->toBe('Terlambat')
        ->and($attendance->dtmAttendanceClockOut)->not->toBeNull()
        ->and($attendance->txtAttendanceClockOutStatus)->toBe('Terlambat')
        ->and((float) $attendance->floatAttendanceClockOutFaceDistance)->toBe(0.22)
        ->and($attendance->txtAttendanceClockOutNote)->toBe('Clock Out otomatis karena Clock In melewati batas Clock Out');
});

it('summarizes only the current Monday to Friday workweek', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    $intern = createAttendanceUser('Intern');

    try {
        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('06 Jul 2026')
            ->assertSee('10 Jul 2026')
            ->assertDontSee('11 Jul 2026')
            ->assertDontSee('12 Jul 2026');
    } finally {
        Carbon::setTestNow();
    }
});

it('renders HRD attendance detail with date and intern filters', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    try {
        $hrd = createAttendanceUser('HRD');
        $firstIntern = createAttendanceUser('Intern');
        $secondIntern = createAttendanceUser('Intern Two');
        TrAttendance::create([
            'intUser_ID' => $firstIntern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'dtmAttendanceClockOut' => Carbon::parse('2026-07-08 17:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockOutStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.index', [
                'from' => '2026-07-08',
                'to' => '2026-07-08',
                'intUser_ID' => $firstIntern->intUser_ID,
            ]))
            ->assertOk()
            ->assertSee('Detail Absensi')
            ->assertSee('Attendance Intern')
            ->assertSee('data-attendance-detail-intern="'.$firstIntern->intUser_ID.'"', false)
            ->assertDontSee('data-attendance-detail-intern="'.$secondIntern->intUser_ID.'"', false);
    } finally {
        Carbon::setTestNow();
    }
});

it('defaults attendance detail and salary slip periods to the last 30 days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    try {
        $hrd = createAttendanceUser('HRD');
        createAttendanceUser('Intern');

        $response = $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.index', ['tab' => 'detail']))
            ->assertOk();

        expect(substr_count($response->getContent(), 'value="2026-06-09"'))->toBeGreaterThanOrEqual(2)
            ->and(substr_count($response->getContent(), 'value="2026-07-09"'))->toBeGreaterThanOrEqual(2);
    } finally {
        Carbon::setTestNow();
    }
});

it('allows HRD to export filtered attendance report and salary slip', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    try {
        $hrd = createAttendanceUser('HRD');
        MAdminProfile::create([
            'intUser_ID' => $hrd->intUser_ID,
            'txtAdminProfileName' => 'HRD Exporter',
            'txtAdminProfileGender' => 'Perempuan',
            'txtAdminProfileDepartment' => 'Human Resources',
            'txtAdminProfilePosition' => 'HRD',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $intern = createAttendanceUser('Intern');
        $intern->intern->update([
            'txtUniversity' => 'Institut Teknologi Sepuluh November',
            'txtInternCostCenter' => 'MDP',
        ]);
        TrAttendance::create([
            'intUser_ID' => $intern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'dtmAttendanceClockOut' => Carbon::parse('2026-07-08 17:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtAttendanceClockOutStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $query = [
            'from' => '2026-07-08',
            'to' => '2026-07-08',
            'intUser_ID' => $intern->intUser_ID,
        ];

        $excelResponse = $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.export.excel', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $excelPath = tempnam(sys_get_temp_dir(), 'attendance-export-');
        file_put_contents($excelPath, $excelResponse->content());
        $spreadsheet = IOFactory::load($excelPath);

        $sheet = $spreadsheet->getActiveSheet();

        expect($spreadsheet->getSheetCount())->toBe(2)
            ->and($sheet->getCell('A2')->getValue())->toBe('Period')
            ->and($sheet->getCell('A3')->getValue())->toBe('Generated')
            ->and($sheet->getCell('A4')->getValue())->toBe('Generated By')
            ->and($sheet->getCell('B4')->getValue())->toBe('HRD Exporter')
            ->and(round($sheet->getColumnDimension('A')->getWidth(), 2))->toBe(27.22)
            ->and(round($sheet->getColumnDimension('B')->getWidth(), 2))->toBe(16.22)
            ->and($sheet->getCell('B7')->getValue())->toBe('H (Tepat Waktu)')
            ->and($sheet->getCell('C5')->getValue())->toBe("Masuk\n(WFH/WFO)")
            ->and($sheet->getCell('G5')->getValue())->toBe('Legend Kehadiran')
            ->and($sheet->getCell('G6')->getValue())->toBe('H')
            ->and($sheet->getCell('H6')->getValue())->toBe('Hadir')
            ->and($sheet->getStyle('G6')->getFill()->getStartColor()->getRGB())->toBe('7ED957');

        $paymentSheet = $spreadsheet->getSheet(1);

        expect($paymentSheet->getTitle())->toBe('Pembayaran Transport')
            ->and($paymentSheet->getCell('A1')->getValue())->toBe('PEMBAYARAN UANG TRANSPORT PKL/MAGANG Juli 2026')
            ->and($paymentSheet->getMergeCells())->toHaveKeys(['G2:G4', 'J2:J4', 'K2:K4', 'L2:L4'])
            ->and($paymentSheet->getCell('G2')->getValue())->toBe("Jenjang\nPendidikan")
            ->and($paymentSheet->getCell('J2')->getValue())->toBe("Total\nHari\nKerja")
            ->and($paymentSheet->getCell('K2')->getValue())->toBe("Uang PKL /\nMAGANG")
            ->and($paymentSheet->getCell('L2')->getValue())->toBe("Total\nDiterima Aktual")
            ->and($paymentSheet->getCell('B5')->getValue())->toBe('Attendance Intern')
            ->and($paymentSheet->getCell('D5')->getValue())->toBe('Institut Teknologi Sepuluh November')
            ->and($paymentSheet->getRowDimension(5)->getRowHeight())->toBeGreaterThan(20)
            ->and($paymentSheet->getCell('H5')->getValue())->toBe('MDP')
            ->and($paymentSheet->getCell('I5')->getValue())->toBe('Digitalisasi')
            ->and($paymentSheet->getCell('J5')->getValue())->toBe(1)
            ->and((float) $paymentSheet->getCell('K5')->getValue())->toBe(100000.0)
            ->and((float) $paymentSheet->getCell('L5')->getValue())->toBe(100000.0)
            ->and(round($paymentSheet->getColumnDimension('M')->getWidth(), 2))->toBe(14.55)
            ->and($paymentSheet->getCell('M5')->getValue())->toBeNull()
            ->and($paymentSheet->getCell('N5')->getValue())->toBeNull()
            ->and($paymentSheet->getCell('O5')->getValue())->toBeNull()
            ->and($paymentSheet->getCell('P5')->getValue())->toBeNull()
            ->and($paymentSheet->getCell('A11')->getValue())->toBe('TOTAL')
            ->and((float) $paymentSheet->getCell('H11')->getValue())->toBe(100000.0)
            ->and($paymentSheet->getCell('F22')->getValue())->toBe('Departemen/ Cost Center')
            ->and($paymentSheet->getCell('G22')->getValue())->toBe('Count of Departemen/ Cost Center')
            ->and($paymentSheet->getCell('H22')->getValue())->toBe("Sum of  Total Diterima\nAktual")
            ->and($paymentSheet->getCell('F24')->getValue())->toBe('MDP')
            ->and($paymentSheet->getCell('G24')->getValue())->toBe(1)
            ->and((float) $paymentSheet->getCell('H24')->getValue())->toBe(100000.0)
            ->and($paymentSheet->getCell('F25')->getValue())->toBe('(blank)')
            ->and($paymentSheet->getCell('F26')->getValue())->toBe('Grand Total')
            ->and($paymentSheet->getCell('G26')->getValue())->toBe(1)
            ->and((float) $paymentSheet->getCell('H26')->getValue())->toBe(100000.0);

        $spreadsheet->disconnectWorksheets();
        unlink($excelPath);

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.report.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.salary-slip.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    } finally {
        Carbon::setTestNow();
    }
});

it('allows HRD to send salary slips to all active interns and shows them on each profile', function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));

    try {
        $hrd = createAttendanceUser('HRD');
        $firstIntern = createAttendanceUser('Intern');
        $secondIntern = createAttendanceUser('Intern Two');
        TrAttendance::create([
            'intUser_ID' => $firstIntern->intUser_ID,
            'dtmAttendanceDate' => '2026-07-08',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-08 08:00:00', 'Asia/Jakarta'),
            'dtmAttendanceClockOut' => Carbon::parse('2026-07-08 17:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtAttendanceClockOutStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.index', ['tab' => 'detail']))
            ->assertOk()
            ->assertSee('Kirim Slip Gaji')
            ->assertSee('Download Slip Gaji')
            ->assertSee('All Intern Periode Ini (2)');

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->post(route('attendance.salary-slips.store'), [
                'dtmSalarySlipPeriodStart' => '2026-07-08',
                'dtmSalarySlipPeriodEnd' => '2026-07-08',
                'intIntern_ID' => 0,
            ])
            ->assertRedirect(route('attendance.index', ['tab' => 'detail']))
            ->assertSessionHas('success');

        $salarySlips = TrSalarySlip::orderBy('intIntern_ID')->get();

        expect($salarySlips)->toHaveCount(2)
            ->and($salarySlips->firstWhere('intIntern_ID', $firstIntern->intern->intIntern_ID)->floatSalarySlipNetSalary)->toBe(100000.0)
            ->and($salarySlips->firstWhere('intIntern_ID', $secondIntern->intern->intIntern_ID)->floatSalarySlipNetSalary)->toBe(0.0);

        foreach ($salarySlips as $salarySlip) {
            Storage::disk('local')->assertExists($salarySlip->txtSalarySlipFilePath);
        }

        $firstSalarySlip = $salarySlips->firstWhere('intIntern_ID', $firstIntern->intern->intIntern_ID);
        $secondSalarySlip = $salarySlips->firstWhere('intIntern_ID', $secondIntern->intern->intIntern_ID);

        $this->withSession(['auth_user_id' => $firstIntern->intUser_ID])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSeeInOrder(['Projects', 'Slip Gaji'])
            ->assertSee('Download')
            ->assertSee('08 Jul 2026 - 08 Jul 2026')
            ->assertSee('Rp 100.000');

        $this->withSession(['auth_user_id' => $firstIntern->intUser_ID])
            ->get(route('profile.salary-slips.show', $firstSalarySlip))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename='.$firstSalarySlip->txtSalarySlipFileName);

        $this->withSession(['auth_user_id' => $firstIntern->intUser_ID])
            ->get(route('profile.salary-slips.show', $secondSalarySlip))
            ->assertForbidden();
    } finally {
        Carbon::setTestNow();
    }
});

it('includes interns that became inactive after the period started in exports and salary slips', function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00', 'Asia/Jakarta'));

    try {
        $hrd = createAttendanceUser('HRD');
        $activeIntern = createAttendanceUser('Intern');
        $inactiveMidPeriod = createAttendanceUser('Inactive Mid Period Intern');
        $inactiveBeforePeriod = createAttendanceUser('Inactive Before Period Intern');

        $activeIntern->intern->update(['txtInternName' => 'Active Period Intern']);
        $inactiveMidPeriod->update(['bitActive' => false]);
        $inactiveMidPeriod->intern->update([
            'txtInternName' => 'Inactive Mid Period Intern',
            'dtmEndDate' => '2026-08-15',
            'bitActive' => false,
        ]);
        $inactiveBeforePeriod->update(['bitActive' => false]);
        $inactiveBeforePeriod->intern->update([
            'txtInternName' => 'Inactive Before Period Intern',
            'dtmEndDate' => '2026-07-31',
            'bitActive' => false,
        ]);

        TrAttendance::create([
            'intUser_ID' => $activeIntern->intUser_ID,
            'dtmAttendanceDate' => '2026-08-14',
            'dtmAttendanceClockIn' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        TrAttendance::create([
            'intUser_ID' => $inactiveMidPeriod->intUser_ID,
            'dtmAttendanceDate' => '2026-08-14',
            'dtmAttendanceClockIn' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $period = [
            'from' => '2026-08-01',
            'to' => '2026-08-20',
        ];
        $excelResponse = $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('attendance.export.excel', $period))
            ->assertOk();
        $excelPath = tempnam(sys_get_temp_dir(), 'attendance-export-');
        file_put_contents($excelPath, $excelResponse->content());
        $spreadsheet = IOFactory::load($excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $names = collect(range(7, 20))
            ->map(fn (int $row) => $sheet->getCell('A'.$row)->getValue())
            ->filter()
            ->values();

        expect($names)->toContain('Active Period Intern')
            ->and($names)->toContain('Inactive Mid Period Intern')
            ->and($names)->not->toContain('Inactive Before Period Intern');

        $spreadsheet->disconnectWorksheets();
        unlink($excelPath);

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->post(route('attendance.salary-slips.store'), [
                'dtmSalarySlipPeriodStart' => '2026-08-01',
                'dtmSalarySlipPeriodEnd' => '2026-08-20',
                'intIntern_ID' => 0,
            ])
            ->assertRedirect(route('attendance.index', ['tab' => 'detail']))
            ->assertSessionHas('success');

        $salarySlips = TrSalarySlip::all();

        expect($salarySlips)->toHaveCount(2)
            ->and($salarySlips->pluck('intIntern_ID')->all())->toContain($activeIntern->intern->intIntern_ID)
            ->and($salarySlips->pluck('intIntern_ID')->all())->toContain($inactiveMidPeriod->intern->intIntern_ID)
            ->and($salarySlips->pluck('intIntern_ID')->all())->not->toContain($inactiveBeforePeriod->intern->intIntern_ID);
    } finally {
        Carbon::setTestNow();
    }
});

it('downloads one salary slip as PDF and all active intern slips as ZIP without storing them', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00', 'Asia/Jakarta'));
    $zipPath = null;

    try {
        $hrd = createAttendanceUser('HRD');
        $firstIntern = createAttendanceUser('Intern');
        createAttendanceUser('Intern Two');
        $payload = [
            'dtmSalarySlipPeriodStart' => '2026-07-08',
            'dtmSalarySlipPeriodEnd' => '2026-07-08',
        ];

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->post(route('attendance.salary-slips.download'), [
                ...$payload,
                'intIntern_ID' => $firstIntern->intern->intIntern_ID,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('salary-slip-attendance-intern-2026-07-08-2026-07-08.pdf');

        expect(TrSalarySlip::count())->toBe(0);

        $zipResponse = $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->post(route('attendance.salary-slips.download'), [
                ...$payload,
                'intIntern_ID' => 0,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/zip')
            ->assertDownload('salary-slips-2026-07-08-2026-07-08.zip');
        $zipPath = $zipResponse->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;

        expect($zip->open($zipPath))->toBeTrue()
            ->and($zip->numFiles)->toBe(2);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            expect($zip->getNameIndex($index))->toEndWith('.pdf')
                ->and($zip->getFromIndex($index))->toStartWith('%PDF');
        }

        $zip->close();

        expect(TrSalarySlip::count())->toBe(0);
    } finally {
        if ($zipPath && is_file($zipPath)) {
            unlink($zipPath);
        }

        Carbon::setTestNow();
    }
});

it('renders and updates HRD profile data', function () {
    $hrd = createAttendanceUser('HRD');

    MAdminProfile::create([
        'intUser_ID' => $hrd->intUser_ID,
        'txtAdminProfileName' => 'HRD User',
        'txtAdminProfileGender' => 'Perempuan',
        'txtAdminProfileDepartment' => 'Human Resources',
        'txtAdminProfilePosition' => 'HRD',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $this->withSession(['auth_user_id' => $hrd->intUser_ID])
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Admin Profile')
        ->assertSee('HRD User')
        ->assertSee('Human Resources');

    $this->withSession(['auth_user_id' => $hrd->intUser_ID])
        ->put(route('profile.update'), [
            'txtAdminProfileName' => 'HRD Updated',
            'txtEmail' => 'hrd.updated@attendance.test',
            'txtAdminProfileGender' => 'Perempuan',
            'txtAdminProfileDepartment' => 'People Operations',
            'txtAdminProfilePosition' => 'HR Business Partner',
            'txtAdminProfilePhone' => '08123456789',
            'txtAdminProfileBio' => 'Mengelola administrasi internship.',
            'bitActive' => '1',
        ])
        ->assertRedirect(route('profile.show'));

    $profile = MAdminProfile::where('intUser_ID', $hrd->intUser_ID)->first();
    $hrd->refresh();

    expect($profile->txtAdminProfileName)->toBe('HRD Updated')
        ->and($profile->txtAdminProfileDepartment)->toBe('People Operations')
        ->and($profile->txtAdminProfilePhone)->toBe('08123456789')
        ->and($hrd->txtEmail)->toBe('hrd.updated@attendance.test');
});

function createAttendanceUser(string $role): MUser
{
    $now = now();
    $actualRole = in_array($role, ['Mentor', 'Headmaster', 'HRD'], true) ? $role : 'Intern';
    $user = MUser::create([
        'txtEmail' => str_replace(' ', '-', strtolower($role)).'@attendance.test',
        'txtPassword' => 'secret',
        'txtRole' => $actualRole,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);

    if ($role === 'Mentor') {
        MMentor::create([
            'intUser_ID' => $user->intUser_ID,
            'txtMentorName' => 'Attendance Mentor',
            'txtRole' => 'Mentor',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => $now,
        ]);
    } elseif ($actualRole === 'Intern') {
        MIntern::create([
            'intUser_ID' => $user->intUser_ID,
            'txtInternNo' => 'INT-ATT',
            'txtInternName' => $role === 'Intern Two' ? 'Attendance Intern Two' : 'Attendance Intern',
            'txtInternType' => 'digitalisasi',
            'floatInternSalary' => 100000,
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => $now,
        ]);

        MAttendanceLocation::firstOrCreate(
            ['txtAttendanceLocationCode' => 'TEST-OFFICE'],
            [
                'txtAttendanceLocationName' => 'Test Office',
                'txtAttendanceLocationAddress' => 'Jakarta test location',
                'floatAttendanceLocationLatitude' => -6.2,
                'floatAttendanceLocationLongitude' => 106.816666,
                'intAttendanceLocationRadiusMeter' => 100,
                'intAttendanceLocationToleranceMeter' => 50,
                'intAttendanceLocationMaximumAccuracyMeter' => 200,
                'bitActive' => true,
                'txtInsertedBy' => 'test',
                'dtmInserted' => $now,
            ],
        );
    }

    return $user;
}

function createFaceEnrollment(MUser $intern): MFaceEnrollment
{
    return MFaceEnrollment::create([
        'intUser_ID' => $intern->intUser_ID,
        'txtFaceEnrollmentDescriptor' => array_fill(0, 512, 0.01),
        'txtFaceEnrollmentAlgorithm' => 'insightface-buffalo_l-v1',
        'intFaceEnrollmentSampleCount' => 3,
        'floatFaceEnrollmentQuality' => 0.91,
        'dtmFaceEnrollmentRegistered' => now(),
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
}

function mockFaceVerify(): void
{
    $mock = Mockery::mock(FaceRecognitionService::class);
    $mock->shouldReceive('verify')
        ->once()
        ->andReturn([
            'match' => true,
            'distance' => 0.22,
            'similarity' => 0.78,
            'threshold' => 0.38,
            'algorithm' => 'insightface-buffalo_l-v1',
            'quality' => 0.9,
        ]);

    app()->instance(FaceRecognitionService::class, $mock);
}

function attendancePostPayload(): array
{
    return [
        'txtAttendanceCapturedImage' => attendanceImagePayload(),
        'floatAttendanceLatitude' => '-6.200000',
        'floatAttendanceLongitude' => '106.816666',
        'floatAttendanceLocationAccuracy' => '25',
        'txtAttendanceDevice' => 'test browser',
    ];
}

function attendanceImagePayload(): string
{
    return 'data:image/jpeg;base64,'.base64_encode('fake-image');
}
