<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiToken;
use App\Models\MAdminProfile;
use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = MUser::where('txtEmail', $validated['email'])->where('bitActive', true)->first();

        if (! $user || ! Hash::check($validated['password'], (string) $user->txtPassword)) {
            throw ValidationException::withMessages(['email' => ['Email atau password tidak sesuai.']]);
        }

        $user->load(['intern', 'mentor', 'adminProfile']);
        [$plainToken, $token] = $this->issueToken($user, $validated['device_name'] ?? 'mobile');

        return $this->success([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toISOString(),
            'user' => $this->person($user),
        ], 'Login berhasil.');
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in([
                RoleAccess::ROLE_INTERN,
                RoleAccess::ROLE_MENTOR,
                RoleAccess::ROLE_HEADMASTER,
                RoleAccess::ROLE_HRD,
            ])],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($validated): MUser {
            $now = now();
            $user = MUser::create([
                'txtEmail' => $validated['email'],
                'txtPassword' => Hash::make($validated['password']),
                'txtRole' => $validated['role'],
                'bitActive' => true,
                'txtInsertedBy' => 'api-register',
                'dtmInserted' => $now,
            ]);

            if ($validated['role'] === RoleAccess::ROLE_INTERN) {
                MIntern::create([
                    'intUser_ID' => $user->intUser_ID,
                    'txtInternNo' => 'INT-'.str_pad((string) $user->intUser_ID, 3, '0', STR_PAD_LEFT),
                    'txtInternName' => $validated['name'],
                    'txtInternGender' => $validated['gender'] ?? null,
                    'txtInternType' => RoleAccess::INTERN_DIGITALISASI,
                    'floatInternSalary' => 0,
                    'bitActive' => true,
                    'txtInsertedBy' => 'api-register',
                    'dtmInserted' => $now,
                ]);
            } elseif ($validated['role'] === RoleAccess::ROLE_MENTOR) {
                MMentor::create([
                    'intUser_ID' => $user->intUser_ID,
                    'txtMentorName' => $validated['name'],
                    'txtMentorGender' => $validated['gender'] ?? null,
                    'txtRole' => RoleAccess::ROLE_MENTOR,
                    'bitActive' => true,
                    'txtInsertedBy' => 'api-register',
                    'dtmInserted' => $now,
                ]);
            } else {
                MAdminProfile::create([
                    'intUser_ID' => $user->intUser_ID,
                    'txtAdminProfileName' => $validated['name'],
                    'txtAdminProfileGender' => $validated['gender'] ?? null,
                    'txtAdminProfileDepartment' => $validated['role'] === RoleAccess::ROLE_HRD ? 'Human Resources' : 'Internship Program',
                    'txtAdminProfilePosition' => $validated['role'],
                    'bitActive' => true,
                    'txtInsertedBy' => 'api-register',
                    'dtmInserted' => $now,
                ]);
            }

            return $user;
        });

        $user->load(['intern', 'mentor', 'adminProfile']);
        [$plainToken, $token] = $this->issueToken($user, $validated['device_name'] ?? 'mobile');

        return $this->success([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toISOString(),
            'user' => $this->person($user),
        ], 'Registrasi berhasil.', [], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->person($this->user($request)));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('kmi_api_token')?->update(['revoked_at' => now()]);

        return $this->success(null, 'Token berhasil dicabut.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        ApiToken::where('intUser_ID', $this->user($request)->intUser_ID)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->success(null, 'Semua token berhasil dicabut.');
    }

    /** @return array{0: string, 1: ApiToken} */
    private function issueToken(MUser $user, string $deviceName): array
    {
        $plainToken = bin2hex(random_bytes(40));
        $ttlDays = max(1, (int) config('services.api_token_ttl_days', env('API_TOKEN_TTL_DAYS', 30)));
        $token = ApiToken::create([
            'intUser_ID' => $user->intUser_ID,
            'name' => $deviceName,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays($ttlDays),
        ]);

        return [$plainToken, $token];
    }
}
