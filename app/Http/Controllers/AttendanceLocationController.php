<?php

namespace App\Http\Controllers;

use App\Models\MAttendanceLocation;
use App\Models\MUser;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceLocationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $this->attendanceAdmin($request);

        DB::transaction(function () use ($request, $user): void {
            $location = MAttendanceLocation::query()
                ->orderByDesc('bitActive')
                ->orderByDesc('dtmUpdated')
                ->orderBy('intAttendanceLocation_ID')
                ->lockForUpdate()
                ->first();

            $payload = [
                ...$this->validated($request, $location),
                'bitActive' => $request->boolean('bitActive'),
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ];

            if ($location) {
                $location->update($payload);
            } else {
                $location = MAttendanceLocation::create([
                    ...$payload,
                    'txtInsertedBy' => $user->txtEmail,
                    'dtmInserted' => now(),
                ]);
            }

            $this->deactivateOtherLocations($location, $user);
        });

        return redirect()->route('attendance.index', ['tab' => 'settings'])->with('success', 'Pengaturan lokasi absensi berhasil disimpan.');
    }

    public function update(Request $request, MAttendanceLocation $attendanceLocation): RedirectResponse
    {
        $user = $this->attendanceAdmin($request);

        DB::transaction(function () use ($request, $user, $attendanceLocation): void {
            $attendanceLocation->update([
                ...$this->validated($request, $attendanceLocation),
                'bitActive' => $request->boolean('bitActive'),
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);

            $this->deactivateOtherLocations($attendanceLocation, $user);
        });

        return redirect()->route('attendance.index', ['tab' => 'settings'])->with('success', 'Pengaturan lokasi absensi berhasil disimpan.');
    }

    public function destroy(Request $request, MAttendanceLocation $attendanceLocation): RedirectResponse
    {
        $user = $this->attendanceAdmin($request);
        $attendanceLocation->update([
            'bitActive' => false,
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return redirect()->route('attendance.index', ['tab' => 'settings'])->with('success', 'Lokasi absensi dinonaktifkan tanpa menghapus histori.');
    }

    private function attendanceAdmin(Request $request): MUser
    {
        $user = MUser::with(['intern', 'mentor'])->findOrFail($request->session()->get('auth_user_id'));

        abort_unless(RoleAccess::isAttendanceAdmin($user), 403);

        return $user;
    }

    private function validated(Request $request, ?MAttendanceLocation $location = null): array
    {
        return $request->validate([
            'txtAttendanceLocationCode' => [
                'required', 'string', 'max:30',
                Rule::unique('mAttendanceLocation', 'txtAttendanceLocationCode')
                    ->ignore($location?->intAttendanceLocation_ID, 'intAttendanceLocation_ID'),
            ],
            'txtAttendanceLocationName' => ['required', 'string', 'max:150'],
            'txtAttendanceLocationAddress' => ['required', 'string', 'max:1000'],
            'floatAttendanceLocationLatitude' => ['required', 'numeric', 'between:-90,90'],
            'floatAttendanceLocationLongitude' => ['required', 'numeric', 'between:-180,180'],
            'intAttendanceLocationRadiusMeter' => ['required', 'integer', 'min:10', 'max:10000'],
            'intAttendanceLocationToleranceMeter' => ['required', 'integer', 'min:0', 'max:10000'],
            'intAttendanceLocationMaximumAccuracyMeter' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
    }

    private function deactivateOtherLocations(MAttendanceLocation $location, MUser $user): void
    {
        MAttendanceLocation::query()
            ->where('intAttendanceLocation_ID', '!=', $location->intAttendanceLocation_ID)
            ->where('bitActive', true)
            ->update([
                'bitActive' => false,
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);
    }
}
