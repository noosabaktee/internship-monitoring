<?php

namespace App\Http\Controllers;

use App\Models\MAttendanceSetting;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Services\FaceRecognitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AttendanceController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';
    private const MIN_START_TIME = '06:00';
    private const FACE_ALGORITHM = 'insightface-buffalo_l-v1';

    public function __construct(private readonly FaceRecognitionService $faceRecognition)
    {
    }

    public function index(Request $request): View
    {
        $authUser = $this->currentUser($request);
        $isMentor = $authUser->txtRole === 'Mentor';
        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $isWorkday = $this->isWorkday($now);
        [$windowStart, $windowEnd] = $this->todayWindow($setting, $now);
        $windowState = $isWorkday ? $this->windowState($now, $windowStart, $windowEnd) : 'offday';
        $enrollment = $isMentor
            ? null
            : $authUser->faceEnrollment()
                ->where('bitActive', true)
                ->first();
        $todayAttendance = $isMentor
            ? null
            : TrAttendance::where('intUser_ID', $authUser->intUser_ID)
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->first();
        $summaryRows = $isMentor ? [] : $this->summaryRows($authUser, $setting, $now);
        $teamTodayRows = collect();

        if ($isMentor) {
            $teamUsers = MUser::with(['intern', 'faceEnrollment'])
                ->where('bitActive', true)
                ->whereHas('intern', fn ($query) => $query->where('bitActive', true))
                ->get()
                ->sortBy(fn (MUser $user) => $this->displayName($user))
                ->values();
            $todayAttendances = TrAttendance::with(['user.intern', 'user.mentor'])
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->get()
                ->keyBy('intUser_ID');

            $teamTodayRows = $this->teamTodayRows($teamUsers, $todayAttendances, $setting, $now);
        }

        return view('dashboard.attendance', [
            'authUser' => $authUser,
            'displayName' => $this->displayName($authUser),
            'setting' => $setting,
            'enrollment' => $enrollment,
            'todayAttendance' => $todayAttendance,
            'summaryRows' => $summaryRows,
            'presentCount' => collect($summaryRows)->where('status', 'Hadir')->count(),
            'absentCount' => collect($summaryRows)->where('status', 'Tidak Masuk')->count(),
            'pendingCount' => collect($summaryRows)->where('status', 'Belum Absensi')->count(),
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
            'windowState' => $windowState,
            'isWorkday' => $isWorkday,
            'isMentor' => $isMentor,
            'teamTodayRows' => $teamTodayRows,
        ]);
    }

    public function detectFace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'txtFaceDetectionImage' => ['required', 'string'],
        ]);

        try {
            $facePayload = $this->faceRecognition->detect($validated['txtFaceDetectionImage']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'detected' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'detected' => true,
            'quality' => round((float) ($facePayload['quality'] ?? 0), 4),
            'algorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $authUser = $this->currentUser($request);

        if ($authUser->txtRole === 'Mentor' || ! $authUser->intern) {
            return back()->withErrors(['attendance' => 'Absensi hanya untuk intern.']);
        }

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        [$windowStart, $windowEnd] = $this->todayWindow($setting, $now);

        if (! $this->isWorkday($now)) {
            return back()->withErrors([
                'attendance' => 'Absensi hanya tersedia pada hari kerja Senin-Jumat.',
            ]);
        }

        if ($now->lt($windowStart)) {
            return back()->withErrors([
                'attendance' => 'Absensi baru bisa dilakukan mulai ' . $windowStart->format('H:i') . ' WIB.',
            ]);
        }

        if ($now->gt($windowEnd)) {
            return back()->withErrors([
                'attendance' => 'Batas absensi hari ini sudah lewat pada ' . $windowEnd->format('H:i') . ' WIB.',
            ]);
        }

        $enrollment = $authUser->faceEnrollment()
            ->where('bitActive', true)
            ->first();

        if (! $enrollment) {
            return back()->withErrors(['attendance' => 'Daftarkan wajah terlebih dahulu sebelum absen.']);
        }

        $alreadyCheckedIn = TrAttendance::where('intUser_ID', $authUser->intUser_ID)
            ->whereDate('dtmAttendanceDate', $now->toDateString())
            ->exists();

        if ($alreadyCheckedIn) {
            return back()->withErrors(['attendance' => 'Absensi hari ini sudah tercatat.']);
        }

        $validated = $request->validate([
            'txtAttendanceCapturedImage' => ['required', 'string'],
            'floatAttendanceLatitude' => ['required', 'numeric', 'between:-90,90'],
            'floatAttendanceLongitude' => ['required', 'numeric', 'between:-180,180'],
            'floatAttendanceLocationAccuracy' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'txtAttendanceDevice' => ['nullable', 'string', 'max:500'],
        ]);
        $enrolledDescriptor = $this->decodeDescriptor($enrollment->txtFaceEnrollmentDescriptor, 'txtFaceEnrollmentDescriptor');

        if (($enrollment->txtFaceEnrollmentAlgorithm !== self::FACE_ALGORITHM) || count($enrolledDescriptor) !== 512) {
            return back()->withErrors([
                'attendance' => 'Face ID lama perlu diperbarui untuk mode Python face recognition.',
            ]);
        }

        $threshold = (float) ($setting->floatAttendanceSettingFaceThreshold ?: 0.38);

        try {
            $facePayload = $this->faceRecognition->verify(
                $validated['txtAttendanceCapturedImage'],
                $enrolledDescriptor,
                $threshold,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        $faceDistance = (float) ($facePayload['distance'] ?? 1.0);

        if (! (bool) ($facePayload['match'] ?? false)) {
            return back()->withErrors([
                'attendance' => 'Wajah tidak cocok dengan Face ID terdaftar. Coba ulang dengan pencahayaan yang lebih stabil.',
            ]);
        }

        $latitude = round((float) $validated['floatAttendanceLatitude'], 7);
        $longitude = round((float) $validated['floatAttendanceLongitude'], 7);
        $locationLabel = $this->coordinateLabel($latitude, $longitude, $validated['floatAttendanceLocationAccuracy'] ?? null);

        $attendanceData = [
            'intUser_ID' => $authUser->intUser_ID,
            'dtmAttendanceDate' => $now->toDateString(),
            'dtmAttendanceClockIn' => $now,
            'txtAttendanceStatus' => 'Hadir',
            'floatAttendanceLatitude' => $latitude,
            'floatAttendanceLongitude' => $longitude,
            'floatAttendanceLocationAccuracy' => isset($validated['floatAttendanceLocationAccuracy'])
                ? round((float) $validated['floatAttendanceLocationAccuracy'], 2)
                : null,
            'txtAttendanceAddress' => $locationLabel,
            'txtAttendanceLocationUrl' => 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude,
            'floatAttendanceFaceDistance' => round($faceDistance, 4),
            'txtAttendanceFaceAlgorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
            'txtAttendanceDevice' => Str::limit($validated['txtAttendanceDevice'] ?? $request->userAgent() ?? 'Browser', 255, ''),
            'txtInsertedBy' => $authUser->txtEmail ?? 'system',
            'dtmInserted' => $now,
        ];

        if (Schema::hasColumn('trAttendance', 'intIntern_ID')) {
            $attendanceData['intIntern_ID'] = $authUser->intern?->intIntern_ID;
        }

        if (Schema::hasColumn('trAttendance', 'dtmCheckIn')) {
            $attendanceData['dtmCheckIn'] = $now;
        }

        if (Schema::hasColumn('trAttendance', 'txtFaceDescriptorMatch')) {
            $attendanceData['txtFaceDescriptorMatch'] = [
                'distance' => round($faceDistance, 4),
                'threshold' => $threshold,
                'similarity' => $facePayload['similarity'] ?? null,
                'quality' => $facePayload['quality'] ?? null,
                'algorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
            ];
        }

        if (Schema::hasColumn('trAttendance', 'floatLatitude')) {
            $attendanceData['floatLatitude'] = $latitude;
        }

        if (Schema::hasColumn('trAttendance', 'floatLongitude')) {
            $attendanceData['floatLongitude'] = $longitude;
        }

        if (Schema::hasColumn('trAttendance', 'txtLocationName')) {
            $attendanceData['txtLocationName'] = $locationLabel;
        }

        if (Schema::hasColumn('trAttendance', 'txtStatus')) {
            $attendanceData['txtStatus'] = 'Present';
        }

        if (Schema::hasColumn('trAttendance', 'txtNotes')) {
            $attendanceData['txtNotes'] = 'Face ID attendance';
        }

        TrAttendance::create($attendanceData);

        return redirect()->route('attendance.index')->with('success', 'Absensi berhasil dicatat.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $authUser = $this->currentUser($request);
        $validated = $request->validate([
            'txtAttendanceSettingStartTime' => ['required', 'date_format:H:i'],
            'txtAttendanceSettingEndTime' => ['required', 'date_format:H:i'],
            'floatAttendanceSettingFaceThreshold' => ['required', 'numeric', 'min:0.1', 'max:1.5'],
        ]);

        if ($validated['txtAttendanceSettingStartTime'] < self::MIN_START_TIME) {
            return back()->withErrors([
                'txtAttendanceSettingStartTime' => 'Jam mulai absensi minimal pukul 06:00 WIB.',
            ]);
        }

        if ($validated['txtAttendanceSettingEndTime'] <= $validated['txtAttendanceSettingStartTime']) {
            return back()->withErrors([
                'txtAttendanceSettingEndTime' => 'Jam terakhir absensi harus lebih besar dari jam mulai.',
            ]);
        }

        $this->attendanceSetting()->update([
            'txtAttendanceSettingStartTime' => $validated['txtAttendanceSettingStartTime'],
            'txtAttendanceSettingEndTime' => $validated['txtAttendanceSettingEndTime'],
            'floatAttendanceSettingFaceThreshold' => round((float) $validated['floatAttendanceSettingFaceThreshold'], 2),
            'bitAttendanceSettingLocationRequired' => true,
            'bitActive' => true,
            'txtUpdatedBy' => $authUser->txtEmail ?? 'system',
            'dtmUpdated' => Carbon::now(self::TIMEZONE),
        ]);

        return redirect()->route('attendance.index')->with('success', 'Setting absensi berhasil diperbarui.');
    }

    private function currentUser(Request $request): MUser
    {
        return MUser::with(['intern', 'mentor'])->findOrFail($request->session()->get('auth_user_id'));
    }

    private function attendanceSetting(): MAttendanceSetting
    {
        return MAttendanceSetting::firstOrCreate(
            ['intAttendanceSetting_ID' => 1],
            [
                'txtAttendanceSettingStartTime' => self::MIN_START_TIME,
                'txtAttendanceSettingEndTime' => '23:59',
                'floatAttendanceSettingFaceThreshold' => 0.38,
                'bitAttendanceSettingLocationRequired' => true,
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => Carbon::now(self::TIMEZONE),
            ],
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayWindow(MAttendanceSetting $setting, Carbon $now): array
    {
        $startTime = $setting->txtAttendanceSettingStartTime ?: self::MIN_START_TIME;
        $endTime = $setting->txtAttendanceSettingEndTime ?: '23:59';

        return [
            Carbon::parse($now->toDateString() . ' ' . $startTime, self::TIMEZONE),
            Carbon::parse($now->toDateString() . ' ' . $endTime, self::TIMEZONE),
        ];
    }

    private function windowState(Carbon $now, Carbon $windowStart, Carbon $windowEnd): string
    {
        if ($now->lt($windowStart)) {
            return 'before';
        }

        if ($now->gt($windowEnd)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summaryRows(MUser $user, MAttendanceSetting $setting, Carbon $now): array
    {
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();
        $records = TrAttendance::where('intUser_ID', $user->intUser_ID)
            ->whereDate('dtmAttendanceDate', '>=', $weekStart->toDateString())
            ->whereDate('dtmAttendanceDate', '<=', $weekEnd->toDateString())
            ->orderBy('dtmAttendanceDate')
            ->get()
            ->keyBy(fn (TrAttendance $attendance) => $attendance->dtmAttendanceDate?->format('Y-m-d'));
        [, $windowEnd] = $this->todayWindow($setting, $now);

        return collect(range(0, 4))
            ->map(function (int $offset) use ($records, $now, $weekStart, $windowEnd) {
                $date = $weekStart->copy()->addDays($offset);
                $dateKey = $date->format('Y-m-d');
                $attendance = $records->get($dateKey);
                $isToday = $date->isSameDay($now);
                $isFuture = $date->gt($now->copy()->startOfDay());

                if ($attendance) {
                    return [
                        'date' => $date,
                        'status' => $attendance->txtAttendanceStatus ?: 'Hadir',
                        'clock' => $attendance->dtmAttendanceClockIn?->format('H:i') ?? '-',
                        'location' => $attendance->txtAttendanceAddress ?: '-',
                        'locationUrl' => $attendance->txtAttendanceLocationUrl,
                        'faceDistance' => $attendance->floatAttendanceFaceDistance,
                    ];
                }

                $status = ($isFuture || ($isToday && ! $now->gt($windowEnd))) ? 'Belum Absensi' : 'Tidak Masuk';

                return [
                    'date' => $date,
                    'status' => $status,
                    'clock' => '-',
                    'location' => '-',
                    'locationUrl' => null,
                    'faceDistance' => null,
                ];
            })
            ->all();
    }

    /**
     * @param Collection<int, MUser> $teamUsers
     * @param Collection<int, TrAttendance> $todayAttendances
     * @return Collection<int, array<string, mixed>>
     */
    private function teamTodayRows(Collection $teamUsers, Collection $todayAttendances, MAttendanceSetting $setting, Carbon $now): Collection
    {
        [, $windowEnd] = $this->todayWindow($setting, $now);
        $isWorkday = $this->isWorkday($now);

        return $teamUsers
            ->map(function (MUser $user) use ($todayAttendances, $now, $windowEnd, $isWorkday) {
                $attendance = $todayAttendances->get($user->intUser_ID);
                $status = match (true) {
                    (bool) $attendance => 'Hadir',
                    ! $isWorkday => 'Tidak Ada Absensi',
                    $now->gt($windowEnd) => 'Tidak Masuk',
                    default => 'Belum Absensi',
                };

                return [
                    'name' => $this->displayName($user),
                    'role' => 'Intern',
                    'faceRegistered' => (bool) $user->faceEnrollment?->bitActive,
                    'status' => $status,
                    'clock' => $attendance?->dtmAttendanceClockIn?->format('H:i') ?? '-',
                    'location' => $attendance?->txtAttendanceAddress ?: '-',
                    'locationUrl' => $attendance?->txtAttendanceLocationUrl,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    private function isWorkday(Carbon $date): bool
    {
        return $date->isWeekday();
    }

    private function displayName(MUser $user): string
    {
        return $user->intern?->txtInternName
            ?? $user->mentor?->txtMentorName
            ?? $user->txtEmail
            ?? 'User';
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

    private function coordinateLabel(float $latitude, float $longitude, mixed $accuracy): string
    {
        $label = 'Lat ' . number_format($latitude, 6) . ', Lng ' . number_format($longitude, 6);

        if (is_numeric($accuracy)) {
            $label .= ' +/- ' . number_format((float) $accuracy, 0) . ' m';
        }

        return $label;
    }
}
