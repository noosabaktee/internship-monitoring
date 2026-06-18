<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index(): View
    {
        return view('profile.show');
    }

    public function create(): View
    {
        return view('profile.edit');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    public function show(): View
    {
        $user = $this->currentUser();

        if ($user?->txtRole === 'Mentor' && $user->mentor) {
            $mentor = $user->mentor->load(['user', 'internProjects.intern', 'internProjects.project']);

            return view('profile.mentor', compact('mentor'));
        }

        $intern = $user?->intern ?? $this->currentIntern();

        if ($intern) {
            $intern->load(['user', 'projects.project', 'projects.mentor', 'achievements', 'evaluations']);
        }

        return view('profile.show', compact('intern'));
    }

    public function edit(?string $profile = null): View
    {
        $user = $this->currentUser();
        $intern = $user?->intern;
        $mentor = $user?->mentor;

        return view('profile.edit', compact('intern', 'mentor', 'profile'));
    }

    public function update(Request $request, ?string $profile = null): RedirectResponse
    {
        $user = $this->currentUser();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->txtRole === 'Mentor' && $user->mentor) {
            return $this->updateMentorProfile($request, $user->mentor);
        }

        $intern = $user->intern;

        if (! $intern) {
            return redirect()->route('profile.show')->withErrors(['profile' => 'No profile is available to edit.']);
        }

        return $this->updateInternProfile($request, $intern);
    }

    public function showIntern(MIntern $intern): View
    {
        $intern->load(['user', 'projects.project', 'projects.mentor', 'achievements', 'evaluations']);

        return view('profile.show', compact('intern'));
    }

    public function showMentor(MMentor $mentor): View
    {
        $mentor->load(['user', 'internProjects.intern', 'internProjects.project']);

        return view('profile.mentor', compact('mentor'));
    }

    public function destroy(?string $profile = null): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    private function updateInternProfile(Request $request, MIntern $intern): RedirectResponse
    {
        $validated = $request->validate([
            'txtInternNo' => ['nullable', 'string', 'max:255'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtEmail' => ['required', 'email', 'max:255'],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternGender' => ['nullable', 'in:Male,Female,Laki-laki,Perempuan'],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtMajor' => ['nullable', 'string', 'max:255'],
            'txtBio' => ['nullable', 'string', 'max:255'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $userData = [
            'txtEmail' => $validated['txtEmail'],
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ];

        if (! empty($validated['txtPassword'])) {
            $userData['txtPassword'] = Hash::make($validated['txtPassword']);
        }

        $intern->user?->update($userData);
        $intern->update([
            'txtInternNo' => $validated['txtInternNo'] ?: $intern->txtInternNo,
            'txtInternName' => $validated['txtInternName'],
            'txtInternGender' => $validated['txtInternGender'] ?? null,
            'txtUniversity' => $validated['txtUniversity'] ?? null,
            'txtMajor' => $validated['txtMajor'] ?? null,
            'txtBio' => $validated['txtBio'] ?? null,
            'dtmInserted' => $validated['dtmInserted'] ?? $intern->dtmInserted,
            'dtmEndDate' => $validated['dtmEndDate'] ?? null,
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile has been updated.');
    }

    private function updateMentorProfile(Request $request, MMentor $mentor): RedirectResponse
    {
        $validated = $request->validate([
            'txtMentorName' => ['required', 'string', 'max:255'],
            'txtEmail' => ['required', 'email', 'max:255'],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtMentorGender' => ['nullable', 'in:Male,Female,Laki-laki,Perempuan'],
            'txtDepartment' => ['nullable', 'string', 'max:255'],
            'txtRole' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $userData = [
            'txtEmail' => $validated['txtEmail'],
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ];

        if (! empty($validated['txtPassword'])) {
            $userData['txtPassword'] = Hash::make($validated['txtPassword']);
        }

        $mentor->user?->update($userData);
        $mentor->update([
            'txtMentorName' => $validated['txtMentorName'],
            'txtMentorGender' => $validated['txtMentorGender'] ?? null,
            'txtDepartment' => $validated['txtDepartment'] ?? null,
            'txtRole' => $validated['txtRole'] ?? 'Mentor',
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile has been updated.');
    }

    private function currentIntern(): ?MIntern
    {
        $userId = session('auth_user_id');

        if ($userId) {
            $user = MUser::with('intern')->find($userId);

            if ($user?->intern) {
                return $user->intern;
            }
        }

        return MIntern::with('user')->orderBy('intIntern_ID')->first();
    }

    private function currentUser(): ?MUser
    {
        $userId = session('auth_user_id');

        if (! $userId) {
            return null;
        }

        return MUser::with(['intern', 'mentor'])->find($userId);
    }
}
