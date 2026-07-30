<?php

use App\Models\MIntern;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = MUser::create([
        'intUser_ID' => 1,
        'txtEmail' => 'api.intern@example.com',
        'txtPassword' => Hash::make('secret123'),
        'txtRole' => 'Intern',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    MIntern::create([
        'intIntern_ID' => 1,
        'intUser_ID' => $user->intUser_ID,
        'txtInternNo' => 'INT-001',
        'txtInternName' => 'API Intern',
        'txtInternType' => 'digitalisasi',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
});

it('rejects protected API requests without a bearer token', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

it('logs in and authenticates subsequent API requests with the returned token', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'api.intern@example.com',
        'password' => 'secret123',
        'device_name' => 'Pest',
    ]);

    $login->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.role', 'Intern');

    $token = $login->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'API Intern');
});

it('returns a non-project dashboard for regular and PKL interns', function () {
    MIntern::where('intIntern_ID', 1)->update([
        'txtInternType' => 'regular',
        'dtmEndDate' => now('Asia/Jakarta')->addDays(10),
    ]);
    TrAttendance::create([
        'intUser_ID' => 1,
        'dtmAttendanceDate' => now('Asia/Jakarta')->toDateString(),
        'dtmAttendanceClockIn' => now('Asia/Jakarta')->setTime(8, 0),
        'txtAttendanceStatus' => 'Hadir',
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'api.intern@example.com',
        'password' => 'secret123',
        'device_name' => 'Pest',
    ])->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.intern_type', 'regular')
        ->assertJsonPath('data.attendance_days', 1)
        ->assertJsonPath('data.remaining_days', 10)
        ->assertJsonCount(0, 'data.leaderboard');
});

it('returns a complete today attendance recap for HRD including interns who have not clocked in', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 08:30:00', 'Asia/Jakarta'));

    try {
        $secondUser = MUser::create([
            'intUser_ID' => 2,
            'txtEmail' => 'second.intern@example.com',
            'txtPassword' => Hash::make('secret123'),
            'txtRole' => 'Intern',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        MIntern::create([
            'intIntern_ID' => 2,
            'intUser_ID' => $secondUser->intUser_ID,
            'txtInternNo' => 'INT-002',
            'txtInternName' => 'Belum Hadir',
            'txtInternType' => 'digitalisasi',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        $hrd = MUser::create([
            'intUser_ID' => 3,
            'txtEmail' => 'hrd@example.com',
            'txtPassword' => Hash::make('secret123'),
            'txtRole' => 'HRD',
            'bitActive' => true,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);
        TrAttendance::create([
            'intUser_ID' => 1,
            'dtmAttendanceDate' => '2026-07-16',
            'dtmAttendanceClockIn' => Carbon::parse('2026-07-16 08:00:00', 'Asia/Jakarta'),
            'txtAttendanceStatus' => 'Hadir',
            'txtAttendanceClockInStatus' => 'Tepat Waktu',
            'txtAttendanceAddress' => 'Kantor Dojo',
            'floatAttendanceLatitude' => -6.2,
            'floatAttendanceLongitude' => 106.8,
            'txtInsertedBy' => 'test',
            'dtmInserted' => now(),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $hrd->txtEmail,
            'password' => 'secret123',
            'device_name' => 'Pest',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonCount(2, 'data.today_records')
            ->assertJsonPath('data.today_summary.total', 2)
            ->assertJsonPath('data.today_summary.clocked_in', 1)
            ->assertJsonPath('data.today_summary.completed', 0)
            ->assertJsonPath('data.today_summary.not_checked_in', 1)
            ->assertJsonPath('data.today_records.0.intern.name', 'API Intern')
            ->assertJsonPath('data.today_records.0.location.name', 'Kantor Dojo')
            ->assertJsonPath('data.today_records.1.intern.name', 'Belum Hadir')
            ->assertJsonPath('data.today_records.1.status', 'Belum Clock In')
            ->assertJsonPath('data.today_records.1.clock_in', null);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/attendance?from=2026-07-15&to=2026-07-16')
            ->assertOk()
            ->assertJsonCount(2, 'data.intern_groups')
            ->assertJsonPath('data.intern_groups.0.intern.name', 'API Intern')
            ->assertJsonPath('data.intern_groups.0.summary.present', 1)
            ->assertJsonPath('data.intern_groups.0.summary.absent', 1)
            ->assertJsonCount(2, 'data.intern_groups.0.records')
            ->assertJsonPath('data.intern_groups.0.records.0.date', '2026-07-16')
            ->assertJsonPath('data.intern_groups.1.intern.name', 'Belum Hadir')
            ->assertJsonPath('data.intern_groups.1.summary.absent', 1)
            ->assertJsonPath('data.intern_groups.1.summary.pending', 1);
    } finally {
        Carbon::setTestNow();
    }
});

it('accepts a typed absence submission from the mobile API', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Jakarta'));
    Storage::fake('local');

    try {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'api.intern@example.com',
            'password' => 'secret123',
            'device_name' => 'Pest',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/work-from-home', [
                'type' => 'Sakit',
                'start_date' => '2026-07-17',
                'end_date' => '2026-07-17',
                'reason' => 'Memerlukan istirahat dan melampirkan surat dokter.',
                'attachment' => UploadedFile::fake()->create(
                    'dokumen-pendukung.pdf',
                    128,
                    'application/pdf',
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'Sakit')
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.start_date', '2026-07-17')
            ->assertJsonPath('data.attachment_available', true);

        $this->assertDatabaseHas('trWorkFromHomeRequest', [
            'intIntern_ID' => 1,
            'txtWorkFromHomeRequestType' => 'Sakit',
            'txtWorkFromHomeRequestStatus' => 'Pending',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

it('keeps an intern certificate private until publication and then serves its PDF', function () {
    $evaluation = TrEvaluation::create([
        'intIntern_ID' => 1,
        'dtmEvaluationCompleted' => now()->toDateString(),
        'floatHardSkill' => 90,
        'floatCollaboration' => 86,
        'floatOwnership' => 88,
        'floatSharing' => 84,
        'floatExposureScore' => 87,
        'txtEvaluationStrength' => 'Cepat belajar.',
        'txtEvaluationDevelopment' => 'Perlu lebih percaya diri.',
        'txtEvaluationRecommendation' => 'Direkomendasikan.',
        'bitEvaluationCertificatePublished' => false,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'api.intern@example.com',
        'password' => 'secret123',
        'device_name' => 'Pest',
    ])->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/evaluations')
        ->assertOk()
        ->assertJsonPath('data.0.certificate_published', false)
        ->assertJsonMissingPath('data.0.exposure_score')
        ->assertJsonMissingPath('data.0.strength');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/v1/me/evaluations/'.$evaluation->intEvaluation_ID.'/certificate')
        ->assertNotFound();

    $evaluation->update([
        'bitEvaluationCertificatePublished' => true,
        'dtmEvaluationCertificatePublished' => now(),
        'txtUpdatedBy' => 'test',
        'dtmUpdated' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/evaluations')
        ->assertOk()
        ->assertJsonPath('data.0.certificate_published', true)
        ->assertJsonPath('data.0.exposure_score', 87)
        ->assertJsonPath('data.0.certificate_url', route(
            'api.v1.me.evaluations.certificate',
            $evaluation->intEvaluation_ID,
        ));

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/v1/me/evaluations/'.$evaluation->intEvaluation_ID.'/certificate')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');
});
