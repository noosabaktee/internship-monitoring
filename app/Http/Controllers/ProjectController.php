<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('dashboard.projects');
    }

    public function create(): View
    {
        return view('dashboard.projects');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('projects.index');
    }

    public function show(string $project): View
    {
        return view('dashboard.projects', compact('project'));
    }

    public function edit(string $project): View
    {
        return view('dashboard.projects', compact('project'));
    }

    public function update(Request $request, string $project): RedirectResponse
    {
        return redirect()->route('projects.index');
    }

    public function destroy(string $project): RedirectResponse
    {
        return redirect()->route('projects.index');
    }
}
