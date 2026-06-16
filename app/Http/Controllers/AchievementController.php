<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(): View
    {
        return view('dashboard.achievements');
    }

    public function create(): View
    {
        return view('dashboard.achievements');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('achievements.index');
    }

    public function show(string $achievement): View
    {
        return view('dashboard.achievements', compact('achievement'));
    }

    public function edit(string $achievement): View
    {
        return view('dashboard.achievements', compact('achievement'));
    }

    public function update(Request $request, string $achievement): RedirectResponse
    {
        return redirect()->route('achievements.index');
    }

    public function destroy(string $achievement): RedirectResponse
    {
        return redirect()->route('achievements.index');
    }
}
