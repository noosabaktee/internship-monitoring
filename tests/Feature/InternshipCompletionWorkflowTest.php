<?php

use App\Models\MAttendanceLocation;
use App\Models\MFaceEnrollment;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Models\TrNotification;
use App\Models\TrProjectStage;
use App\Models\TrWorkFromHomeRequest;
use App\Services\FaceRecognitionService;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('calculates assessment grades from the certificate score ranges', function () {
    expect(TrEvaluation::gradeFor(100))->toBe('A')
        ->and(TrEvaluation::gradeFor(90))->toBe('A')
        ->and(TrEvaluation::gradeFor(89.99))->toBe('B')
        ->and(TrEvaluation::gradeFor(70))->toBe('C')
        ->and(TrEvaluation::gradeFor(60))->toBe('D')
        ->and(TrEvaluation::gradeFor(59.99))->toBe('E');
});

it('publishes one final report and locks the certificate until it is issued', function () {
    $mentor = revisionUser('Mentor', 'Final Report Mentor');
    $intern = revisionUser('Intern', 'Final Report Intern', '2026-07-31');
    $payload = revisionEvaluationPayload($intern->intern->intIntern_ID);

    expect(Schema::hasColumn('trEvaluation', 'dtmPeriod'))->toBeFalse();

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->post(route('analytics.store'), $payload)
        ->assertRedirect(route('analytics.index'))
        ->assertSessionHasNoErrors();

    $evaluation = TrEvaluation::first();

    expect($evaluation)->not->toBeNull()
        ->and($evaluation->dtmEvaluationCompleted)->not->toBeNull()
        ->and((float) $evaluation->floatExposureScore)->toBe(87.5)
        ->and($evaluation->bitEvaluationCertificatePublished)->toBeFalse();

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('INTERNSHIP DASHBOARD');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Final Report Intern');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertSee('Rapor Final Saya')
        ->assertSee('Mentor atau Headmaster belum menerbitkan sertifikat')
        ->assertSee('Menunggu diterbitkan')
        ->assertDontSee('Technical Skills')
        ->assertDontSee('Cepat belajar dan konsisten menyelesaikan tanggung jawab.')
        ->assertDontSee('Unduh Sertifikat');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('analytics.certificate', $evaluation->intEvaluation_ID))
        ->assertNotFound();

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertSee('Terbitkan Sertifikat');

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->patch(route('analytics.certificate.publish', $evaluation->intEvaluation_ID))
        ->assertRedirect(route('analytics.index'))
        ->assertSessionHasNoErrors();

    expect($evaluation->refresh()->bitEvaluationCertificatePublished)->toBeTrue()
        ->and($evaluation->dtmEvaluationCertificatePublished)->not->toBeNull()
        ->and($evaluation->intEvaluationCertificatePublishedByUser_ID)->toBe($mentor->intUser_ID);

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertSee('Technical Skills')
        ->assertSee('Professionalism &amp; Work Ethics', false)
        ->assertSee('Unduh Sertifikat');

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('analytics.certificate', $evaluation->intEvaluation_ID))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->from(route('analytics.create'))
        ->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->post(route('analytics.store'), $payload)
        ->assertRedirect(route('analytics.create'))
        ->assertSessionHasErrors('intIntern_ID');

    expect(TrEvaluation::where('intIntern_ID', $intern->intern->intIntern_ID)->count())->toBe(1)
        ->and(TrNotification::where('intUser_ID', $intern->intUser_ID)->where('txtNotificationType', 'certificate')->exists())->toBeTrue();
});

