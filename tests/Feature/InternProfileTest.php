<?php

use App\Models\MIntern;
use App\Models\MUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = MUser::create([
        'txtEmail' => 'intern.profile@example.test',
        'txtPassword' => bcrypt('password'),
        'txtRole' => 'Intern',
        'bitActive' => true,
    ]);

    $this->intern = MIntern::create([
        'intUser_ID' => $this->user->intUser_ID,
        'txtInternNo' => 'INT-PROFILE',
        'txtInternName' => 'Profile Intern',
        'dtmInserted' => '2026-08-01',
        'dtmEndDate' => '2026-12-31',
        'bitActive' => true,
    ]);

    $this->withSession(['auth_user_id' => $this->user->intUser_ID]);
});

it('shows the saved end date as disabled even when old input contains a different date', function () {
    $response = $this->withSession(['_old_input' => ['dtmEndDate' => '2027-01-31']])
        ->get(route('profile.edit'))
        ->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $input = (new DOMXPath($document))->query('//input[@name="dtmEndDate"]')->item(0);

    expect($input)->not->toBeNull()
        ->and($input->hasAttribute('disabled'))->toBeTrue()
        ->and($input->getAttribute('value'))->toBe('2026-12-31');
});

it('updates editable profile data while preserving the end date', function (array $extraInput, ?string $savedEndDate) {
    $this->intern->update(['dtmEndDate' => $savedEndDate]);

    $this->put(route('profile.update'), [
        'txtInternNo' => 'INT-PROFILE',
        'txtInternName' => 'Updated Profile Intern',
        'txtEmail' => 'updated.intern@example.test',
        ...$extraInput,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.show'));

    expect($this->intern->fresh()->dtmEndDate?->toDateString())->toBe($savedEndDate)
        ->and($this->intern->fresh()->bitActive)->toBeTrue()
        ->and($this->intern->fresh()->txtInternName)->toBe('Updated Profile Intern')
        ->and($this->user->fresh()->txtEmail)->toBe('updated.intern@example.test');
})->with([
    'disabled field omitted' => [[], '2026-12-31'],
    'attempt to change date' => [['dtmEndDate' => '2027-01-31'], '2026-12-31'],
    'attempt to clear date' => [['dtmEndDate' => ''], '2026-12-31'],
    'attempt to set an unset date' => [['dtmEndDate' => '2027-01-31'], null],
]);

it('shows the saved status as disabled regardless of old input', function (bool $savedStatus) {
    $this->intern->update(['bitActive' => $savedStatus]);

    $response = $this->withSession(['_old_input' => ['bitActive' => (int) ! $savedStatus]])
        ->get(route('profile.edit'))
        ->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $select = $xpath->query('//select[@name="bitActive"]')->item(0);
    $selectedOption = $xpath->query('//select[@name="bitActive"]/option[@selected]')->item(0);

    expect($select)->not->toBeNull()
        ->and($select->hasAttribute('disabled'))->toBeTrue()
        ->and($selectedOption->getAttribute('value'))->toBe((string) (int) $savedStatus);
})->with([true, false]);

it('preserves the saved status when a profile request attempts to change it', function (bool $savedStatus) {
    $this->intern->update(['bitActive' => $savedStatus]);

    $this->put(route('profile.update'), [
        'txtInternNo' => 'INT-PROFILE',
        'txtInternName' => 'Updated Profile Intern',
        'txtEmail' => $this->user->txtEmail,
        'bitActive' => (int) ! $savedStatus,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.show'));

    expect($this->intern->fresh()->bitActive)->toBe($savedStatus)
        ->and($this->intern->fresh()->txtInternName)->toBe('Updated Profile Intern');
})->with([true, false]);
