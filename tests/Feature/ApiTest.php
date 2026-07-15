<?php

use App\Models\MIntern;
use App\Models\MUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