it('lets every intern type open their report while exposure stays digitalisasi only', function () {
    $mentor = revisionUser('Mentor', 'Certificate Manager Mentor');
    $regularIntern = revisionUser('Intern', 'Regular Report Intern', '2026-12-31', RoleAccess::INTERN_REGULAR);
    $pklIntern = revisionUser('Intern', 'PKL Report Intern', '2026-12-31', RoleAccess::INTERN_PKL);
    $digitalIntern = revisionUser('Intern', 'Digital Exposure Intern', '2026-07-31', RoleAccess::INTERN_DIGITALISASI);

    TrEvaluation::create([
        ...revisionEvaluationPayload($digitalIntern->intern->intIntern_ID),
        'dtmEvaluationCompleted' => '2026-07-15',
        'floatExposureScore' => 87.5,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    foreach ([$regularIntern, $pklIntern, $digitalIntern] as $internUser) {
        $this->withSession(['auth_user_id' => $internUser->intUser_ID])
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Rapor Final Saya');
    }

    $this->withSession(['auth_user_id' => $regularIntern->intUser_ID])
        ->get(route('exposure.index'))
        ->assertForbidden();

    $this->withSession(['auth_user_id' => $pklIntern->intUser_ID])
        ->get(route('exposure.index'))
        ->assertForbidden();

    $this->withSession(['auth_user_id' => $digitalIntern->intUser_ID])
        ->get(route('exposure.index'))
        ->assertOk();

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->get(route('analytics.create'))
        ->assertOk()
        ->assertSee('Add Sertifikat')
        ->assertSee('value="'.$regularIntern->intern->intIntern_ID.'"', false)
        ->assertSee('Regular Report Intern')
        ->assertSee('value="'.$pklIntern->intern->intIntern_ID.'"', false)
        ->assertSee('PKL Report Intern')
        ->assertDontSee('value="'.$digitalIntern->intern->intIntern_ID.'"', false);

    $this->withSession(['auth_user_id' => $mentor->intUser_ID])
        ->post(route('analytics.store'), revisionEvaluationPayload($pklIntern->intern->intIntern_ID))
        ->assertRedirect(route('analytics.index'))
        ->assertSessionHasNoErrors();

    expect(TrEvaluation::where('intIntern_ID', $pklIntern->intern->intIntern_ID)->exists())->toBeTrue();
});

it('adds a notification for active users when calendar sharing is created', function () {
    $headmaster = revisionUser('Headmaster', 'Calendar Owner');
    $intern = revisionUser('Intern', 'Calendar Recipient', '2026-12-31', RoleAccess::INTERN_PKL);

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->post(route('calendar-sharing.store'), [
            'txtCalendarSharingTheme' => 'Safety Sharing',
            'txtCalendarSharingObjective' => 'Meningkatkan awareness',
            'txtCalendarSharingDescription' => 'Sesi sharing keselamatan kerja.',
            'txtCalendarSharingTargetAudience' => 'All Intern',
            'dtmCalendarSharingDate' => '2026-08-03 09:00:00',
            'txtCalendarSharingStatus' => 'Open',
        ])
        ->assertRedirect(route('calendar-sharing.index'))
        ->assertSessionHasNoErrors();

    $notification = TrNotification::where('intUser_ID', $intern->intUser_ID)
        ->where('txtNotificationType', 'calendar')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->txtNotificationTitle)->toBe('Calendar sharing baru')
        ->and($notification->txtNotificationMessage)->toContain('Safety Sharing')
        ->and($notification->txtNotificationLink)->toBe(route('calendar-sharing.index'))
        ->and($notification->dtmNotificationRead)->toBeNull();
});

it('filters exposure projects and interns to digitalisasi data', function () {
    $headmaster = revisionUser('Headmaster', 'Exposure Headmaster');
    $mentor = revisionUser('Mentor', 'Exposure Mentor');
    $digitalIntern = revisionUser('Intern', 'Digital Curve Intern', '2026-08-31', RoleAccess::INTERN_DIGITALISASI);
    $regularIntern = revisionUser('Intern', 'Regular Curve Intern', '2026-08-31', RoleAccess::INTERN_REGULAR);

    $digitalProject = revisionProject('Digital Curve Project');
    $regularProject = revisionProject('Regular Curve Project');

    revisionProjectStage($digitalProject);
    revisionProjectStage($regularProject);
    revisionProjectAssignment($digitalProject, $digitalIntern->intern, $mentor->mentor);
    revisionProjectAssignment($regularProject, $regularIntern->intern, $mentor->mentor);

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->get(route('exposure.index'))
        ->assertOk()
        ->assertSee('Digital Curve Project')
        ->assertSee('Digital Curve Intern')
        ->assertDontSee('Regular Curve Project')
        ->assertDontSee('Regular Curve Intern');

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('Digital Curve Project')
        ->assertDontSee('Regular Curve Project')
        ->assertDontSee('Regular Curve Intern');
});

it('renders the public login summary with finalized evaluations', function () {
    $intern = revisionUser('Intern', 'Login Summary Intern', '2026-07-31');
    TrEvaluation::create([
        ...revisionEvaluationPayload($intern->intern->intIntern_ID),
        'dtmEvaluationCompleted' => '2026-07-15',
        'floatExposureScore' => 87.5,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Login Summary Intern');
});

it('stores notification countdowns as whole calendar days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 07:00:00', 'Asia/Jakarta'));

    try {
        revisionUser('Intern', 'Countdown Intern', '2026-07-21');
        $hrd = revisionUser('HRD', 'Countdown HRD');

        app(NotificationService::class)->syncFor($hrd);

        $message = TrNotification::where('intUser_ID', $hrd->intUser_ID)
            ->where('txtNotificationTitle', 'Intern segera lulus')
            ->value('txtNotificationMessage');

        expect($message)->toContain('6 hari')
            ->not->toMatch('/\d+[\.,]\d+\s+hari/');
    } finally {
        Carbon::setTestNow();
    }
});

