<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('dashboard.analytics');
    }

    public function create(): View
    {
        return view('dashboard.analytics');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('analytics.index');
    }

    public function show(string $analytic): View
    {
        return view('dashboard.analytics', compact('analytic'));
    }

    public function edit(string $analytic): View
    {
        return view('dashboard.analytics', compact('analytic'));
    }

    public function update(Request $request, string $analytic): RedirectResponse
    {
        return redirect()->route('analytics.index');
    }

    public function destroy(string $analytic): RedirectResponse
    {
        return redirect()->route('analytics.index');
    }
}
