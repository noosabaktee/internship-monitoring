<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(): View
    {
        return view('profile.show');
    }

    public function create(): View
    {
        return view('profile.edit');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    public function show(): View
    {
        return view('profile.show');
    }

    public function edit(?string $profile = null): View
    {
        return view('profile.edit', compact('profile'));
    }

    public function update(Request $request, ?string $profile = null): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    public function destroy(?string $profile = null): RedirectResponse
    {
        return redirect()->route('profile.show');
    }
}