it('blocks attendance after effective internship end date and excludes the intern from admin monitoring', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00', 'Asia/Jakarta'));

    try {
        $intern = revisionUser('Intern', 'Completed Internship', '2026-07-14');
        $headmaster = revisionUser('Headmaster', 'Attendance Headmaster');

        $this->from(route('attendance.index'))
            ->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'))
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasErrors('attendance');

        expect(TrAttendance::count())->toBe(0);

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Masa internship kamu sudah selesai');

        $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertDontSee('Completed Internship')
            ->assertSee('data-attendance-admin-tab="today"', false)
            ->assertSee('data-attendance-admin-tab="detail"', false)
            ->assertSee('data-attendance-admin-tab="settings"', false)
            ->assertSee('Pengaturan Lokasi Absensi')
            ->assertSee('Gunakan Lokasi Saat Ini')
            ->assertSee('attendanceLocationSettingForm')
            ->assertSee('attendanceLocationMap')
            ->assertDontSee('Tambah Lokasi')
            ->assertDontSee('attendanceLocationCreateModal');
    } finally {
        Carbon::setTestNow();
    }
});

it('keeps the attendance location setting as a singleton', function () {
    $headmaster = revisionUser('Headmaster', 'Location Setting Headmaster');
    $payload = [
        'txtAttendanceLocationCode' => 'KDC-JKT',
        'txtAttendanceLocationName' => 'Kantor Jakarta',
        'txtAttendanceLocationAddress' => 'Jakarta',
        'floatAttendanceLocationLatitude' => -6.2,
        'floatAttendanceLocationLongitude' => 106.816666,
        'intAttendanceLocationRadiusMeter' => 100,
        'intAttendanceLocationToleranceMeter' => 50,
        'intAttendanceLocationMaximumAccuracyMeter' => 200,
        'bitActive' => 1,
    ];

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->post(route('attendance-locations.store'), $payload)
        ->assertRedirect(route('attendance.index', ['tab' => 'settings']))
        ->assertSessionHasNoErrors();

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->post(route('attendance-locations.store'), [
            ...$payload,
            'txtAttendanceLocationCode' => 'KDC-SINGLE',
            'txtAttendanceLocationName' => 'Kantor Utama',
            'intAttendanceLocationRadiusMeter' => 175,
        ])
        ->assertRedirect(route('attendance.index', ['tab' => 'settings']))
        ->assertSessionHasNoErrors();

    expect(MAttendanceLocation::count())->toBe(1)
        ->and(MAttendanceLocation::first()->txtAttendanceLocationCode)->toBe('KDC-SINGLE')
        ->and(MAttendanceLocation::first()->txtAttendanceLocationName)->toBe('Kantor Utama')
        ->and(MAttendanceLocation::first()->intAttendanceLocationRadiusMeter)->toBe(175)
        ->and(MAttendanceLocation::first()->bitActive)->toBeTrue();
});

