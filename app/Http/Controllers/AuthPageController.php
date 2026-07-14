<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MAdminProfile;
use App\Models\MProject;
use App\Models\MMentor;
use App\Models\MUser;
use App\Models\TrCalendarSharing;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class AuthPageController extends Controller
{
    public function login(): View
    {
        return view('auth.login', [
            'loginSummary' => $this->loginSummary(),
        ]);
    }

    private function loginSummary(): array
    {
        $fallback = $this->fallbackLoginSummary();

        try {
            $latestEvaluations = TrEvaluation::with(['intern.user'])
                ->where('bitActive', true)
                ->orderByDesc('dtmPeriod')
                ->get()
                ->unique('intIntern_ID')
                ->values();
            $assignments = TrInternProject::with(['intern.user', 'project.skillSet'])
                ->where('bitActive', true)
                ->get();
            $activeAssignments = $assignments->filter(fn ($assignment) => $assignment->project?->bitActive);
            $projectTypes = $activeAssignments
                ->groupBy(fn ($assignment) => $assignment->project?->txtProjectType ?: 'Other')
                ->map->count();
            $skillSetProjects = $activeAssignments
                ->groupBy(fn ($assignment) => $assignment->project?->skillSet?->txtSkillSetName ?: 'Unmapped')
                ->map->count()
                ->sortDesc();
            $topEvaluation = $latestEvaluations
                ->sortByDesc('floatExposureScore')
                ->first();
            $topIntern = $topEvaluation?->intern;
            $averageScore = round((float) $latestEvaluations->avg('floatExposureScore'), 1);
            $actualProgress = round((float) $activeAssignments->avg('floatProgress'), 1);
            $activeProjects = MProject::where('bitActive', true)->count();
            $totalInterns = MIntern::where('bitActive', true)->count();
            $sharingCount = (int) ($projectTypes['Sharing'] ?? 0);
            $collaborationCount = (int) ($projectTypes['Collaboration'] ?? 0);
            $topScore = (float) ($topEvaluation?->floatExposureScore ?? 0);
            $topAssignment = $topIntern
                ? $activeAssignments
                    ->where('intIntern_ID', $topIntern->intIntern_ID)
                    ->sortByDesc('floatProgress')
                    ->first()
                : null;
            $topFocus = $topAssignment?->project?->skillSet?->txtSkillSetName
                ?? $topAssignment?->project?->txtProjectName
                ?? $fallback['topPerformer']['focus'];
            $nextSession = TrCalendarSharing::where('bitActive', true)
                ->whereDate('dtmCalendarSharingDate', '>=', now()->startOfDay())
                ->orderBy('dtmCalendarSharingDate')
                ->orderBy('intCalendarSharing_ID')
                ->first();

            return [
                'monthLabel' => now()->format('F Y'),
                'totalInterns' => $totalInterns ?: $fallback['totalInterns'],
                'activeProjects' => $activeProjects ?: $fallback['activeProjects'],
                'averageScore' => $averageScore ?: $fallback['averageScore'],
                'collaborationCount' => $collaborationCount ?: $fallback['collaborationCount'],
                'actualProgress' => $actualProgress ?: $fallback['actualProgress'],
                'progressItems' => [
                    [
                        'label' => 'Exposure Rate',
                        'value' => $averageScore ?: $fallback['averageScore'],
                        'display' => ($averageScore ?: $fallback['averageScore']) . '%',
                        'color' => '#21d66f',
                    ],
                    [
                        'label' => 'Project Completion',
                        'value' => $actualProgress ?: $fallback['actualProgress'],
                        'display' => ($actualProgress ?: $fallback['actualProgress']) . '%',
                        'color' => '#f6c343',
                    ],
                    [
                        'label' => 'Collaboration',
                        'value' => min(100, $collaborationCount * 20),
                        'display' => $collaborationCount . ' Projects',
                        'color' => '#54a5ff',
                    ],
                    [
                        'label' => 'Knowledge Sharing',
                        'value' => min(100, $sharingCount * 20),
                        'display' => $sharingCount . ' Sessions',
                        'color' => '#b076ff',
                    ],
                ],
                'topPerformer' => [
                    'name' => $topIntern?->txtInternName ?: $fallback['topPerformer']['name'],
                    'tag' => $topScore >= 85 ? 'High Exposure' : 'Rising Talent',
                    'score' => $topScore ?: $fallback['topPerformer']['score'],
                    'focus' => $topFocus,
                    'photo' => $topIntern?->user?->txtProfilePhoto,
                ],
                'performerAvatars' => $latestEvaluations->sortByDesc('floatExposureScore')->take(5)->map(fn ($evaluation) => [
                    'name' => $evaluation->intern?->txtInternName ?? 'Intern',
                    'photo' => $evaluation->intern?->user?->txtProfilePhoto,
                ])->values()->all() ?: $fallback['performerAvatars'],
                'sessions' => TrCalendarSharing::where('bitActive', true)
                    ->whereDate('dtmCalendarSharingDate', '>=', now()->startOfDay())
                    ->orderBy('dtmCalendarSharingDate')
                    ->orderBy('intCalendarSharing_ID')
                    ->take(3)
                    ->get()
                    ->map(fn ($sharing) => [
                        'day' => $sharing->dtmCalendarSharingDate?->format('d') ?? '--',
                        'month' => strtoupper($sharing->dtmCalendarSharingDate?->format('M') ?? 'TBA'),
                        'title' => $sharing->txtCalendarSharingTheme ?: 'Sharing Session',
                        'time' => '14:00 - 16:00',
                        'icon' => $sharing->txtCalendarSharingIcon ?: 'fa-regular fa-calendar-days',
                    ])
                    ->values()
                    ->all() ?: $fallback['sessions'],
                'skillSets' => $skillSetProjects->take(7)->map(fn ($total, $name) => [
                    'name' => $name,
                    'count' => $total,
                ])->values()->all() ?: $fallback['skillSets'],
                'updates' => [
                    ($topIntern?->txtInternName ?: $fallback['topPerformer']['name']) . ' leads this month with ' . number_format($topScore ?: $fallback['topPerformer']['score'], 1) . ' exposure score',
                    $collaborationCount . ' collaboration projects currently active',
                    $nextSession
                        ? 'Next sharing session on ' . $nextSession->dtmCalendarSharingDate?->format('d F Y')
                        : 'New sharing sessions will appear here',
                ],
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function fallbackLoginSummary(): array
    {
        return [
            'monthLabel' => now()->format('F Y'),
            'totalInterns' => 10,
            'activeProjects' => 6,
            'averageScore' => 80.7,
            'collaborationCount' => 3,
            'actualProgress' => 41.5,
            'progressItems' => [
                ['label' => 'Exposure Rate', 'value' => 85, 'display' => '85%', 'color' => '#21d66f'],
                ['label' => 'Project Completion', 'value' => 41.5, 'display' => '41.5%', 'color' => '#f6c343'],
                ['label' => 'Collaboration', 'value' => 60, 'display' => '3 Projects', 'color' => '#54a5ff'],
                ['label' => 'Knowledge Sharing', 'value' => 60, 'display' => '3 Sessions', 'color' => '#b076ff'],
            ],
            'topPerformer' => [
                'name' => 'Christopher Rey Wijaya',
                'tag' => 'High Exposure',
                'score' => 22,
                'focus' => 'Engineering Modeling & Simulation',
                'photo' => null,
            ],
            'performerAvatars' => [
                ['name' => 'Humaira Zeanova', 'photo' => null],
                ['name' => 'Christopher Rey Wijaya', 'photo' => null],
                ['name' => 'Muhammad Kautsar', 'photo' => null],
                ['name' => 'Delia Nur Ilmi Salam', 'photo' => null],
            ],
            'sessions' => [
                ['day' => '27', 'month' => 'JUN', 'title' => 'Digital Twin Project Review', 'time' => '14:00 - 16:00', 'icon' => 'fa-solid fa-network-wired'],
                ['day' => '03', 'month' => 'JUL', 'title' => 'Computer Vision Use Case', 'time' => '14:00 - 16:00', 'icon' => 'fa-solid fa-brain'],
                ['day' => '09', 'month' => 'JUL', 'title' => 'CFD Simulation Clinic', 'time' => '14:00 - 16:00', 'icon' => 'fa-solid fa-gears'],
            ],
            'skillSets' => [
                ['name' => 'Engineering Modeling & Simulation', 'count' => 4],
                ['name' => 'Web Development', 'count' => 3],
                ['name' => 'Embedded Systems & IoT Data Acquisition', 'count' => 3],
                ['name' => 'AI & Computer Vision', 'count' => 1],
                ['name' => 'Robotic Process Automation (RPA)', 'count' => 2],
                ['name' => 'Reverse Engineering', 'count' => 1],
            ],
            'updates' => [
                'Christopher Rey Wijaya reached High Exposure Level',
                '3 Collaboration Projects currently active',
                'New Sharing Session on 27 June 2026',
            ],
        ];
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
            'txtRole' => ['required', Rule::in([RoleAccess::ROLE_INTERN, RoleAccess::ROLE_MENTOR, RoleAccess::ROLE_HEADMASTER, RoleAccess::ROLE_HRD])],
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

        if ($validated['txtRole'] === RoleAccess::ROLE_INTERN) {
            MIntern::create([
                'intUser_ID' => $user->intUser_ID,
                'txtInternNo' => 'INT-' . str_pad((string) ($user->intUser_ID), 3, '0', STR_PAD_LEFT),
                'txtInternName' => $validated['name'],
                'txtInternGender' => $validated['txtGender'] ?? null,
                'txtInternType' => RoleAccess::INTERN_DIGITALISASI,
                'floatInternSalary' => 0,
                'bitActive' => true,
                'txtInsertedBy' => 'register',
                'dtmInserted' => $now,
            ]);
        }

        if ($validated['txtRole'] === RoleAccess::ROLE_MENTOR) {
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

        if (in_array($validated['txtRole'], [RoleAccess::ROLE_HEADMASTER, RoleAccess::ROLE_HRD], true)) {
            MAdminProfile::create([
                'intUser_ID' => $user->intUser_ID,
                'txtAdminProfileName' => $validated['name'],
                'txtAdminProfileGender' => $validated['txtGender'] ?? null,
                'txtAdminProfileDepartment' => $validated['txtRole'] === RoleAccess::ROLE_HRD ? 'Human Resources' : 'Internship Program',
                'txtAdminProfilePosition' => $validated['txtRole'],
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
