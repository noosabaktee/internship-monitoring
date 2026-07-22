<?php

namespace App\Http\Controllers\Api;

use App\Models\MFaceEnrollment;
use App\Services\FaceRecognitionService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileController extends ApiController
{
    private const FACE_ALGORITHM = 'insightface-buffalo_l-v1';

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->person($this->user($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'university' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);
        $now = now();

        if ($user->intern) {
            $user->intern->update([
                ...array_key_exists('name', $validated) ? ['txtInternName' => $validated['name']] : [],
                ...array_key_exists('gender', $validated) ? ['txtInternGender' => $validated['gender']] : [],
                ...array_key_exists('university', $validated) ? ['txtUniversity' => $validated['university']] : [],
                ...array_key_exists('department', $validated) ? ['txtDept' => $validated['department']] : [],
                ...array_key_exists('bio', $validated) ? ['txtBio' => $validated['bio']] : [],
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => $now,
            ]);
        } elseif ($user->mentor) {
            $user->mentor->update([
                ...array_key_exists('name', $validated) ? ['txtMentorName' => $validated['name']] : [],
                ...array_key_exists('gender', $validated) ? ['txtMentorGender' => $validated['gender']] : [],
                ...array_key_exists('department', $validated) ? ['txtDepartment' => $validated['department']] : [],
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => $now,
            ]);
        } elseif ($user->adminProfile) {
            $user->adminProfile->update([
                ...array_key_exists('name', $validated) ? ['txtAdminProfileName' => $validated['name']] : [],
                ...array_key_exists('gender', $validated) ? ['txtAdminProfileGender' => $validated['gender']] : [],
                ...array_key_exists('department', $validated) ? ['txtAdminProfileDepartment' => $validated['department']] : [],
                ...array_key_exists('bio', $validated) ? ['txtAdminProfileBio' => $validated['bio']] : [],
                ...array_key_exists('phone', $validated) ? ['txtAdminProfilePhone' => $validated['phone']] : [],
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => $now,
            ]);
        }

        return $this->success($this->person($user->fresh()), 'Profil berhasil diperbarui.');
    }

    public function photo(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->txtProfilePhoto) {
            Storage::disk('public')->delete($user->txtProfilePhoto);
        }

        $path = $validated['photo']->store('profile-photos', 'public');
        $user->update(['txtProfilePhoto' => $path, 'txtUpdatedBy' => $user->txtEmail, 'dtmUpdated' => now()]);

        return $this->success(['path' => $path, 'url' => Storage::disk('public')->url($path)], 'Foto profil berhasil diperbarui.');
    }

    public function faceEnrollment(Request $request, FaceRecognitionService $faceRecognition): JsonResponse
    {
        $user = $this->user($request);

        if (! RoleAccess::isIntern($user)) {
            abort(403, 'Face ID hanya dapat didaftarkan oleh intern.');
        }

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'string'],
        ]);

        try {
            $payload = $faceRecognition->enroll($validated['images']);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $descriptor = $payload['embedding'] ?? null;
        if (! is_array($descriptor) || count($descriptor) < 64) {
            abort(422, 'Face service mengembalikan descriptor yang tidak valid.');
        }

        $now = Carbon::now('Asia/Jakarta');
        $enrollment = MFaceEnrollment::updateOrCreate(
            ['intUser_ID' => $user->intUser_ID],
            [
                'txtFaceEnrollmentDescriptor' => array_map('floatval', $descriptor),
                'txtFaceEnrollmentAlgorithm' => $payload['algorithm'] ?? self::FACE_ALGORITHM,
                'intFaceEnrollmentSampleCount' => (int) ($payload['sample_count'] ?? count($validated['images'])),
                'floatFaceEnrollmentQuality' => round((float) ($payload['quality'] ?? 0), 4),
                'dtmFaceEnrollmentRegistered' => $now,
                'bitActive' => true,
                'txtInsertedBy' => $user->txtEmail,
                'dtmInserted' => $now,
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => $now,
            ],
        );

        return $this->success([
            'registered' => true,
            'algorithm' => $enrollment->txtFaceEnrollmentAlgorithm,
            'sample_count' => $enrollment->intFaceEnrollmentSampleCount,
            'quality' => $enrollment->floatFaceEnrollmentQuality,
            'registered_at' => $enrollment->dtmFaceEnrollmentRegistered?->toISOString(),
        ], 'Face ID berhasil didaftarkan.');
    }

    public function removeFaceEnrollment(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Face ID hanya dapat dihapus oleh intern.');
        $user->faceEnrollment()->update(['bitActive' => false, 'txtUpdatedBy' => $user->txtEmail, 'dtmUpdated' => now()]);

        return $this->success(['registered' => false], 'Face ID berhasil dihapus.');
    }
}