it('activates location-free WFH attendance only after HRD approval', function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00', 'Asia/Jakarta'));

    try {
        $intern = revisionUser('Intern', 'WFH Intern', '2026-08-31');
        $hrd = revisionUser('HRD', 'WFH HRD');
        revisionFaceEnrollment($intern);

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('work-from-home.store'), [
                'dtmWorkFromHomeRequestStartDate' => '2026-07-15',
                'dtmWorkFromHomeRequestEndDate' => '2026-07-15',
                'txtWorkFromHomeRequestReason' => 'Menunggu teknisi internet di rumah sambil tetap mengerjakan project.',
                'txtWorkFromHomeRequestAttachment' => UploadedFile::fake()->create('support.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $wfhRequest = TrWorkFromHomeRequest::first();
        expect($wfhRequest->txtWorkFromHomeRequestStatus)->toBe('Pending')
            ->and(TrNotification::where('intUser_ID', $hrd->intUser_ID)->where('txtNotificationType', 'wfh')->exists())->toBeTrue();

        $attachmentResponse = $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->get(route('work-from-home.attachment', $wfhRequest->intWorkFromHomeRequest_ID))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');

        expect($attachmentResponse->headers->get('content-disposition'))->toStartWith('inline;');

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('work-from-home.index'))
            ->assertOk()
            ->assertSee('WFH Intern')
            ->assertSee('target="_blank"', false);

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Pengajuan WFH baru');

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->patch(route('work-from-home.approve', $wfhRequest->intWorkFromHomeRequest_ID), [
                'txtWorkFromHomeRequestReviewNote' => 'Disetujui untuk satu hari.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        revisionMockFaceVerification();

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), revisionAttendancePayload(-7.25, 112.75))
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasNoErrors();

        $attendance = TrAttendance::first();
        expect($attendance->txtAttendanceWorkMode)->toBe('WFH')
            ->and($attendance->intWorkFromHomeRequest_ID)->toBe($wfhRequest->intWorkFromHomeRequest_ID)
            ->and($attendance->intAttendanceLocation_ID)->toBeNull();

        $this->withSession(['auth_user_id' => $hrd->intUser_ID])
            ->patch(route('work-from-home.reject', $wfhRequest->intWorkFromHomeRequest_ID), [
                'txtWorkFromHomeRequestReviewNote' => 'WFH dibatalkan setelah absensi.',
            ])
            ->assertSessionHasErrors('wfh');

        expect($wfhRequest->refresh()->txtWorkFromHomeRequestStatus)->toBe('Approved');
    } finally {
        Carbon::setTestNow();
    }
});

