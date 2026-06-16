<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InternController extends Controller
{
    public function index(): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();

        return view('dashboard.interns', compact('interns'));
    }

    public function create(): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();

        return view('dashboard.interns', [
            'interns' => $interns,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtMajor' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $now = now();
            $user = MUser::create([
                'txtEmail' => $validated['txtEmail'],
                'txtPassword' => Hash::make($validated['txtPassword'] ?: 'password'),
                'txtRole' => 'Intern',
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            MIntern::create([
                'intUser_ID' => $user->intUser_ID,
                'txtInternNo' => 'INT-' . str_pad((string) $user->intUser_ID, 3, '0', STR_PAD_LEFT),
                'txtInternName' => $validated['txtInternName'],
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtMajor' => $validated['txtMajor'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Data intern berhasil ditambahkan.');
    }

    public function show(string $intern): View
    {
        $intern = MIntern::with(['user', 'projects.project', 'projects.mentor', 'achievements', 'evaluations'])
            ->findOrFail($intern);

        return view('profile.show', compact('intern'));
    }

    public function edit(string $intern): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();
        $editingIntern = MIntern::with('user')->findOrFail($intern);

        return view('dashboard.interns', [
            'interns' => $interns,
            'editingIntern' => $editingIntern,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $intern): RedirectResponse
    {
        $internModel = MIntern::with('user')->findOrFail($intern);
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')->ignore($internModel->intUser_ID, 'intUser_ID')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtMajor' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($internModel, $validated) {
            $now = now();
            $userData = [
                'txtEmail' => $validated['txtEmail'],
                'txtRole' => 'Intern',
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ];

            if (! empty($validated['txtPassword'])) {
                $userData['txtPassword'] = Hash::make($validated['txtPassword']);
            }

            $internModel->user->update($userData);
            $internModel->update([
                'txtInternName' => $validated['txtInternName'],
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtMajor' => $validated['txtMajor'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Data intern berhasil diperbarui.');
    }

    public function destroy(string $intern): RedirectResponse
    {
        $internModel = MIntern::with('user')->findOrFail($intern);
        $now = now();

        $internModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        $internModel->user?->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('interns.index')->with('success', 'Data intern berhasil dinonaktifkan.');
    }
}
