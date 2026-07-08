<?php

use App\Models\MAttendanceSetting;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
