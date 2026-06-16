<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.leaderboard');
    }

    public function create(): View
    {
        return view('dashboard.leaderboard');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('leaderboard.index');
    }

    public function show(string $leaderboard): View
    {
        return view('dashboard.leaderboard', compact('leaderboard'));
    }

    public function edit(string $leaderboard): View
    {
        return view('dashboard.leaderboard', compact('leaderboard'));
    }

    public function update(Request $request, string $leaderboard): RedirectResponse
    {
        return redirect()->route('leaderboard.index');
    }

    public function destroy(string $leaderboard): RedirectResponse
    {
        return redirect()->route('leaderboard.index');
    }
}
