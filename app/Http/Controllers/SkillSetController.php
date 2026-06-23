<?php

namespace App\Http\Controllers;

use App\Models\MSkillSet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SkillSetController extends Controller
{
    public function index(): View
    {
        $skillSets = MSkillSet::orderBy('intSkillSet_ID')->get();

        return view('dashboard.skill-sets', compact('skillSets'));
    }

    public function create(): View
    {
        $skillSets = MSkillSet::orderBy('intSkillSet_ID')->get();

        return view('dashboard.skill-sets', [
            'skillSets' => $skillSets,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'txtSkillSetName' => ['required', 'string', 'max:255'],
            'txtSkillSetDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        MSkillSet::create([
            'txtSkillSetName' => $validated['txtSkillSetName'],
            'txtSkillSetDescription' => $validated['txtSkillSetDescription'] ?? null,
            'bitActive' => (bool) ($validated['bitActive'] ?? true),
            'txtInsertedBy' => 'system',
            'dtmInserted' => now(),
        ]);

        return redirect()->route('skill-sets.index')->with('success', 'Skill set data has been added.');
    }

    public function show(string $skillSet): View
    {
        $skillSet = MSkillSet::findOrFail($skillSet);

        return view('dashboard.skill-sets', compact('skillSet'));
    }

    public function edit(string $skillSet): View
    {
        $skillSets = MSkillSet::orderBy('intSkillSet_ID')->get();
        $editingSkillSet = MSkillSet::findOrFail($skillSet);

        return view('dashboard.skill-sets', [
            'skillSets' => $skillSets,
            'editingSkillSet' => $editingSkillSet,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $skillSet): RedirectResponse
    {
        $skillSetModel = MSkillSet::findOrFail($skillSet);
        $validated = $request->validate([
            'txtSkillSetName' => ['required', 'string', 'max:255'],
            'txtSkillSetDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $skillSetModel->update([
            'txtSkillSetName' => $validated['txtSkillSetName'],
            'txtSkillSetDescription' => $validated['txtSkillSetDescription'] ?? null,
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('skill-sets.index')->with('success', 'Skill set data has been updated.');
    }

    public function destroy(string $skillSet): RedirectResponse
    {
        $skillSetModel = MSkillSet::findOrFail($skillSet);
        $skillSetModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('skill-sets.index')->with('success', 'Skill set data has been deactivated.');
    }
}
