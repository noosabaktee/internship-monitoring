<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('dashboard.settings');
    }

    public function create(): View
    {
        return view('dashboard.settings');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('settings.index');
    }

    public function show(string $setting): View
    {
        return view('dashboard.settings', compact('setting'));
    }

    public function edit(string $setting): View
    {
        return view('dashboard.settings', compact('setting'));
    }

    public function update(Request $request, string $setting): RedirectResponse
    {
        return redirect()->route('settings.index');
    }

    public function destroy(string $setting): RedirectResponse
    {
        return redirect()->route('settings.index');
    }
}