it('lets attendance admins revise an unused approved WFH request', function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00', 'Asia/Jakarta'));

    try {
        $intern = revisionUser('Intern', 'Revised WFH Intern', '2026-08-31');
        $headmaster = revisionUser('Headmaster', 'Revised WFH Headmaster');

        $this->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('work-from-home.store'), [
                'dtmWorkFromHomeRequestStartDate' => '2026-07-20',
                'dtmWorkFromHomeRequestEndDate' => '2026-07-20',
                'txtWorkFromHomeRequestReason' => 'Pengajuan jadwal kerja dari rumah yang mungkin berubah.',
                'txtWorkFromHomeRequestAttachment' => UploadedFile::fake()->create('revision.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $wfhRequest = TrWorkFromHomeRequest::firstOrFail();

        $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
            ->patch(route('work-from-home.approve', $wfhRequest->intWorkFromHomeRequest_ID), [
                'txtWorkFromHomeRequestReviewNote' => 'Disetujui sementara.',
            ])
            ->assertSessionHasNoErrors();

        $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
            ->get(route('work-from-home.index'))
            ->assertOk()
            ->assertSee('Ubah keputusan')
            ->assertSee('Batalkan WFH');

        $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
            ->patch(route('work-from-home.reject', $wfhRequest->intWorkFromHomeRequest_ID), [
                'txtWorkFromHomeRequestReviewNote' => 'Rencana WFH dibatalkan dan intern kembali WFO.',
            ])
            ->assertSessionHasNoErrors();

        expect($wfhRequest->refresh()->txtWorkFromHomeRequestStatus)->toBe('Rejected')
            ->and($wfhRequest->txtWorkFromHomeRequestReviewNote)->toBe('Rencana WFH dibatalkan dan intern kembali WFO.');
    } finally {
        Carbon::setTestNow();
    }
});

it('rejects normal attendance outside the configured office radius and tolerance', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00', 'Asia/Jakarta'));

    try {
        $intern = revisionUser('Intern', 'Geofence Intern', '2026-08-31');
        revisionFaceEnrollment($intern);
        MAttendanceLocation::create([
            'txtAttendanceLocationCode' => 'KDC-TEST',
            'txtAttendanceLocationName' => 'KDC Test Office',
            'txtAttendanceLocationAddress' => 'Jakarta',
            'floatAttendanceLocationLatitude' => -6.2,
            'floatAttendanceLocationLongitude' => 106.816666,
            'intAttendanceLocationRadiusMeter' => 100,
            'intAttendanceLocationToleranceMeter' => 50,
            'intAttendanceLocationMaximumAccuracyMeter' => 200,
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        revisionMockFaceVerification();

        $this->from(route('attendance.index'))
            ->withSession(['auth_user_id' => $intern->intUser_ID])
            ->post(route('attendance.check-in.store'), revisionAttendancePayload(-7.25, 112.75))
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasErrors('attendance');

        expect(TrAttendance::count())->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});

function revisionUser(string $role, string $name, ?string $endDate = null, string $internType = RoleAccess::INTERN_DIGITALISASI): MUser
{
    $user = MUser::create([
        'txtEmail' => str($name)->slug('.').'@revision.test',
        'txtPassword' => Hash::make('password'),
        'txtRole' => $role,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    if ($role === 'Intern') {
        MIntern::create([
            'intUser_ID' => $user->intUser_ID,
            'txtInternNo' => 'REV-'.$user->intUser_ID,
            'txtInternName' => $name,
            'txtInternType' => $internType,
            'dtmEndDate' => $endDate,
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
    } elseif ($role === 'Mentor') {
        MMentor::create([
            'intUser_ID' => $user->intUser_ID,
            'txtMentorName' => $name,
            'txtRole' => 'Mentor',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
    }

    return $user->load(['intern', 'mentor']);
}

function revisionProject(string $name, string $type = 'Main'): MProject
{
    return MProject::create([
        'txtProjectName' => $name,
        'txtProjectType' => $type,
        'dtmProjectStartDate' => '2026-07-01',
        'dtmProjectEndDate' => '2026-07-31',
        'txtDescription' => $name.' description',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
}

function revisionProjectStage(MProject $project): void
{
    TrProjectStage::create([
        'intProject_ID' => $project->intProject_ID,
        'intProjectStageNumber' => 1,
        'txtProjectStageStep' => 'Delivery',
        'dtmProjectStageStartDate' => '2026-07-01',
        'dtmProjectStageEndDate' => '2026-07-31',
        'floatProjectStagePlan' => 100,
        'floatProjectStageActual' => 50,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
}

function revisionProjectAssignment(MProject $project, MIntern $intern, MMentor $mentor): void
{
    TrInternProject::create([
        'intIntern_ID' => $intern->intIntern_ID,
        'intProject_ID' => $project->intProject_ID,
        'intMentor_ID' => $mentor->intMentor_ID,
        'floatProgress' => 50,
        'txtStatus' => 'Inprogress',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
}

function revisionEvaluationPayload(int $internId): array
{
    return [
        'intIntern_ID' => $internId,
        'floatDisciplineAttendance' => 90,
        'floatResponsibilityInitiative' => 85,
        'floatTechnicalSkills' => 88,
        'floatTeamwork' => 87,
        'floatCommunicationSkills' => 90,
        'floatCreativityProblemSolving' => 85,
        'floatProfessionalismWorkEthics' => 87.5,
        'txtEvaluationStrength' => 'Cepat belajar dan konsisten menyelesaikan tanggung jawab.',
        'txtEvaluationDevelopment' => 'Perlu lebih percaya diri saat mempresentasikan keputusan teknis.',
        'txtEvaluationRecommendation' => 'Lulus dengan hasil sangat baik dan direkomendasikan untuk kesempatan berikutnya.',
    ];
}

function revisionFaceEnrollment(MUser $intern): void
{
    MFaceEnrollment::create([
        'intUser_ID' => $intern->intUser_ID,
        'txtFaceEnrollmentDescriptor' => array_fill(0, 512, 0.01),
        'txtFaceEnrollmentAlgorithm' => 'insightface-buffalo_l-v1',
        'intFaceEnrollmentSampleCount' => 3,
        'floatFaceEnrollmentQuality' => .9,
        'dtmFaceEnrollmentRegistered' => now(),
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
}

function revisionMockFaceVerification(): void
{
    $mock = Mockery::mock(FaceRecognitionService::class);
    $mock->shouldReceive('verify')->once()->andReturn([
        'match' => true,
        'distance' => .2,
        'similarity' => .8,
        'algorithm' => 'insightface-buffalo_l-v1',
        'quality' => .9,
    ]);
    app()->instance(FaceRecognitionService::class, $mock);
}

function revisionAttendancePayload(float $latitude, float $longitude): array
{
    return [
        'txtAttendanceCapturedImage' => 'data:image/jpeg;base64,'.base64_encode('revision-image'),
        'floatAttendanceLatitude' => $latitude,
        'floatAttendanceLongitude' => $longitude,
        'floatAttendanceLocationAccuracy' => 20,
        'txtAttendanceDevice' => 'Revision test browser',
    ];
}
