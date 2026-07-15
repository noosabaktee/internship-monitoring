<?php

use App\Models\MAdminProfile;
use App\Models\MAttendanceLocation;
use App\Models\MAttendanceSetting;
use App\Models\MFaceEnrollment;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Services\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        expect($spreadsheet->getActiveSheet()->getCell('I2')->getValue())->toBe('HRD Exporter');

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
