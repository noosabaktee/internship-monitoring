<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('dashboard.reports');
    }

    public function create(): View
    {
        return view('dashboard.reports');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('reports.index');
    }

    public function show(string $report): View
    {
        return view('dashboard.reports', compact('report'));
    }

    public function edit(string $report): View
    {
        return view('dashboard.reports', compact('report'));
    }

    public function update(Request $request, string $report): RedirectResponse
    {
        return redirect()->route('reports.index');
    }

    public function destroy(string $report): RedirectResponse
    {
        return redirect()->route('reports.index');
    }
}
