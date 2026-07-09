<?php

use App\Models\MAttendanceSetting;
use App\Models\MFaceEnrollment;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Services\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('renders attendance page for an authenticated user', function () {
    $user = createAttendanceUser('Intern');

    $this->withSession(['auth_user_id' => $user->intUser_ID])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertSee('ABSENSI');
});

it('allows mentor to update attendance settings', function () {
    $mentor = createAttendanceUser('Mentor');

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->put(route('attendance.settings.update'), [
            'txtAttendanceSettingStartTime' => '06:30',
            'txtAttendanceSettingEndTime' => '18:00',
            'floatAttendanceSettingFaceThreshold' => '0.70',
        ])
        ->assertRedirect(route('attendance.index'));

    $setting = MAttendanceSetting::find(1);

    expect($setting->txtAttendanceSettingStartTime)->toBe('06:30')
        ->and($setting->txtAttendanceSettingEndTime)->toBe('18:00')
        ->and((float) $setting->floatAttendanceSettingFaceThreshold)->toBe(0.7);
});

it('blocks intern from updating attendance settings', function () {
    $intern = createAttendanceUser('Intern');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->put(route('attendance.settings.update'), [
            'txtAttendanceSettingStartTime' => '06:30',
            'txtAttendanceSettingEndTime' => '18:00',
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
        ->post(route('attendance.face-enrollment.store'), [
            'txtFaceEnrollmentImages' => json_encode([
                attendanceImagePayload(),
                attendanceImagePayload(),
                attendanceImagePayload(),
            ]),
            'intFaceEnrollmentSampleCount' => '3',
            'floatFaceEnrollmentQuality' => '1',
        ])
        ->assertRedirect(route('attendance.index'));

    $enrollment = MFaceEnrollment::where('intUser_ID', $intern->intUser_ID)->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->txtFaceEnrollmentAlgorithm)->toBe('insightface-buffalo_l-v1')
        ->and($enrollment->intFaceEnrollmentSampleCount)->toBe(3)
        ->and($enrollment->txtFaceEnrollmentDescriptor)->toHaveCount(512);
});

it('checks in using python face service verification result', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'Asia/Jakarta'));

    $intern = createAttendanceUser('Intern');
    $embedding = array_fill(0, 512, 0.01);

    MFaceEnrollment::create([
        'intUser_ID' => $intern->intUser_ID,
        'txtFaceEnrollmentDescriptor' => $embedding,
        'txtFaceEnrollmentAlgorithm' => 'insightface-buffalo_l-v1',
        'intFaceEnrollmentSampleCount' => 3,
        'floatFaceEnrollmentQuality' => 0.91,
        'dtmFaceEnrollmentRegistered' => now(),
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $this->mock(FaceRecognitionService::class, function ($mock) {
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
    });

    try {
        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), [
                'txtAttendanceCapturedImage' => attendanceImagePayload(),
                'floatAttendanceLatitude' => '-6.200000',
                'floatAttendanceLongitude' => '106.816666',
                'floatAttendanceLocationAccuracy' => '25',
                'txtAttendanceDevice' => 'test browser',
            ])
            ->assertRedirect(route('attendance.index'));
    } finally {
        Carbon::setTestNow();
    }

    $attendance = TrAttendance::where('intUser_ID', $intern->intUser_ID)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->txtAttendanceStatus)->toBe('Hadir')
        ->and((float) $attendance->floatAttendanceFaceDistance)->toBe(0.22)
        ->and($attendance->txtAttendanceFaceAlgorithm)->toBe('insightface-buffalo_l-v1');
});

function createAttendanceUser(string $role): MUser
{
    $now = now();
    $user = MUser::create([
        'txtEmail' => strtolower($role) . '@attendance.test',
        'txtPassword' => 'secret',
        'txtRole' => $role,
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
    } else {
        MIntern::create([
            'intUser_ID' => $user->intUser_ID,
            'txtInternNo' => 'INT-ATT',
            'txtInternName' => 'Attendance Intern',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => $now,
        ]);
    }

    return $user;
}

function attendanceImagePayload(): string
{
    return 'data:image/jpeg;base64,' . base64_encode('fake-image');
}
