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
            'txtInternGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtDept' => ['nullable', 'string', 'max:255'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'txtInternExtendEndDates' => ['nullable', 'array'],
            'txtInternExtendEndDates.*' => ['nullable', 'date'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $now = now();
            $joinDate = $validated['dtmInserted'] ?? $now;
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
                'txtInternGender' => $validated['txtInternGender'] ?? null,
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtDept' => $validated['txtDept'] ?? null,
                'dtmEndDate' => $validated['dtmEndDate'] ?? null,
                'txtInternExtendEndDates' => $this->extensionDates($validated['txtInternExtendEndDates'] ?? []),
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $joinDate,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Intern data has been added.');
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
            'txtInternGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtDept' => ['nullable', 'string', 'max:255'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'txtInternExtendEndDates' => ['nullable', 'array'],
            'txtInternExtendEndDates.*' => ['nullable', 'date'],
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
                'txtInternGender' => $validated['txtInternGender'] ?? null,
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtDept' => $validated['txtDept'] ?? null,
                'dtmEndDate' => $validated['dtmEndDate'] ?? null,
                'txtInternExtendEndDates' => $this->extensionDates($validated['txtInternExtendEndDates'] ?? []),
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'dtmInserted' => $validated['dtmInserted'] ?? $internModel->dtmInserted,
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Intern data has been updated.');
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

        return redirect()->route('interns.index')->with('success', 'Intern data has been deactivated.');
    }

    /**
     * @param array<int, string|null> $dates
     * @return array<int, string>
     */
    private function extensionDates(array $dates): array
    {
        return collect($dates)
            ->filter()
            ->values()
            ->all();
    }
}
