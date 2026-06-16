<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\TrAchievement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AchievementController extends Controller
{
    public function index(): View
    {
        $achievements = TrAchievement::with('intern')->orderByDesc('dtmAwarded')->orderBy('intAchievement_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();

        return view('dashboard.achievements', compact('achievements', 'interns'));
    }

    public function create(): View
    {
        $achievements = TrAchievement::with('intern')->orderByDesc('dtmAwarded')->orderBy('intAchievement_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();

        return view('dashboard.achievements', [
            'achievements' => $achievements,
            'interns' => $interns,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intIntern_ID' => ['required', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'txtAchievementTitle' => ['required', 'string', 'max:255'],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'txtIcon' => ['nullable', 'string', 'max:255'],
            'dtmAwarded' => ['nullable', 'date'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        TrAchievement::create([
            ...$validated,
            'txtIcon' => $validated['txtIcon'] ?: 'fa-solid fa-award',
            'dtmAwarded' => $validated['dtmAwarded'] ?? now(),
            'bitActive' => (bool) ($validated['bitActive'] ?? true),
            'txtInsertedBy' => 'system',
            'dtmInserted' => now(),
        ]);

        return redirect()->route('achievements.index')->with('success', 'Achievement berhasil ditambahkan.');
    }

    public function show(string $achievement): View
    {
        $achievement = TrAchievement::with('intern')->findOrFail($achievement);

        return view('dashboard.achievements', compact('achievement'));
    }

    public function edit(string $achievement): View
    {
        $achievements = TrAchievement::with('intern')->orderByDesc('dtmAwarded')->orderBy('intAchievement_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $editingAchievement = TrAchievement::findOrFail($achievement);

        return view('dashboard.achievements', [
            'achievements' => $achievements,
            'interns' => $interns,
            'editingAchievement' => $editingAchievement,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $achievement): RedirectResponse
    {
        $achievementModel = TrAchievement::findOrFail($achievement);
        $validated = $request->validate([
            'intIntern_ID' => ['required', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'txtAchievementTitle' => ['required', 'string', 'max:255'],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'txtIcon' => ['nullable', 'string', 'max:255'],
            'dtmAwarded' => ['nullable', 'date'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $achievementModel->update([
            ...$validated,
            'txtIcon' => $validated['txtIcon'] ?: 'fa-solid fa-award',
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('achievements.index')->with('success', 'Achievement berhasil diperbarui.');
    }

    public function destroy(string $achievement): RedirectResponse
    {
        TrAchievement::findOrFail($achievement)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('achievements.index')->with('success', 'Achievement berhasil dinonaktifkan.');
    }
}
