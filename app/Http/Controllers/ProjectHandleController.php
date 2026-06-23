<?php

namespace App\Http\Controllers;

use App\Models\MProjectHandle;
use App\Models\MProjectWeight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectHandleController extends Controller
{
    public function index(): View
    {
        $projectHandles = MProjectHandle::orderBy('intProjectHandle_ID')->get();
        $projectWeight = $this->projectWeight();

        return view('dashboard.project-handles', compact('projectHandles', 'projectWeight'));
    }

    public function create(): View
    {
        $projectHandles = MProjectHandle::orderBy('intProjectHandle_ID')->get();
        $projectWeight = $this->projectWeight();

        return view('dashboard.project-handles', [
            'projectHandles' => $projectHandles,
            'projectWeight' => $projectWeight,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedHandle($request);

        MProjectHandle::create([
            'txtProjectHandleDuration' => $validated['txtProjectHandleDuration'],
            'intProjectHandleMain' => $validated['intProjectHandleMain'],
            'intProjectHandleCollaboration' => $validated['intProjectHandleCollaboration'],
            'intProjectHandleSatellite' => $validated['intProjectHandleSatellite'],
            'intProjectHandleSharing' => $validated['intProjectHandleSharing'],
            'bitActive' => (bool) ($validated['bitActive'] ?? true),
            'txtInsertedBy' => 'system',
            'dtmInserted' => now(),
        ]);

        return redirect()->route('project-handles.index')->with('success', 'Project handle data has been added.');
    }

    public function show(string $projectHandle): View
    {
        $projectHandle = MProjectHandle::findOrFail($projectHandle);

        return view('dashboard.project-handles', compact('projectHandle'));
    }

    public function edit(string $projectHandle): View
    {
        $projectHandles = MProjectHandle::orderBy('intProjectHandle_ID')->get();
        $editingProjectHandle = MProjectHandle::findOrFail($projectHandle);
        $projectWeight = $this->projectWeight();

        return view('dashboard.project-handles', [
            'projectHandles' => $projectHandles,
            'editingProjectHandle' => $editingProjectHandle,
            'projectWeight' => $projectWeight,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $projectHandle): RedirectResponse
    {
        $projectHandleModel = MProjectHandle::findOrFail($projectHandle);
        $validated = $this->validatedHandle($request);

        $projectHandleModel->update([
            'txtProjectHandleDuration' => $validated['txtProjectHandleDuration'],
            'intProjectHandleMain' => $validated['intProjectHandleMain'],
            'intProjectHandleCollaboration' => $validated['intProjectHandleCollaboration'],
            'intProjectHandleSatellite' => $validated['intProjectHandleSatellite'],
            'intProjectHandleSharing' => $validated['intProjectHandleSharing'],
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('project-handles.index')->with('success', 'Project handle data has been updated.');
    }

    public function destroy(string $projectHandle): RedirectResponse
    {
        $projectHandleModel = MProjectHandle::findOrFail($projectHandle);
        $projectHandleModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('project-handles.index')->with('success', 'Project handle data has been deactivated.');
    }

    public function updateWeights(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intProjectWeightMain' => ['required', 'integer', 'min:0'],
            'intProjectWeightCollaboration' => ['required', 'integer', 'min:0'],
            'intProjectWeightSatellite' => ['required', 'integer', 'min:0'],
            'intProjectWeightSharing' => ['required', 'integer', 'min:0'],
        ]);
        $projectWeight = $this->projectWeight();

        $projectWeight->update([
            'intProjectWeightMain' => $validated['intProjectWeightMain'],
            'intProjectWeightCollaboration' => $validated['intProjectWeightCollaboration'],
            'intProjectWeightSatellite' => $validated['intProjectWeightSatellite'],
            'intProjectWeightSharing' => $validated['intProjectWeightSharing'],
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('project-handles.index')->with('success', 'Project weight data has been updated.');
    }

    private function validatedHandle(Request $request): array
    {
        return $request->validate([
            'txtProjectHandleDuration' => ['required', 'string', 'max:255'],
            'intProjectHandleMain' => ['required', 'integer', 'min:0'],
            'intProjectHandleCollaboration' => ['required', 'integer', 'min:0'],
            'intProjectHandleSatellite' => ['required', 'integer', 'min:0'],
            'intProjectHandleSharing' => ['required', 'integer', 'min:0'],
            'bitActive' => ['nullable', 'boolean'],
        ]);
    }

    private function projectWeight(): MProjectWeight
    {
        return MProjectWeight::where('bitActive', true)->orderBy('intProjectWeight_ID')->first()
            ?? MProjectWeight::create([
                'intProjectWeightMain' => 10,
                'intProjectWeightCollaboration' => 7,
                'intProjectWeightSatellite' => 5,
                'intProjectWeightSharing' => 3,
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => now(),
            ]);
    }
}
