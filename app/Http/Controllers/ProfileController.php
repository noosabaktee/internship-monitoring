<?php

namespace App\Http\Controllers;

use App\Models\MFaceEnrollment;
use App\Models\MIntern;
use App\Models\MAdminProfile;
use App\Models\MMentor;
use App\Models\MUser;
use App\Services\FaceRecognitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProfileController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';
    private const FACE_ALGORITHM = 'insightface-buffalo_l-v1';

    public function __construct(private readonly FaceRecognitionService $faceRecognition)
    {
    }

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
        $user = $this->currentUser();

        if ($user && ! $user->intern && ! $user->mentor) {
            $user->load('adminProfile');

            return view('profile.admin', compact('user'));
        }

        if ($user?->txtRole === 'Mentor' && $user->mentor) {
            $mentor = $user->mentor->load([
                'user',
                'internProjects.intern',
                'internProjects.project',
                'projectMentors.project.assignments.intern',
            ]);

            return view('profile.mentor', compact('mentor'));
        }

        $intern = $user?->intern;

        if ($intern) {
            $intern->load(['user.faceEnrollment', 'projects.project.projectMentors.mentor', 'projects.mentor', 'achievements', 'evaluations']);
        }

        return view('profile.show', compact('intern'));
    }

    public function edit(?string $profile = null): View
    {
        $user = $this->currentUser();
        $intern = $user?->intern;
        $mentor = $user?->mentor;
        $adminProfile = $user?->adminProfile;

        return view('profile.edit', compact('intern', 'mentor', 'adminProfile', 'user', 'profile'));
    }

    public function update(Request $request, ?string $profile = null): RedirectResponse
    {
        $user = $this->currentUser();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->txtRole === 'Mentor' && $user->mentor) {
            return $this->updateMentorProfile($request, $user->mentor);
        }

        if (! $user->intern && ! $user->mentor) {
            return $this->updateAdminProfile($request, $user);
        }

        $intern = $user->intern;

        if (! $intern) {
            return redirect()->route('profile.show')->withErrors(['profile' => 'No profile is available to edit.']);
        }

        return $this->updateInternProfile($request, $intern);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'txtProfilePhoto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->txtProfilePhoto) {
            Storage::disk('public')->delete($user->txtProfilePhoto);
        }

        $path = $validated['txtProfilePhoto']->store('profile-photos', 'public');
        $user->update([
            'txtProfilePhoto' => $path,
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile photo has been updated.');
    }

    public function storeFaceEnrollment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        if (! $user || $user->txtRole === 'Mentor' || ! $user->intern) {
            return redirect()->route('profile.show')->withErrors(['profile' => 'Face ID hanya dapat didaftarkan oleh intern.']);
        }

        $validated = $request->validate([
            'txtFaceEnrollmentImages' => ['required', 'string'],
            'intFaceEnrollmentSampleCount' => ['nullable', 'integer', 'min:1', 'max:12'],
            'floatFaceEnrollmentQuality' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);
        $images = $this->decodeImageList($validated['txtFaceEnrollmentImages']);
        $now = Carbon::now(self::TIMEZONE);

        try {
            $facePayload = $this->faceRecognition->enroll($images);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['profile' => $exception->getMessage()]);
        }

        $descriptor = $this->decodeDescriptor($facePayload['embedding'] ?? null, 'txtFaceEnrollmentImages');

        MFaceEnrollment::updateOrCreate(
            ['intUser_ID' => $user->intUser_ID],
            [
                'txtFaceEnrollmentDescriptor' => $descriptor,
                'txtFaceEnrollmentAlgorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
                'intFaceEnrollmentSampleCount' => (int) ($facePayload['sample_count'] ?? count($images)),
                'floatFaceEnrollmentQuality' => round((float) ($facePayload['quality'] ?? 0), 4),
                'dtmFaceEnrollmentRegistered' => $now,
                'bitActive' => true,
                'txtInsertedBy' => $user->txtEmail ?? 'system',
                'dtmInserted' => $now,
                'txtUpdatedBy' => $user->txtEmail ?? 'system',
                'dtmUpdated' => $now,
            ],
        );

        return redirect()->route('profile.show')->with('success', 'Face ID absensi berhasil didaftarkan.');
    }

    public function destroyFaceEnrollment(): RedirectResponse
    {
        $user = $this->currentUser();

        if (! $user || $user->txtRole === 'Mentor' || ! $user->intern) {
            return redirect()->route('profile.show')->withErrors(['profile' => 'Face ID hanya dapat direset oleh intern.']);
        }

        $user->faceEnrollment()->update([
            'bitActive' => false,
            'txtUpdatedBy' => $user->txtEmail ?? 'system',
            'dtmUpdated' => Carbon::now(self::TIMEZONE),
        ]);

        return redirect()->route('profile.show')->with('success', 'Face ID absensi berhasil direset.');
    }

    public function showIntern(MIntern $intern): View
    {
        $intern->load(['user.faceEnrollment', 'projects.project.projectMentors.mentor', 'projects.mentor', 'achievements', 'evaluations']);

        return view('profile.show', compact('intern'));
    }

    public function showMentor(MMentor $mentor): View
    {
        $mentor->load([
            'user',
            'internProjects.intern',
            'internProjects.project',
            'projectMentors.project.assignments.intern',
        ]);

        return view('profile.mentor', compact('mentor'));
    }

    public function destroy(?string $profile = null): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    private function updateInternProfile(Request $request, MIntern $intern): RedirectResponse
    {
        $validated = $request->validate([
            'txtInternNo' => ['nullable', 'string', 'max:255'],
            'txtInternName' => ['required', 'string', 'max:255'],
            'txtEmail' => ['required', 'email', 'max:255'],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtInternGender' => ['nullable', 'in:Male,Female,Laki-laki,Perempuan'],
            'txtUniversity' => ['nullable', 'string', 'max:255'],
            'txtDept' => ['nullable', 'string', 'max:255'],
            'txtBio' => ['nullable', 'string', 'max:255'],
            'dtmInserted' => ['nullable', 'date'],
            'dtmEndDate' => ['nullable', 'date', 'after_or_equal:dtmInserted'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $userData = [
            'txtEmail' => $validated['txtEmail'],
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ];

        if (! empty($validated['txtPassword'])) {
            $userData['txtPassword'] = Hash::make($validated['txtPassword']);
        }

        $intern->user?->update($userData);
        $intern->update([
            'txtInternNo' => $validated['txtInternNo'] ?: $intern->txtInternNo,
            'txtInternName' => $validated['txtInternName'],
            'txtInternGender' => $validated['txtInternGender'] ?? null,
            'txtUniversity' => $validated['txtUniversity'] ?? null,
            'txtDept' => $validated['txtDept'] ?? null,
            'txtBio' => $validated['txtBio'] ?? null,
            'dtmInserted' => $validated['dtmInserted'] ?? $intern->dtmInserted,
            'dtmEndDate' => $validated['dtmEndDate'] ?? null,
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile has been updated.');
    }

    private function updateMentorProfile(Request $request, MMentor $mentor): RedirectResponse
    {
        $validated = $request->validate([
            'txtMentorName' => ['required', 'string', 'max:255'],
            'txtEmail' => ['required', 'email', 'max:255'],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtMentorGender' => ['nullable', 'in:Male,Female,Laki-laki,Perempuan'],
            'txtDepartment' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $userData = [
            'txtEmail' => $validated['txtEmail'],
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ];

        if (! empty($validated['txtPassword'])) {
            $userData['txtPassword'] = Hash::make($validated['txtPassword']);
        }

        $mentor->user?->update($userData);
        $mentor->update([
            'txtMentorName' => $validated['txtMentorName'],
            'txtMentorGender' => $validated['txtMentorGender'] ?? null,
            'txtDepartment' => $validated['txtDepartment'] ?? null,
            'txtRole' => $mentor->txtRole ?: 'Mentor',
            'bitActive' => (bool) ($validated['bitActive'] ?? false),
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile has been updated.');
    }

    private function updateAdminProfile(Request $request, MUser $user): RedirectResponse
    {
        $validated = $request->validate([
            'txtAdminProfileName' => ['required', 'string', 'max:255'],
            'txtEmail' => ['required', 'email', 'max:255'],
            'txtPassword' => ['nullable', 'string', 'min:6'],
            'txtAdminProfileGender' => ['nullable', 'in:Male,Female,Laki-laki,Perempuan'],
            'txtAdminProfileDepartment' => ['nullable', 'string', 'max:255'],
            'txtAdminProfilePhone' => ['nullable', 'string', 'max:50'],
            'txtAdminProfileBio' => ['nullable', 'string', 'max:255'],
            'bitActive' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $userData = [
            'txtEmail' => $validated['txtEmail'],
            'bitActive' => (bool) ($validated['bitActive'] ?? true),
            'txtUpdatedBy' => 'profile',
            'dtmUpdated' => $now,
        ];

        if (! empty($validated['txtPassword'])) {
            $userData['txtPassword'] = Hash::make($validated['txtPassword']);
        }

        $user->update($userData);
        MAdminProfile::updateOrCreate(
            ['intUser_ID' => $user->intUser_ID],
            [
                'txtAdminProfileName' => $validated['txtAdminProfileName'],
                'txtAdminProfileGender' => $validated['txtAdminProfileGender'] ?? null,
                'txtAdminProfileDepartment' => $validated['txtAdminProfileDepartment'] ?? null,
                'txtAdminProfilePosition' => $user->adminProfile?->txtAdminProfilePosition ?: $user->txtRole,
                'txtAdminProfilePhone' => $validated['txtAdminProfilePhone'] ?? null,
                'txtAdminProfileBio' => $validated['txtAdminProfileBio'] ?? null,
                'bitActive' => (bool) ($validated['bitActive'] ?? true),
                'txtInsertedBy' => 'profile',
                'dtmInserted' => $now,
                'txtUpdatedBy' => 'profile',
                'dtmUpdated' => $now,
            ],
        );

        return redirect()->route('profile.show')->with('success', 'Profile has been updated.');
    }

    /**
     * @return array<int, float>
     */
    private function decodeDescriptor(mixed $value, string $field): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'Descriptor wajah tidak valid.']);
        }

        $descriptor = [];

        foreach ($decoded as $entry) {
            if (! is_numeric($entry)) {
                throw ValidationException::withMessages([$field => 'Descriptor wajah berisi nilai yang tidak valid.']);
            }

            $descriptor[] = round((float) $entry, 6);
        }

        if (count($descriptor) < 64 || count($descriptor) > 1024) {
            throw ValidationException::withMessages([$field => 'Descriptor wajah tidak lengkap.']);
        }

        return $descriptor;
    }

    /**
     * @return array<int, string>
     */
    private function decodeImageList(string $value): array
    {
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['txtFaceEnrollmentImages' => 'Data gambar wajah tidak valid.']);
        }

        $images = collect($decoded)
            ->filter(fn ($image) => is_string($image) && str_starts_with($image, 'data:image/'))
            ->values()
            ->all();

        if (count($images) === 0 || count($images) > 5) {
            throw ValidationException::withMessages(['txtFaceEnrollmentImages' => 'Jumlah sampel wajah tidak valid.']);
        }

        return $images;
    }

    private function currentIntern(): ?MIntern
    {
        $userId = session('auth_user_id');

        if ($userId) {
            $user = MUser::with('intern.user.faceEnrollment')->find($userId);

            if ($user?->intern) {
                return $user->intern;
            }
        }

        return MIntern::with('user.faceEnrollment')->orderBy('intIntern_ID')->first();
    }

    private function currentUser(): ?MUser
    {
        $userId = session('auth_user_id');

        if (! $userId) {
            return null;
        }

        return MUser::with(['intern', 'mentor', 'adminProfile', 'faceEnrollment'])->find($userId);
    }
}
