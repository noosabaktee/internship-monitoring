<?php

namespace App\Http\Controllers;

use App\Models\MIntern;
use App\Models\MUser;
use App\Models\TrInternProject;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InternController extends Controller
{
    public function index(): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();

        return view('dashboard.interns', compact('interns'));
    }

    public function create(): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();

        return view('dashboard.interns', [
            'interns' => $interns,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtInternGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtDept' => ['nullable', 'string', 'max:255'],
            'txtInternCostCenter' => ['nullable', 'string', 'max:255'],
            'txtInternType' => ['required', Rule::in(RoleAccess::internTypes())],
            'floatInternSalary' => ['nullable', 'numeric', 'min:0'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'txtInternExtendEndDates' => ['nullable', 'array'],
            'txtInternExtendEndDates.*' => ['nullable', 'date'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $intern = DB::transaction(function () use ($validated): MIntern {
            $now = now();
            $joinDate = $validated['dtmInserted'] ?? $now;
            $user = MUser::create([
                'txtEmail' => $validated['txtEmail'],
                'txtPassword' => Hash::make($validated['txtPassword'] ?: 'password'),
                'txtRole' => 'Intern',
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $now,
            ]);

            return MIntern::create([
                'intUser_ID' => $user->intUser_ID,
                'txtInternNo' => 'INT-'.str_pad((string) $user->intUser_ID, 3, '0', STR_PAD_LEFT),
                'txtInternName' => $validated['txtInternName'],
                'txtInternGender' => $validated['txtInternGender'] ?? null,
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtDept' => $validated['txtDept'] ?? null,
                'txtInternCostCenter' => $validated['txtInternCostCenter'] ?? null,
                'txtInternType' => $validated['txtInternType'],
                'floatInternSalary' => round((float) ($validated['floatInternSalary'] ?? 0), 2),
                'dtmEndDate' => $validated['dtmEndDate'] ?? null,
                'txtInternExtendEndDates' => $this->extensionDates($validated['txtInternExtendEndDates'] ?? []),
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'system',
                'dtmInserted' => $joinDate,
            ]);
        });

        $intern->load('user');
        $notifications->send(
            $intern->user,
            'internship',
            'Selamat datang di program internship',
            'Profil internship kamu sudah aktif. Lengkapi Face ID sebelum melakukan absensi pertama.',
            route('profile.show'),
            'intern-created:'.$intern->intIntern_ID,
        );

        return redirect()->route('interns.index')->with('success', 'Intern data has been added.');
    }

    public function show(string $intern): View
    {
        $intern = MIntern::with(['user', 'projects.project.projectMentors.mentor', 'projects.mentor', 'achievements', 'evaluations'])
            ->findOrFail($intern);

        return view('profile.show', compact('intern'));
    }

    public function edit(string $intern): View
    {
        $interns = MIntern::with('user')->orderBy('intIntern_ID')->get();
        $editingIntern = MIntern::with('user')->findOrFail($intern);

        return view('dashboard.interns', [
            'interns' => $interns,
            'editingIntern' => $editingIntern,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, string $intern, NotificationService $notifications): RedirectResponse
    {
        $internModel = MIntern::with('user')->findOrFail($intern);
        $previousEndDate = $internModel->effectiveEndDate()?->toDateString();
        $validated = $request->validate([
            'txtEmail' => ['required', 'email', 'max:255', Rule::unique('mUser', 'txtEmail')->ignore($internModel->intUser_ID, 'intUser_ID')],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtInternGender' => ['nullable', Rule::in(['Male', 'Female', 'Laki-laki', 'Perempuan'])],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtDept' => ['nullable', 'string', 'max:255'],
            'txtInternCostCenter' => ['nullable', 'string', 'max:255'],
            'txtInternType' => ['required', Rule::in(RoleAccess::internTypes())],
            'floatInternSalary' => ['nullable', 'numeric', 'min:0'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'txtInternExtendEndDates' => ['nullable', 'array'],
            'txtInternExtendEndDates.*' => ['nullable', 'date'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($internModel, $validated) {
            $now = now();
            $userData = [
                'txtEmail' => $validated['txtEmail'],
                'txtRole' => 'Intern',
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ];

            if (! empty($validated['txtPassword'])) {
                $userData['txtPassword'] = Hash::make($validated['txtPassword']);
            }

            $internModel->user->update($userData);
            $internModel->update([
                'txtInternName' => $validated['txtInternName'],
                'txtInternGender' => $validated['txtInternGender'] ?? null,
                'txtUniversity' => $validated['txtUniversity'] ?? null,
                'txtDept' => $validated['txtDept'] ?? null,
                'txtInternCostCenter' => $validated['txtInternCostCenter'] ?? null,
                'txtInternType' => $validated['txtInternType'],
                'floatInternSalary' => round((float) ($validated['floatInternSalary'] ?? 0), 2),
                'dtmEndDate' => $validated['dtmEndDate'] ?? null,
                'txtInternExtendEndDates' => $this->extensionDates($validated['txtInternExtendEndDates'] ?? []),
                'bitActive' => (bool) ($validated['bitActive'] ?? false),
                'dtmInserted' => $validated['dtmInserted'] ?? $internModel->dtmInserted,
                'txtUpdatedBy' => 'system',
                'dtmUpdated' => $now,
            ]);
        });

        $internModel->refresh()->load('user');
        $currentEndDate = $internModel->effectiveEndDate()?->toDateString();

        if ($currentEndDate && $currentEndDate !== $previousEndDate) {
            $wasExtended = $previousEndDate
                && Carbon::parse($currentEndDate)->gt(Carbon::parse($previousEndDate));
            $notifications->send(
                $internModel->user,
                'internship',
                $wasExtended ? 'Masa internship diperpanjang' : ($previousEndDate ? 'Tanggal selesai internship diperbarui' : 'Tanggal selesai internship ditetapkan'),
                'Tanggal akhir internship kamu sekarang '.Carbon::parse($currentEndDate)->format('d M Y').'.',
                route('profile.show'),
                'intern-end-updated:'.$internModel->intIntern_ID.':'.$currentEndDate,
            );

            TrInternProject::with('mentor.user')
                ->where('intIntern_ID', $internModel->intIntern_ID)
                ->where('bitActive', true)
                ->get()
                ->pluck('mentor.user')
                ->filter()
                ->unique('intUser_ID')
                ->each(fn (MUser $mentorUser) => $notifications->send(
                    $mentorUser,
                    'internship',
                    'Periode intern diperbarui',
                    $internModel->txtInternName.' memiliki tanggal akhir baru '.Carbon::parse($currentEndDate)->format('d M Y').'.',
                    route('profile.intern.show', $internModel->intIntern_ID),
                    'mentored-intern-end-updated:'.$internModel->intIntern_ID.':'.$currentEndDate,
                ));
        }

        return redirect()->route('interns.index')->with('success', 'Intern data has been updated.');
    }

    public function destroy(string $intern): RedirectResponse
    {
        $internModel = MIntern::with('user')->findOrFail($intern);
        $now = now();

        $internModel->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);
        $internModel->user?->update([
            'bitActive' => false,
            'txtUpdatedBy' => 'system',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('interns.index')->with('success', 'Intern data has been deactivated.');
    }

    /**
     * @param  array<int, string|null>  $dates
     * @return array<int, string>
     */
    private function extensionDates(array $dates): array
    {
        return collect($dates)
            ->filter()
            ->values()
            ->all();
    }
}
