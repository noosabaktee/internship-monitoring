<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternController extends Controller
{
    public function index(): View
    {
        return view('dashboard.interns');
    }

    public function create(): View
    {
        return view('dashboard.interns');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('interns.index');
    }

    public function show(string $intern): View
    {
        return view('dashboard.interns', compact('intern'));
    }

    public function edit(string $intern): View
    {
        return view('dashboard.interns', compact('intern'));
    }

    public function update(Request $request, string $intern): RedirectResponse
    {
        return redirect()->route('interns.index');
    }

    public function destroy(string $intern): RedirectResponse
    {
        return redirect()->route('interns.index');
    }
}
