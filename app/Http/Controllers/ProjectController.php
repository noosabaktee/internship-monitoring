<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\TrInternProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = MProject::with(['assignments.intern', 'assignments.mentor'])->orderBy('intProject_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();

        return view('dashboard.projects', compact('projects', 'interns', 'mentors'));
    }

    public function create(): View
    {
        $projects = MProject::with(['assignments.intern', 'assignments.mentor'])->orderBy('intProject_ID')->get();
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();

        return view('dashboard.projects', [
            'projects' => $projects,
            'interns' => $interns,
            'mentors' => $mentors,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'txtProjectName' => ['required', 'string', 'max:255'],
            'txtProjectType' => ['required', Rule::in(['Main', 'Satellite', 'Collaboration', 'Sharing'])],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
            'intIntern_ID' => ['nullable', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'integer', Rule::exists('mMentor', 'intMentor_ID')],
            'floatProgress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'txtStatus' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            $now = now();
            $project = MProject::create([
                'txtProjectName' => $validated['txtProjectName'],
                'txtProjectType' => $validated['txtProjectType'],
                'txtDescription' => $validated['txtDescription'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            if (! empty($validated['intIntern_ID']) && ! empty($validated['intMentor_ID'])) {
                TrInternProject::create([
                    'intIntern_ID' => $validated['intIntern_ID'],
                    'intProject_ID' => $project->intProject_ID,
                    'intMentor_ID' => $validated['intMentor_ID'],
                    'floatProgress' => $validated['floatProgress'] ?? 0,
                    'txtStatus' => $validated['txtStatus'] ?? 'On Track',
                    'bitActive' => true,
                    'txtInsertedBy' => 'system',
                    'dtmInserted' => $now,
                ]);
            }
        });

        return redirect()->route('projects.index')->with('success', 'Data project berhasil ditambahkan.');
    }

    public function show(string $project): View
    {
        $project = MProject::with(['assignments.intern', 'assignments.mentor'])->findOrFail($project);

        return view('dashboard.projects', compact('project'));
    }

    public function edit(string $project): View
    {
        $projects = MProject::with(['assignments.intern', 'assignments.mentor'])->orderBy('intProject_ID')->get();
        $editingProject = MProject::with(['assignments.intern', 'assignments.mentor'])->findOrFail($project);
        $interns = MIntern::where('bitActive', true)->orderBy('txtInternName')->get();
        $mentors = MMentor::where('bitActive', true)->orderBy('txtMentorName')->get();

        return view('dashboard.projects', [
            'projects' => $projects,
            'editingProject' => $editingProject,
            'interns' => $interns,
            'mentors' => $mentors,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $project): RedirectResponse
    {
        $projectModel = MProject::with('assignments')->findOrFail($project);
        $validated = $request->validate([
            'txtProjectName' => ['required', 'string', 'max:255'],
            'txtProjectType' => ['required', Rule::in(['Main', 'Satellite', 'Collaboration', 'Sharing'])],
            'txtDescription' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
            'intIntern_ID' => ['nullable', 'integer', Rule::exists('mIntern', 'intIntern_ID')],
            'intMentor_ID' => ['nullable', 'integer', Rule::exists('mMentor', 'intMentor_ID')],
            'floatProgress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'txtStatus' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($projectModel, $validated) {
            $now = now();
            $projectModel->update([
                'txtProjectName' => $validated['txtProjectName'],
                'txtProjectType' => $validated['txtProjectType'],
                'txtDescription' => $validated['txtDescription'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);

            if (! empty($validated['intIntern_ID']) && ! empty($validated['intMentor_ID'])) {
                TrInternProject::updateOrCreate(
                    ['intProject_ID' => $projectModel->intProject_ID],
                    [
                        'intIntern_ID' => $validated['intIntern_ID'],
                        'intMentor_ID' => $validated['intMentor_ID'],
                        'floatProgress' => $validated['floatProgress'] ?? 0,
                        'txtStatus' => $validated['txtStatus'] ?? 'On Track',
                        'bitActive' => true,
                        'txtUpdatedBy' => 'system',
                        'dtmUpdated' => $now,
                    ],
                );
            }
        });

        return redirect()->route('projects.index')->with('success', 'Data project berhasil diperbarui.');
    }

    public function destroy(string $project): RedirectResponse
    {
        $projectModel = MProject::findOrFail($project);
        $now = now();

        $projectModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        TrInternProject::where('intProject_ID', $projectModel->intProject_ID)->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('projects.index')->with('success', 'Data project berhasil dinonaktifkan.');
    }
}
