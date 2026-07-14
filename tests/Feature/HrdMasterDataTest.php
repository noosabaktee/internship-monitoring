<?php

use App\Models\MAdminProfile;
use App\Models\MMentor;
use App\Models\MUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows headmaster to create HRD data from master data', function () {
    $headmaster = createHrdMasterUser('Headmaster', 'Headmaster User');

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->get(route('hrds.index'))
        ->assertOk()
        ->assertSeeText('HRD & Headmaster Data')
        ->assertSee('Add HRD');

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->post(route('hrds.store'), [
            'txtAdminProfileName' => 'HRD Master',
            'txtRole' => 'HRD',
            'txtAdminProfileGender' => 'Perempuan',
            'txtEmail' => 'hrd.master@example.test',
            'txtPassword' => 'password',
            'txtAdminProfileDepartment' => 'Human Resources',
            'txtAdminProfilePhone' => '081234567890',
            'txtAdminProfileBio' => 'Handles internship administration.',
            'bitActive' => '1',
        ])
        ->assertRedirect(route('hrds.index'));

    $user = MUser::where('txtEmail', 'hrd.master@example.test')->first();
    $profile = MAdminProfile::where('intUser_ID', $user->intUser_ID)->first();

    expect($user->txtRole)->toBe('HRD')
        ->and($user->bitActive)->toBeTrue()
        ->and($profile->txtAdminProfileName)->toBe('HRD Master')
        ->and($profile->txtAdminProfilePosition)->toBe('HRD')
        ->and($profile->txtAdminProfilePhone)->toBe('081234567890');
});

it('allows HRD to update HRD data', function () {
    $hrd = createHrdMasterUser('HRD', 'Original HRD');

    $this->withSession(['auth_user_id' => $hrd->intUser_ID])
        ->put(route('hrds.update', $hrd->adminProfile->intAdminProfile_ID), [
            'txtAdminProfileName' => 'Updated HRD',
            'txtRole' => 'HRD',
            'txtAdminProfileGender' => 'Laki-laki',
            'txtEmail' => 'updated.hrd@example.test',
            'txtAdminProfileDepartment' => 'People Operations',
            'txtAdminProfilePhone' => '089999999999',
            'txtAdminProfileBio' => 'Updated profile.',
            'bitActive' => '1',
        ])
        ->assertRedirect(route('hrds.index'));

    $hrd->refresh();
    $hrd->adminProfile->refresh();

    expect($hrd->txtEmail)->toBe('updated.hrd@example.test')
        ->and($hrd->txtRole)->toBe('HRD')
        ->and($hrd->adminProfile->txtAdminProfileName)->toBe('Updated HRD')
        ->and($hrd->adminProfile->txtAdminProfilePosition)->toBe('HRD');
});

it('allows headmaster to promote mentor to headmaster', function () {
    $headmaster = createHrdMasterUser('Headmaster', 'Main Headmaster');
    $mentorUser = MUser::create([
        'txtEmail' => 'mentor.promote@example.test',
        'txtPassword' => Hash::make('password'),
        'txtRole' => 'Mentor',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);
    $mentor = MMentor::create([
        'intUser_ID' => $mentorUser->intUser_ID,
        'txtMentorName' => 'Promotion Mentor',
        'txtMentorGender' => 'Perempuan',
        'txtDepartment' => 'Engineering',
        'txtRole' => 'Mentor',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $this->withSession(['auth_user_id' => $headmaster->intUser_ID])
        ->put(route('mentors.update', $mentor->intMentor_ID), [
            'txtMentorName' => 'Promotion Mentor',
            'txtRole' => 'Headmaster',
            'txtMentorGender' => 'Perempuan',
            'txtEmail' => 'mentor.promote@example.test',
            'txtDepartment' => 'Engineering',
            'bitActive' => '1',
        ])
        ->assertRedirect(route('mentors.index'));

    $mentorUser->refresh();
    $adminProfile = MAdminProfile::where('intUser_ID', $mentorUser->intUser_ID)->first();

    expect($mentorUser->txtRole)->toBe('Headmaster')
        ->and($adminProfile)->not->toBeNull()
        ->and($adminProfile->txtAdminProfileName)->toBe('Promotion Mentor')
        ->and($adminProfile->txtAdminProfilePosition)->toBe('Headmaster');
});

it('allows HRD to change headmaster to mentor', function () {
    $hrd = createHrdMasterUser('HRD', 'Role Manager HRD');
    $headmaster = createHrdMasterUser('Headmaster', 'Demoted Headmaster');

    $this->withSession(['auth_user_id' => $hrd->intUser_ID])
        ->put(route('hrds.update', $headmaster->adminProfile->intAdminProfile_ID), [
            'txtAdminProfileName' => 'Demoted Headmaster',
            'txtRole' => 'Mentor',
            'txtAdminProfileGender' => 'Laki-laki',
            'txtEmail' => 'demoted.headmaster@example.test',
            'txtAdminProfileDepartment' => 'Engineering',
            'txtAdminProfilePhone' => '081111111111',
            'txtAdminProfileBio' => 'Now mentoring interns.',
            'bitActive' => '1',
        ])
        ->assertRedirect(route('hrds.index'));

    $headmaster->refresh();
    $mentor = MMentor::where('intUser_ID', $headmaster->intUser_ID)->first();

    expect($headmaster->txtRole)->toBe('Mentor')
        ->and($headmaster->txtEmail)->toBe('demoted.headmaster@example.test')
        ->and($mentor)->not->toBeNull()
        ->and($mentor->txtMentorName)->toBe('Demoted Headmaster')
        ->and($mentor->txtRole)->toBe('Mentor');
});

it('blocks intern from HRD master data', function () {
    $intern = MUser::create([
        'txtEmail' => 'intern.hrd-data@example.test',
        'txtPassword' => Hash::make('password'),
        'txtRole' => 'Intern',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    $this->withSession(['auth_user_id' => $intern->intUser_ID])
        ->get(route('hrds.index'))
        ->assertForbidden();
});

function createHrdMasterUser(string $role, string $name): MUser
{
    $user = MUser::create([
        'txtEmail' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
        'txtPassword' => Hash::make('password'),
        'txtRole' => $role,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    MAdminProfile::create([
        'intUser_ID' => $user->intUser_ID,
        'txtAdminProfileName' => $name,
        'txtAdminProfileGender' => 'Laki-laki',
        'txtAdminProfileDepartment' => $role === 'HRD' ? 'Human Resources' : 'Internship Program',
        'txtAdminProfilePosition' => $role,
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => now(),
    ]);

    return $user->load('adminProfile');
}
