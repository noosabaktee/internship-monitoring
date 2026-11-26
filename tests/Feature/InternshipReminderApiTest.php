<?php

use App\Models\MIntern;
use App\Models\MUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-26 15:00:00', 'Asia/Jakarta'));

    $this->createReminderIntern = function (array $attributes = [], array $userAttributes = []): MIntern {
        $number = MUser::count() + 1;
        $user = MUser::create(array_merge([
            'txtEmail' => "reminder.intern.{$number}@example.com",
            'txtPassword' => Hash::make('secret123'),
            'txtRole' => 'Intern',
            'bitActive' => true,
        ], $userAttributes));

        return MIntern::create(array_merge([
            'intUser_ID' => $user->intUser_ID,
            'txtInternNo' => "INT-{$number}",
            'txtInternName' => "Reminder Intern {$number}",
            'txtUniversity' => 'Universitas Contoh',
            'txtDept' => 'IT',
            'txtInternType' => 'digitalisasi',
            'dtmEndDate' => '2026-12-10',
            'bitActive' => true,
        ], $attributes));
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns JSON when opened in a browser without login or a bearer token', function () {
    ($this->createReminderIntern)(['txtInternPicName' => 'Admin PIC']);

    $this->withHeader('Accept', 'text/html')->get('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.user', 'Admin PIC')
        ->assertJsonMissingPath('data.0.pic_name')
        ->assertJsonCount(1, 'data');
});

it('keeps the other intern endpoints protected', function () {
    $this->getJson('/api/v1/interns')->assertUnauthorized();
    $this->getJson('/api/v1/interns/1')->assertUnauthorized();
});

it('returns all active intern types within the inclusive calendar-month window sorted by end date', function () {
    $last = ($this->createReminderIntern)(['dtmEndDate' => '2026-12-26 23:59:59', 'txtInternType' => 'pkl']);
    $middle = ($this->createReminderIntern)(['dtmEndDate' => '2026-12-10', 'txtInternType' => 'regular']);
    $first = ($this->createReminderIntern)(['dtmEndDate' => '2026-11-26 01:00:00']);
    ($this->createReminderIntern)(['dtmEndDate' => '2026-11-25 23:59:59']);
    ($this->createReminderIntern)(['dtmEndDate' => '2026-12-27']);
    ($this->createReminderIntern)(['dtmEndDate' => null]);
    ($this->createReminderIntern)(['bitActive' => false]);
    ($this->createReminderIntern)([], ['bitActive' => false]);
    ($this->createReminderIntern)([], ['txtRole' => 'Mentor']);

    $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $first->intIntern_ID)
        ->assertJsonPath('data.0.user', null)
        ->assertJsonMissingPath('data.0.pic_name')
        ->assertJsonPath('data.0.remaining_days', 0)
        ->assertJsonPath('data.1.id', $middle->intIntern_ID)
        ->assertJsonPath('data.1.type', 'regular')
        ->assertJsonPath('data.1.remaining_days', 14)
        ->assertJsonPath('data.1.email', $middle->user->txtEmail)
        ->assertJsonPath('data.1.university', 'Universitas Contoh')
        ->assertJsonPath('data.1.department', 'IT')
        ->assertJsonPath('data.2.id', $last->intIntern_ID)
        ->assertJsonPath('data.2.type', 'pkl')
        ->assertJsonPath('data.2.remaining_days', 30)
        ->assertJsonPath('meta.from_date', '2026-11-26')
        ->assertJsonPath('meta.to_date', '2026-12-26')
        ->assertJsonPath('meta.timezone', 'Asia/Jakarta')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonMissingPath('data.0.salary')
        ->assertJsonMissingPath('data.0.txtPassword');
});

it('uses the latest extension even when extensions are unsorted or the original end date is missing', function () {
    $extended = ($this->createReminderIntern)([
        'dtmEndDate' => '2026-11-01',
        'txtInternExtendEndDates' => ['2026-12-20', null, '2026-12-05'],
    ]);
    $extensionOnly = ($this->createReminderIntern)([
        'dtmEndDate' => null,
        'txtInternExtendEndDates' => ['2026-12-02'],
    ]);
    $originalLater = ($this->createReminderIntern)([
        'dtmEndDate' => '2026-12-15',
        'txtInternExtendEndDates' => ['2026-11-25'],
    ]);
    ($this->createReminderIntern)([
        'dtmEndDate' => '2026-12-01',
        'txtInternExtendEndDates' => ['2027-01-01', '2026-12-10'],
    ]);

    $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $extensionOnly->intIntern_ID)
        ->assertJsonPath('data.0.end_date', null)
        ->assertJsonPath('data.0.effective_end_date', '2026-12-02')
        ->assertJsonPath('data.1.id', $originalLater->intIntern_ID)
        ->assertJsonPath('data.1.effective_end_date', '2026-12-15')
        ->assertJsonPath('data.2.id', $extended->intIntern_ID)
        ->assertJsonPath('data.2.end_date', '2026-11-01')
        ->assertJsonPath('data.2.effective_end_date', '2026-12-20')
        ->assertJsonPath('data.2.remaining_days', 24);
});

it('clamps the next calendar month to its last day', function () {
    Carbon::setTestNow(Carbon::parse('2027-01-31 08:00:00', 'Asia/Jakarta'));
    ($this->createReminderIntern)(['dtmEndDate' => '2027-02-28']);
    ($this->createReminderIntern)(['dtmEndDate' => '2027-03-01']);

    $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.remaining_days', 28)
        ->assertJsonPath('meta.from_date', '2027-01-31')
        ->assertJsonPath('meta.to_date', '2027-02-28');
});

it('uses the Jakarta date when the current UTC date is still yesterday', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-25 18:00:00', 'UTC'));
    ($this->createReminderIntern)(['dtmEndDate' => '2026-11-25']);
    ($this->createReminderIntern)(['dtmEndDate' => '2026-11-26']);

    $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.remaining_days', 0)
        ->assertJsonPath('meta.from_date', '2026-11-26');
});

it('returns an empty array when no interns match', function () {
    $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('returns every match without pagination and keeps repeated reads consistent', function () {
    for ($i = 0; $i < 25; $i++) {
        ($this->createReminderIntern)();
    }

    $response = $this->getJson('/api/v1/interns/ending-soon')
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 25);

    expect(array_column($response->json('data'), 'id'))->toBe(range(1, 25));

    $this->getJson('/api/v1/interns/ending-soon')
        ->assertExactJson($response->json());
});
