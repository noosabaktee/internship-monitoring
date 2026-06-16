<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentorController extends Controller
{
    public function index(): View
    {
        return view('dashboard.mentors');
    }

    public function create(): View
    {
        return view('dashboard.mentors');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('mentors.index');
    }

    public function show(string $mentor): View
    {
        return view('dashboard.mentors', compact('mentor'));
    }

    public function edit(string $mentor): View
    {
        return view('dashboard.mentors', compact('mentor'));
    }

    public function update(Request $request, string $mentor): RedirectResponse
    {
        return redirect()->route('mentors.index');
    }

    public function destroy(string $mentor): RedirectResponse
    {
        return redirect()->route('mentors.index');
    }
}
