<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthPageController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'txtEmail' => ['required', 'email'],
            'txtPassword' => ['required', 'string'],
        ]);

        $user = MUser::where('txtEmail', $credentials['txtEmail'])
            ->where('bitActive', true)
            ->first();

        if (! $user || ! Hash::check($credentials['txtPassword'], $user->txtPassword)) {
            return back()
                ->withErrors(['txtEmail' => 'Email or password does not match.'])
                ->onlyInput('txtEmail');
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user_id', $user->intUser_ID);

        return redirect()->route('dashboard.index');
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'txtRole' => ['required', Rule::in(['Intern', 'Mentor'])],
            'txtGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')],
            'txtPassword' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $now = now();
        $user = MUser::create([
            'txtEmail' => $validated['txtEmail'],
            'txtPassword' => Hash::make($validated['txtPassword']),
            'txtRole' => $validated['txtRole'],
            'bitActive' => true,
            'txtInsertedBy' => 'register',
            'dtmInserted' => $now,
        ]);

        if ($validated['txtRole'] === 'Intern') {
            MIntern::create([
                'intUser_ID' => $user->intUser_ID,
                'txtInternNo' => 'INT-' . str_pad((string) ($user->intUser_ID), 3, '0', STR_PAD_LEFT),
                'txtInternName' => $validated['name'],
                'txtInternGender' => $validated['txtGender'] ?? null,
                'bitActive' => true,
                'txtInsertedBy' => 'register',
                'dtmInserted' => $now,
            ]);
        }

        if ($validated['txtRole'] === 'Mentor') {
            MMentor::create([
                'intUser_ID' => $user->intUser_ID,
                'txtMentorName' => $validated['name'],
                'txtMentorGender' => $validated['txtGender'] ?? null,
                'txtRole' => 'Mentor',
                'bitActive' => true,
                'txtInsertedBy' => 'register',
                'dtmInserted' => $now,
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user_id', $user->intUser_ID);

        return redirect()->route('dashboard.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('auth_user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
