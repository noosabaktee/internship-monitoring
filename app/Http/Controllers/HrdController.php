<?php

namespace App\Http\Controllers;

use App\Models\MAdminProfile;
use App\Models\MMentor;
use App\Models\MUser;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class HrdController extends Controller
{
    public function index(): View
    {
        return view('dashboard.hrds', [
            'hrds' => $this->hrdProfiles(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.hrds', [
            'hrds' => $this->hrdProfiles(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedHrd($request);

        DB::transaction(function () use ($validated) {
            $now = now();
            $user = MUser::create([
                'txtEmail' => $validated['txtEmail'],
                'txtPassword' => Hash::make($validated['txtPassword'] ?: 'password'),
                'txtRole' => $validated['txtRole'],
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            MAdminProfile::create([
                'intUser_ID' => $user->intUser_ID,
                'txtAdminProfileName' => $validated['txtAdminProfileName'],
                'txtAdminProfileGender' => $validated['txtAdminProfileGender'] ?? null,
                'txtAdminProfileDepartment' => $validated['txtAdminProfileDepartment'] ?? null,
                'txtAdminProfilePosition' => $validated['txtRole'],
                'txtAdminProfilePhone' => $validated['txtAdminProfilePhone'] ?? null,
                'txtAdminProfileBio' => $validated['txtAdminProfileBio'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            if ($validated['txtRole'] === RoleAccess::ROLE_MENTOR) {
                $this->ensureMentorProfileFromAdmin($user, $validated, $now);
            }
        });

        return redirect()->route('hrds.index')->with('success', 'HRD data has been added.');
    }

    public function show(string $hrd): View
    {
        $hrdProfile = $this->hrdProfile($hrd);

        return view('dashboard.hrds', [
            'hrds' => $this->hrdProfiles(),
            'hrdProfile' => $hrdProfile,
        ]);
    }

    public function edit(string $hrd): View
    {
        return view('dashboard.hrds', [
            'hrds' => $this->hrdProfiles(),
            'editingHrd' => $this->hrdProfile($hrd),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $hrd): RedirectResponse
    {
        $hrdProfile = $this->hrdProfile($hrd);
        $validated = $this->validatedHrd($request, $hrdProfile);
        $isEditingSelf = (int) $hrdProfile->intUser_ID === (int) $request->session()->get('auth_user_id');

        DB::transaction(function () use ($hrdProfile, $validated) {
            $now = now();
            $userData = [
                'txtEmail' => $validated['txtEmail'],
                'txtRole' => $validated['txtRole'],
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ];

            if (! empty($validated['txtPassword'])) {
                $userData['txtPassword'] = Hash::make($validated['txtPassword']);
            }

            $hrdProfile->user?->update($userData);
            $hrdProfile->update([
                'txtAdminProfileName' => $validated['txtAdminProfileName'],
                'txtAdminProfileGender' => $validated['txtAdminProfileGender'] ?? null,
                'txtAdminProfileDepartment' => $validated['txtAdminProfileDepartment'] ?? null,
                'txtAdminProfilePosition' => $validated['txtRole'],
                'txtAdminProfilePhone' => $validated['txtAdminProfilePhone'] ?? null,
                'txtAdminProfileBio' => $validated['txtAdminProfileBio'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);

            if ($validated['txtRole'] === RoleAccess::ROLE_MENTOR) {
                $this->ensureMentorProfileFromAdmin($hrdProfile->user, $validated, $now);
            }
        });

        $redirectRoute = $isEditingSelf && $validated['txtRole'] === RoleAccess::ROLE_MENTOR
            ? 'dashboard.index'
            : 'hrds.index';

        return redirect()->route($redirectRoute)->with('success', 'HRD data has been updated.');
    }

    public function destroy(string $hrd): RedirectResponse
    {
        $hrdProfile = $this->hrdProfile($hrd);
        $now = now();

        $hrdProfile->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        $hrdProfile->user?->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('hrds.index')->with('success', 'HRD data has been deactivated.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MAdminProfile>
     */
    private function hrdProfiles()
    {
        return MAdminProfile::with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('txtRole', [RoleAccess::ROLE_HRD, RoleAccess::ROLE_HEADMASTER]))
            ->orderBy('intAdminProfile_ID')
            ->get();
    }

    private function hrdProfile(string $hrd): MAdminProfile
    {
        return MAdminProfile::with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('txtRole', [RoleAccess::ROLE_HRD, RoleAccess::ROLE_HEADMASTER]))
            ->findOrFail($hrd);
    }

    private function validatedHrd(Request $request, ?MAdminProfile $hrdProfile = null): array
    {
        return $request->validate([
            'txtEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('mUser', 'txtEmail')->ignore($hrdProfile?->intUser_ID, 'intUser_ID'),
            ],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtAdminProfileName' => ['required', 'string', 'max:255'],
            'txtRole' => ['required', Rule::in($this->managedRoles())],
            'txtAdminProfileGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtAdminProfileDepartment' => ['nullable', 'string', 'max:255'],
            'txtAdminProfilePhone' => ['nullable', 'string', 'max:50'],
            'txtAdminProfileBio' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function managedRoles(): array
    {
        return [
            RoleAccess::ROLE_MENTOR,
            RoleAccess::ROLE_HRD,
            RoleAccess::ROLE_HEADMASTER,
        ];
    }

    private function ensureMentorProfileFromAdmin(MUser $user, array $validated, mixed $now): void
    {
        $mentor = MMentor::firstOrNew(['intUser_ID' => $user->intUser_ID]);

        if (! $mentor->exists) {
            $mentor->txtInsertedBy = 'system';
            $mentor->dtmInserted = $now;
        }

        $mentor->fill([
            'txtMentorName' => $validated['txtAdminProfileName'],
            'txtMentorGender' => $validated['txtAdminProfileGender'] ?? null,
            'txtDepartment' => $validated['txtAdminProfileDepartment'] ?? null,
            'txtRole' => RoleAccess::ROLE_MENTOR,
            'bitActive' => (bool) ($validated['bitActive'] ?? true),
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        $mentor->save();
    }
}
