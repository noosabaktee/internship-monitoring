<?php

namespace App\Http\Controllers;

use App\Models\MMentor;
use App\Models\MUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MentorController extends Controller
{
    public function index(): View
    {
        $mentors = MMentor::with('user')->orderBy('intMentor_ID')->get();

        return view('dashboard.mentors', compact('mentors'));
    }

    public function create(): View
    {
        $mentors = MMentor::with('user')->orderBy('intMentor_ID')->get();

        return view('dashboard.mentors', [
            'mentors' => $mentors,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtMentorName' => ['required', 'string', 'max:255'],
            'txtMentorGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtDepartment' => ['nullable', 'string', 'max:255'],
            'txtRole' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $now = now();
            $user = MUser::create([
                'txtEmail' => $validated['txtEmail'],
                'txtPassword' => Hash::make($validated['txtPassword'] ?: 'password'),
                'txtRole' => 'Mentor',
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            MMentor::create([
                'intUser_ID' => $user->intUser_ID,
                'txtMentorName' => $validated['txtMentorName'],
                'txtMentorGender' => $validated['txtMentorGender'] ?? null,
                'txtDepartment' => $validated['txtDepartment'] ?? null,
                'txtRole' => $validated['txtRole'] ?? 'Mentor',
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        });

        return redirect()->route('mentors.index')->with('success', 'Mentor data has been added.');
    }

    public function show(string $mentor): View
    {
        $mentor = MMentor::with([
            'user',
            'internProjects.intern',
            'internProjects.project',
            'projectMentors.project.assignments.intern',
        ])->findOrFail($mentor);

        return view('dashboard.mentors', compact('mentor'));
    }

    public function edit(string $mentor): View
    {
        $mentors = MMentor::with('user')->orderBy('intMentor_ID')->get();
        $editingMentor = MMentor::with('user')->findOrFail($mentor);

        return view('dashboard.mentors', [
            'mentors' => $mentors,
            'editingMentor' => $editingMentor,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $mentor): RedirectResponse
    {
        $mentorModel = MMentor::with('user')->findOrFail($mentor);
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')->ignore($mentorModel->intUser_ID, 'intUser_ID')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtMentorName' => ['required', 'string', 'max:255'],
            'txtMentorGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtDepartment' => ['nullable', 'string', 'max:255'],
            'txtRole' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($mentorModel, $validated) {
            $now = now();
            $userData = [
                'txtEmail' => $validated['txtEmail'],
                'txtRole' => 'Mentor',
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ];

            if (! empty($validated['txtPassword'])) {
                $userData['txtPassword'] = Hash::make($validated['txtPassword']);
            }

            $mentorModel->user->update($userData);
            $mentorModel->update([
                'txtMentorName' => $validated['txtMentorName'],
                'txtMentorGender' => $validated['txtMentorGender'] ?? null,
                'txtDepartment' => $validated['txtDepartment'] ?? null,
                'txtRole' => $validated['txtRole'] ?? 'Mentor',
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);
        });

        return redirect()->route('mentors.index')->with('success', 'Mentor data has been updated.');
    }

    public function destroy(string $mentor): RedirectResponse
    {
        $mentorModel = MMentor::with('user')->findOrFail($mentor);
        $now = now();

        $mentorModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        $mentorModel->user?->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('mentors.index')->with('success', 'Mentor data has been deactivated.');
    }
}
