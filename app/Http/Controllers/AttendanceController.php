<?php

namespace App\Http\Controllers;

use App\Models\MAttendanceLocation;
use App\Models\MAttendanceSetting;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrSalarySlip;
use App\Models\TrWorkFromHomeRequest;
use App\Services\FaceRecognitionService;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class AttendanceController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const DEFAULT_CLOCK_IN_START = '06:30';

    private const DEFAULT_CLOCK_IN_END = '09:00';

    private const DEFAULT_CLOCK_OUT_START = '16:00';

    private const DEFAULT_CLOCK_OUT_END = '18:30';

    private const FACE_ALGORITHM = 'insightface-buffalo_l-v1';

    public function __construct(private readonly FaceRecognitionService $faceRecognition) {}

    public function index(Request $request): View
    {
        $authUser = $this->currentUser($request);
        $isAttendanceAdmin = RoleAccess::isAttendanceAdmin($authUser);
        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $isWorkday = $this->isWorkday($now);
        $windows = $this->attendanceWindows($setting, $now);
        $windowState = $isWorkday ? $this->attendanceWindowState($now, $windows) : 'offday';
        $internshipCompleted = ! $isAttendanceAdmin && $authUser->intern?->hasCompletedInternship($now);
        $workContext = $isAttendanceAdmin ? null : $this->workContext($authUser, $now);
        $enrollment = $isAttendanceAdmin
            ? null
            : $authUser->faceEnrollment()
                ->where('bitActive', true)
                ->first();
        $todayAttendance = $isAttendanceAdmin
            ? null
            : TrAttendance::where('intUser_ID', $authUser->intUser_ID)
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->first();
        $todayClockInAt = $todayAttendance ? $this->attendanceClockInAt($todayAttendance) : null;
        $todayClockOutAt = $todayAttendance ? $this->attendanceClockOutAt($todayAttendance) : null;
        $summaryRows = $isAttendanceAdmin ? [] : $this->summaryRows($authUser, $setting, $now);
        $summary = collect($summaryRows);
        $teamTodayRows = collect();
        $attendanceDetail = [
            'filters' => [],
            'interns' => collect(),
            'rows' => collect(),
            'summary' => [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'pending' => 0,
                'clockOutWarnings' => 0,
            ],
            'selectedIntern' => null,
            'payroll' => null,
        ];

        if ($isAttendanceAdmin) {
            $teamUsers = $this->attendanceInternUsers();
            $detailUsers = $this->attendanceDetailInternUsers();
            $todayAttendances = TrAttendance::with(['user.intern', 'user.mentor'])
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->get()
                ->keyBy('intUser_ID');

            $teamTodayRows = $this->teamTodayRows($teamUsers, $todayAttendances, $setting, $now);
            $attendanceDetail = $this->adminAttendanceDetail($request, $detailUsers, $setting, $now);
        }

        return view('dashboard.attendance', [
            'authUser' => $authUser,
            'displayName' => $this->displayName($authUser),
            'setting' => $setting,
            'enrollment' => $enrollment,
            'todayAttendance' => $todayAttendance,
            'todayClockInAt' => $todayClockInAt,
            'todayClockOutAt' => $todayClockOutAt,
            'todayClockInStatus' => $todayAttendance ? $this->attendanceClockInStatus($todayAttendance, $windows) : null,
            'summaryRows' => $summaryRows,
            'presentCount' => $summary->where('status', 'Hadir')->count(),
            'lateCount' => $summary->where('status', 'Terlambat')->count(),
            'absentCount' => $summary->where('status', 'Tidak Masuk')->count(),
            'pendingCount' => $summary->where('status', 'Belum Clock In')->count(),
            'clockInStart' => $windows['clockInStart'],
            'clockInEnd' => $windows['clockInEnd'],
            'clockInLateEnd' => $windows['clockInLateEnd'],
            'clockOutStart' => $windows['clockOutStart'],
            'clockOutEnd' => $windows['clockOutEnd'],
            'clockOutLateEnd' => $windows['clockOutLateEnd'],
            'windowState' => $windowState,
            'isWorkday' => $isWorkday,
            'isAttendanceAdmin' => $isAttendanceAdmin,
            'teamTodayRows' => $teamTodayRows,
            'attendanceDetailFilters' => $attendanceDetail['filters'],
            'attendanceDetailInterns' => $attendanceDetail['interns'],
            'salarySlipInterns' => $isAttendanceAdmin
                ? $this->attendancePeriodInternUsers(
                    Carbon::parse($attendanceDetail['filters']['from'], self::TIMEZONE),
                    Carbon::parse($attendanceDetail['filters']['to'], self::TIMEZONE),
                )
                : collect(),
            'attendanceDetailRows' => $attendanceDetail['rows'],
            'attendanceDetailSummary' => $attendanceDetail['summary'],
            'attendanceSelectedIntern' => $attendanceDetail['selectedIntern'],
            'attendancePayrollSummary' => $attendanceDetail['payroll'],
            'internshipCompleted' => (bool) $internshipCompleted,
            'internshipEndDate' => $authUser->intern?->effectiveEndDate(),
            'todayWorkMode' => $workContext['mode'] ?? 'Office',
            'attendanceLocation' => $isAttendanceAdmin
                ? MAttendanceLocation::query()
                    ->orderByDesc('bitActive')
                    ->orderByDesc('dtmUpdated')
                    ->orderBy('intAttendanceLocation_ID')
                    ->first()
                : null,
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

    public function apiIndex(Request $request): JsonResponse
    {
        $authUser = $this->currentUser($request);
        $isAttendanceAdmin = RoleAccess::isAttendanceAdmin($authUser);
        $query = TrAttendance::with(['attendanceLocation', 'workFromHomeRequest', 'user.intern'])
            ->when(! $isAttendanceAdmin, fn ($query) => $query->where('intUser_ID', $authUser->intUser_ID))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('dtmAttendanceDate', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('dtmAttendanceDate', '<=', $request->query('to')))
            ->when(
                $request->filled('intern_id') && $isAttendanceAdmin,
                fn ($query) => $query->whereHas(
                    'user.intern',
                    fn ($query) => $query->where('intIntern_ID', $request->integer('intern_id'))
                )
            )
            ->orderByDesc('dtmAttendanceDate')
            ->orderByDesc('dtmAttendanceClockIn');
        $paginator = $query->paginate(min(100, max(1, $request->integer('per_page', 20))));
        $now = Carbon::now(self::TIMEZONE);
        $setting = $this->attendanceSetting();
        $today = $isAttendanceAdmin ? null : TrAttendance::where('intUser_ID', $authUser->intUser_ID)->whereDate('dtmAttendanceDate', $now->toDateString())->first();
        $teamTodayRecords = collect();
        $internGroups = null;

        if ($isAttendanceAdmin) {
            $teamUsers = $this->attendanceInternUsers();
            $todayAttendances = TrAttendance::with(['attendanceLocation', 'workFromHomeRequest', 'user.intern'])
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->get()
                ->keyBy('intUser_ID');
            $teamTodayRecords = $this->apiTeamTodayRecords($teamUsers, $todayAttendances, $setting, $now);

            if ($request->filled('from') && $request->filled('to')) {
                $detail = $this->adminAttendanceDetail($request, $this->attendanceDetailInternUsers(), $setting, $now);
                $internGroups = $this->apiAdminInternGroups($detail['rows']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data absensi berhasil diambil.',
            'data' => [
                'today' => $today ? $this->apiAttendanceRecord($today) : null,
                'today_records' => $isAttendanceAdmin ? $teamTodayRecords->values() : null,
                'intern_groups' => $isAttendanceAdmin ? $internGroups : null,
                'today_summary' => $isAttendanceAdmin ? [
                    'total' => $teamTodayRecords->count(),
                    'clocked_in' => $teamTodayRecords->whereNotNull('clock_in')->count(),
                    'completed' => $teamTodayRecords->whereNotNull('clock_out')->count(),
                    'not_checked_in' => $teamTodayRecords->whereNull('clock_in')->count(),
                ] : null,
                'face_registered' => $isAttendanceAdmin ? null : (bool) $authUser->faceEnrollment()->where('bitActive', true)->exists(),
                'work_mode' => $isAttendanceAdmin ? null : $this->workContext($authUser, $now)['mode'],
                'settings' => [
                    'clock_in_start' => $setting->txtAttendanceSettingClockInStartTime,
                    'clock_in_end' => $setting->txtAttendanceSettingClockInEndTime,
                    'clock_out_start' => $setting->txtAttendanceSettingClockOutStartTime,
                    'clock_out_end' => $setting->txtAttendanceSettingClockOutEndTime,
                    'face_threshold' => $isAttendanceAdmin ? $setting->floatAttendanceSettingFaceThreshold : null,
                    'location_required' => (bool) $setting->bitAttendanceSettingLocationRequired,
                    'timezone' => self::TIMEZONE,
                ],
                'records' => collect($paginator->items())->map(fn ($attendance) => $this->apiAttendanceRecord($attendance))->values(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function checkIn(Request $request): JsonResponse|Response|RedirectResponse
    {
        $authUser = $this->currentUser($request);
        $this->ensureInternCanAttend($authUser);

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $windows = $this->attendanceWindows($setting, $now);
        $workContext = $this->workContext($authUser, $now);

        if (! $this->isWorkday($now)) {
            return $this->attendanceActionError($request, 'Clock In hanya tersedia pada hari kerja Senin-Jumat.');
        }

        if ($now->lt($windows['clockInStart'])) {
            return $this->attendanceActionError($request, 'Clock In baru bisa dilakukan mulai '.$windows['clockInStart']->format('H:i').' WIB.');
        }

        $alreadyCheckedIn = TrAttendance::where('intUser_ID', $authUser->intUser_ID)
            ->whereDate('dtmAttendanceDate', $now->toDateString())
            ->whereNotNull('dtmAttendanceClockIn')
            ->exists();

        if ($alreadyCheckedIn) {
            return $this->attendanceActionError($request, 'Clock In hari ini sudah tercatat.');
        }

        $verification = $this->verifiedFaceAndLocation($request, $authUser, $setting, $workContext);
        $clockInStatus = $now->gt($windows['clockInEnd']) ? 'Terlambat' : 'Tepat Waktu';
        $shouldAutoClockOut = $now->gt($windows['clockOutEnd']);
        $clockOutStatus = $shouldAutoClockOut ? 'Terlambat' : null;
        $status = $clockInStatus === 'Terlambat' ? 'Terlambat' : 'Hadir';

        $attendanceData = [
            'intUser_ID' => $authUser->intUser_ID,
            'intAttendanceLocation_ID' => $verification['locationId'],
            'intWorkFromHomeRequest_ID' => $workContext['request']?->intWorkFromHomeRequest_ID,
            'txtAttendanceWorkMode' => $workContext['mode'],
            'dtmAttendanceDate' => $now->toDateString(),
            'dtmAttendanceClockIn' => $now,
            'txtAttendanceStatus' => $status,
            'floatAttendanceLatitude' => $verification['latitude'],
            'floatAttendanceLongitude' => $verification['longitude'],
            'floatAttendanceLocationAccuracy' => $verification['accuracy'],
            'floatAttendanceDistanceMeter' => $verification['distanceMeter'],
            'floatAttendanceAllowedDistanceMeter' => $verification['allowedDistanceMeter'],
            'bitAttendanceWithinTolerance' => $verification['withinTolerance'],
            'txtAttendanceAddress' => $verification['locationLabel'],
            'txtAttendanceLocationUrl' => $verification['locationUrl'],
            'txtAttendanceClockInStatus' => $clockInStatus,
            'floatAttendanceFaceDistance' => $verification['faceDistance'],
            'txtAttendanceFaceAlgorithm' => $verification['algorithm'],
            'txtAttendanceDevice' => $verification['device'],
            'txtAttendanceNote' => match (true) {
                $shouldAutoClockOut => 'Clock In terlambat, Clock Out otomatis',
                $status === 'Terlambat' => 'Clock In terlambat',
                default => 'Clock In tepat waktu',
            },
            'txtInsertedBy' => $authUser->txtEmail ?? 'system',
            'dtmInserted' => $now,
        ];

        if ($shouldAutoClockOut) {
            $attendanceData = array_merge($attendanceData, [
                'dtmAttendanceClockOut' => $now,
                'floatAttendanceClockOutLatitude' => $verification['latitude'],
                'floatAttendanceClockOutLongitude' => $verification['longitude'],
                'floatAttendanceClockOutLocationAccuracy' => $verification['accuracy'],
                'floatAttendanceClockOutDistanceMeter' => $verification['distanceMeter'],
                'bitAttendanceClockOutWithinTolerance' => $verification['withinTolerance'],
                'txtAttendanceClockOutAddress' => $verification['locationLabel'],
                'txtAttendanceClockOutLocationUrl' => $verification['locationUrl'],
                'txtAttendanceClockOutStatus' => $clockOutStatus,
                'floatAttendanceClockOutFaceDistance' => $verification['faceDistance'],
                'txtAttendanceClockOutFaceAlgorithm' => $verification['algorithm'],
                'txtAttendanceClockOutDevice' => $verification['device'],
                'txtAttendanceClockOutNote' => 'Clock Out otomatis karena Clock In melewati batas Clock Out',
            ]);
        }

        if (Schema::hasColumn('trAttendance', 'intIntern_ID')) {
            $attendanceData['intIntern_ID'] = $authUser->intern?->intIntern_ID;
        }

        if (Schema::hasColumn('trAttendance', 'dtmCheckIn')) {
            $attendanceData['dtmCheckIn'] = $now;
        }

        if ($shouldAutoClockOut && Schema::hasColumn('trAttendance', 'dtmCheckOut')) {
            $attendanceData['dtmCheckOut'] = $now;
        }

        if (Schema::hasColumn('trAttendance', 'txtFaceDescriptorMatch')) {
            $attendanceData['txtFaceDescriptorMatch'] = $verification['faceMatchPayload'];
        }

        if (Schema::hasColumn('trAttendance', 'floatLatitude')) {
            $attendanceData['floatLatitude'] = $verification['latitude'];
        }

        if (Schema::hasColumn('trAttendance', 'floatLongitude')) {
            $attendanceData['floatLongitude'] = $verification['longitude'];
        }

        if (Schema::hasColumn('trAttendance', 'txtLocationName')) {
            $attendanceData['txtLocationName'] = $verification['locationLabel'];
        }

        if (Schema::hasColumn('trAttendance', 'txtStatus')) {
            $attendanceData['txtStatus'] = $status === 'Terlambat' ? 'Late' : 'Present';
        }

        if (Schema::hasColumn('trAttendance', 'txtNotes')) {
            $attendanceData['txtNotes'] = 'Face ID clock in';
        }

        $attendance = TrAttendance::create($attendanceData);

        $message = match (true) {
            $shouldAutoClockOut => 'Clock In terlambat berhasil dicatat dan Clock Out otomatis tersimpan.',
            $status === 'Terlambat' => 'Clock In berhasil dicatat dengan status Terlambat.',
            default => 'Clock In berhasil dicatat.',
        };

        return $this->attendanceActionSuccess($request, $attendance->fresh(['attendanceLocation', 'workFromHomeRequest']), $message);
    }

    public function checkOut(Request $request): JsonResponse|Response|RedirectResponse
    {
        $authUser = $this->currentUser($request);
        $this->ensureInternCanAttend($authUser);

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $windows = $this->attendanceWindows($setting, $now);

        if (! $this->isWorkday($now)) {
            return $this->attendanceActionError($request, 'Clock Out hanya tersedia pada hari kerja Senin-Jumat.');
        }

        $attendance = TrAttendance::where('intUser_ID', $authUser->intUser_ID)
            ->whereDate('dtmAttendanceDate', $now->toDateString())
            ->first();

        if (! $attendance?->dtmAttendanceClockIn) {
            return $this->attendanceActionError($request, 'Clock In terlebih dahulu sebelum Clock Out.');
        }

        if ($attendance->dtmAttendanceClockOut) {
            return $this->attendanceActionError($request, 'Clock Out hari ini sudah tercatat.');
        }

        if ($now->lt($windows['clockOutStart'])) {
            return $this->attendanceActionError($request, 'Clock Out baru bisa dilakukan mulai '.$windows['clockOutStart']->format('H:i').' WIB.');
        }

        $clockInAt = $this->attendanceClockInAt($attendance);

        if ($clockInAt?->gt($windows['clockOutEnd'])) {
            return $this->attendanceActionError($request, 'Clock In tercatat setelah batas Clock Out, jadi Clock Out hari ini tidak tersedia.');
        }

        if ($now->gt($windows['clockOutLateEnd'])) {
            return $this->attendanceActionError($request, 'Batas Clock Out terlambat hari ini sudah lewat pada 23:59 WIB.');
        }

        $workContext = [
            'mode' => $attendance->txtAttendanceWorkMode ?: 'Office',
            'request' => $attendance->workFromHomeRequest,
        ];
        $verification = $this->verifiedFaceAndLocation($request, $authUser, $setting, $workContext);
        $clockOutStatus = $now->gt($windows['clockOutEnd']) ? 'Terlambat' : 'Tepat Waktu';

        $attendanceData = [
            'dtmAttendanceClockOut' => $now,
            'floatAttendanceClockOutLatitude' => $verification['latitude'],
            'floatAttendanceClockOutLongitude' => $verification['longitude'],
            'floatAttendanceClockOutLocationAccuracy' => $verification['accuracy'],
            'floatAttendanceClockOutDistanceMeter' => $verification['distanceMeter'],
            'bitAttendanceClockOutWithinTolerance' => $verification['withinTolerance'],
            'txtAttendanceClockOutAddress' => $verification['locationLabel'],
            'txtAttendanceClockOutLocationUrl' => $verification['locationUrl'],
            'txtAttendanceClockOutStatus' => $clockOutStatus,
            'floatAttendanceClockOutFaceDistance' => $verification['faceDistance'],
            'txtAttendanceClockOutFaceAlgorithm' => $verification['algorithm'],
            'txtAttendanceClockOutDevice' => $verification['device'],
            'txtAttendanceClockOutNote' => $clockOutStatus === 'Terlambat' ? 'Clock Out terlambat' : 'Face ID clock out',
        ];

        if (Schema::hasColumn('trAttendance', 'dtmCheckOut')) {
            $attendanceData['dtmCheckOut'] = $now;
        }

        $attendance->update($attendanceData);

        $message = $clockOutStatus === 'Terlambat'
            ? 'Clock Out berhasil dicatat dengan status Terlambat.'
            : 'Clock Out berhasil dicatat.';

        return $this->attendanceActionSuccess($request, $attendance->fresh(['attendanceLocation', 'workFromHomeRequest']), $message);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $authUser = $this->currentUser($request);

        if (! RoleAccess::isAttendanceAdmin($authUser)) {
            abort(403, 'Setting absensi hanya untuk Headmaster atau HRD.');
        }

        $validated = $request->validate([
            'txtAttendanceSettingClockInStartTime' => ['required', 'date_format:H:i'],
            'txtAttendanceSettingClockInEndTime' => ['required', 'date_format:H:i'],
            'txtAttendanceSettingClockOutStartTime' => ['required', 'date_format:H:i'],
            'txtAttendanceSettingClockOutEndTime' => ['required', 'date_format:H:i'],
            'floatAttendanceSettingFaceThreshold' => ['required', 'numeric', 'min:0.1', 'max:1.5'],
        ]);

        if ($validated['txtAttendanceSettingClockInEndTime'] <= $validated['txtAttendanceSettingClockInStartTime']) {
            return back()->withErrors([
                'txtAttendanceSettingClockInEndTime' => 'Batas Clock In harus lebih besar dari mulai Clock In.',
            ]);
        }

        if ($validated['txtAttendanceSettingClockOutStartTime'] <= $validated['txtAttendanceSettingClockInEndTime']) {
            return back()->withErrors([
                'txtAttendanceSettingClockOutStartTime' => 'Mulai Clock Out harus lebih besar dari batas Clock In.',
            ]);
        }

        if ($validated['txtAttendanceSettingClockOutEndTime'] <= $validated['txtAttendanceSettingClockOutStartTime']) {
            return back()->withErrors([
                'txtAttendanceSettingClockOutEndTime' => 'Batas Clock Out harus lebih besar dari mulai Clock Out.',
            ]);
        }

        $this->attendanceSetting()->update([
            'txtAttendanceSettingStartTime' => $validated['txtAttendanceSettingClockInStartTime'],
            'txtAttendanceSettingEndTime' => $validated['txtAttendanceSettingClockOutEndTime'],
            'txtAttendanceSettingClockInStartTime' => $validated['txtAttendanceSettingClockInStartTime'],
            'txtAttendanceSettingClockInEndTime' => $validated['txtAttendanceSettingClockInEndTime'],
            'txtAttendanceSettingClockOutStartTime' => $validated['txtAttendanceSettingClockOutStartTime'],
            'txtAttendanceSettingClockOutEndTime' => $validated['txtAttendanceSettingClockOutEndTime'],
            'floatAttendanceSettingFaceThreshold' => round((float) $validated['floatAttendanceSettingFaceThreshold'], 2),
            'bitAttendanceSettingLocationRequired' => true,
            'bitActive' => true,
            'txtUpdatedBy' => $authUser->txtEmail ?? 'system',
            'dtmUpdated' => Carbon::now(self::TIMEZONE),
        ]);

        return redirect()->route('attendance.index', ['tab' => 'settings'])->with('success', 'Setting absensi berhasil diperbarui.');
    }

    public function exportExcel(Request $request): Response
    {
        $payload = $this->attendanceExportPayload($request);
        $filename = 'attendance-'.$payload['filters']['from'].'-'.$payload['filters']['to'].'.xlsx';
        $spreadsheet = $this->attendanceSpreadsheet($payload);
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attendanceSpreadsheet(array $payload): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        $dates = $payload['calendar'];
        $matrixRows = $payload['matrixRows'];
        $dateCount = max(1, $dates->count());
        $dateStartColumn = 2;
        $recapStartColumn = $dateStartColumn + $dateCount;
        $legendColumn = $recapStartColumn + 4;
        $matrixLastColumn = Coordinate::stringFromColumnIndex($recapStartColumn + 2);
        $lastColumn = Coordinate::stringFromColumnIndex($legendColumn + 1);

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->setCellValue('A1', 'Kalbe Internship Attendance Report');
        $sheet->setCellValue('A2', 'Period');
        $sheet->setCellValue('B2', $payload['filters']['from'].' to '.$payload['filters']['to']);
        $sheet->setCellValue('A3', 'Generated');
        $sheet->setCellValue('B3', $payload['generatedAt']->format('d M Y H:i').' WIB');
        $sheet->setCellValue('A4', 'Generated By');
        $sheet->setCellValue('B4', $payload['generatedBy']);

        $legendStart = Coordinate::stringFromColumnIndex($legendColumn);
        $legendLabelColumn = Coordinate::stringFromColumnIndex($legendColumn + 1);
        $sheet->mergeCells($legendStart.'5:'.$legendLabelColumn.'5');
        $sheet->setCellValue($legendStart.'5', 'Legend Kehadiran');
        $legendRow = 6;

        foreach ($payload['legend'] as $legend) {
            $sheet->setCellValue($legendStart.$legendRow, $legend['code']);
            $sheet->setCellValue($legendLabelColumn.$legendRow, $legend['label']);
            $sheet->getStyle($legendStart.$legendRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($legend['color']);
            $legendRow++;
        }

        $monthRow = 5;
        $dateRow = 6;
        $dataStartRow = 7;
        $sheet->setCellValue('A'.$monthRow, 'Bulan');
        $sheet->setCellValue('A'.$dateRow, 'Tanggal');

        $currentMonth = null;
        $monthStartIndex = $dateStartColumn;

        foreach ($dates as $offset => $date) {
            $columnIndex = $dateStartColumn + $offset;
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $monthLabel = $this->monthLabel($date);
            $sheet->setCellValue($column.$dateRow, (int) $date->format('j'));

            if ($currentMonth === null) {
                $currentMonth = $monthLabel;
                $monthStartIndex = $columnIndex;
            }

            $nextDate = $dates->get($offset + 1);
            $isMonthEnd = ! $nextDate || $this->monthLabel($nextDate) !== $currentMonth;

            if ($isMonthEnd) {
                $monthStart = Coordinate::stringFromColumnIndex($monthStartIndex);
                $monthEnd = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->mergeCells($monthStart.$monthRow.':'.$monthEnd.$monthRow);
                $sheet->setCellValue($monthStart.$monthRow, $currentMonth);
                $currentMonth = $nextDate ? $this->monthLabel($nextDate) : null;
                $monthStartIndex = $columnIndex + 1;
            }
        }

        $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn).$monthRow, "Masuk\n(WFH/WFO)");
        $sheet->mergeCells(Coordinate::stringFromColumnIndex($recapStartColumn).$monthRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn).$dateRow);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn + 1).$monthRow, "Tidak Masuk\n(Absen/Izin/Sakit)");
        $sheet->mergeCells(Coordinate::stringFromColumnIndex($recapStartColumn + 1).$monthRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn + 1).$dateRow);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn + 2).$monthRow, 'Uang Saku');
        $sheet->mergeCells(Coordinate::stringFromColumnIndex($recapStartColumn + 2).$monthRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn + 2).$dateRow);

        $rowNumber = $dataStartRow;

        foreach ($matrixRows as $matrixRow) {
            $sheet->setCellValue('A'.$rowNumber, $matrixRow['name']);

            foreach ($matrixRow['cells'] as $offset => $cell) {
                $column = Coordinate::stringFromColumnIndex($dateStartColumn + $offset);
                $sheet->setCellValue($column.$rowNumber, $cell['code']);
                $sheet->getStyle($column.$rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cell['color']);
                $sheet->getStyle($column.$rowNumber)->getFont()->getColor()->setRGB('111827');
                $sheet->getStyle($column.$rowNumber)->getFont()->setBold(true);
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn).$rowNumber, $matrixRow['presentCount']);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn + 1).$rowNumber, $matrixRow['notPresentCount']);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($recapStartColumn + 2).$rowNumber, $matrixRow['allowance']);
            $rowNumber++;
        }

        $lastRow = max($dataStartRow, $rowNumber - 1);
        $sheet->freezePane('B7');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('12351F');
        $sheet->getStyle('A2:A4')->getFont()->setBold(true);
        $sheet->getStyle('A'.$monthRow.':'.$matrixLastColumn.$dateRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$monthRow.':'.$matrixLastColumn.$dateRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        $sheet->getStyle('A'.$monthRow.':'.$matrixLastColumn.$dateRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A'.$monthRow.':'.$matrixLastColumn.$dateRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('111827');
        $sheet->getStyle('A'.$dataStartRow.':'.$matrixLastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('111827');
        $sheet->getStyle('A'.$dataStartRow.':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B'.$dataStartRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn - 1).$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($recapStartColumn).$dataStartRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn + 2).$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($recapStartColumn + 2).$dataStartRow.':'.Coordinate::stringFromColumnIndex($recapStartColumn + 2).$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($legendStart.'5:'.$legendLabelColumn.($legendRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle($legendStart.'5')->getFont()->setBold(true);
        $sheet->getStyle($legendStart.'5:'.$legendLabelColumn.'5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($legendStart.'6:'.$legendStart.($legendRow - 1))->getFont()->setBold(true)->getColor()->setRGB('111827');
        $sheet->getStyle($legendStart.'6:'.$legendLabelColumn.($legendRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(27.22);
        for ($columnIndex = $dateStartColumn; $columnIndex < $recapStartColumn; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(16.22);
        }

        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($recapStartColumn))->setWidth(15.22);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($recapStartColumn + 1))->setWidth(23.22);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($recapStartColumn + 2))->setWidth(15.22);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($recapStartColumn + 3))->setWidth(8.11);
        $sheet->getColumnDimension($legendStart)->setWidth(7.22);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($legendColumn + 1))->setWidth(21.22);
        $sheet->getRowDimension(1)->setRowHeight(23.4);
        foreach ([2, 3, 4] as $metaRow) {
            $sheet->getRowDimension($metaRow)->setRowHeight(14.4);
        }
        $sheet->getRowDimension($monthRow)->setRowHeight(34.1);
        $sheet->getRowDimension($dateRow)->setRowHeight(34.1);
        for ($row = $dataStartRow; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(28.1);
        }
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $spreadsheet->getProperties()
            ->setCreator('Kalbe Internship Monitoring')
            ->setTitle('Attendance Report')
            ->setSubject('Attendance export');

        return $spreadsheet;
    }

    public function reportPdf(Request $request): Response
    {
        $payload = $this->attendanceExportPayload($request);
        $filename = 'attendance-report-'.$payload['filters']['from'].'-'.$payload['filters']['to'].'.pdf';

        return $this->pdfResponse('dashboard.attendance.report-pdf', ['payload' => $payload], $filename, 'landscape');
    }

    public function salarySlipPdf(Request $request): Response
    {
        $payload = $this->attendanceExportPayload($request);

        if (! $payload['payroll']) {
            abort(422, 'Pilih satu intern terlebih dahulu untuk download slip gaji.');
        }

        $filename = 'salary-slip-'.Str::slug($payload['payroll']['internName']).'-'.$payload['filters']['from'].'-'.$payload['filters']['to'].'.pdf';

        return $this->pdfResponse('dashboard.attendance.salary-slip-pdf', ['payload' => $payload], $filename);
    }

    public function sendSalarySlips(Request $request, NotificationService $notifications): RedirectResponse
    {
        $context = $this->salarySlipGenerationContext($request);
        $authUser = $context['authUser'];
        $selectedInternId = $context['selectedInternId'];
        $recipients = $context['recipients'];
        $now = $context['now'];
        $from = $context['from'];
        $to = $context['to'];
        $documents = $context['documents'];
        $storedPaths = [];

        try {
            DB::transaction(function () use ($documents, $authUser, $notifications, $now, $from, $to, &$storedPaths): void {
                foreach ($documents as $document) {
                    if (! Storage::disk('local')->put($document['filePath'], $document['contents'])) {
                        throw new RuntimeException('File slip gaji gagal disimpan.');
                    }

                    $storedPaths[] = $document['filePath'];
                    $payroll = $document['payroll'];
                    $recipient = $document['recipient'];
                    $salarySlip = TrSalarySlip::create([
                        'intIntern_ID' => $recipient->intern->intIntern_ID,
                        'intSalarySlipCreatedByUser_ID' => $authUser->intUser_ID,
                        'dtmSalarySlipPeriodStart' => $from,
                        'dtmSalarySlipPeriodEnd' => $to,
                        'txtSalarySlipFileName' => $document['fileName'],
                        'txtSalarySlipFilePath' => $document['filePath'],
                        'intSalarySlipWorkdays' => $payroll['workdays'],
                        'intSalarySlipPresentDays' => $payroll['presentDays'],
                        'intSalarySlipLateDays' => $payroll['lateDays'],
                        'intSalarySlipAbsentDays' => $payroll['absentDays'],
                        'intSalarySlipPendingDays' => $payroll['pendingDays'],
                        'intSalarySlipPaidDays' => $payroll['paidDays'],
                        'floatSalarySlipDailySalary' => $payroll['dailySalary'],
                        'floatSalarySlipGrossSalary' => $payroll['grossSalary'],
                        'floatSalarySlipDeduction' => $payroll['deduction'],
                        'floatSalarySlipNetSalary' => $payroll['netSalary'],
                        'txtInsertedBy' => $authUser->txtEmail ?? 'system',
                        'dtmInserted' => $now,
                    ]);

                    $notifications->send(
                        $recipient,
                        'salary-slip',
                        'Slip gaji tersedia',
                        'Slip gaji periode '.$from->format('d M Y').' - '.$to->format('d M Y').' sudah tersedia di profil kamu.',
                        route('profile.show').'#salary-slips',
                        'salary-slip-created:'.$salarySlip->intSalarySlip_ID,
                    );
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        $recipientLabel = $selectedInternId === 0
            ? $recipients->count().' intern aktif'
            : $this->displayName($recipients->first());

        return redirect()
            ->route('attendance.index', ['tab' => 'detail'])
            ->with('success', 'Slip gaji berhasil dikirim kepada '.$recipientLabel.'.');
    }

    public function downloadSalarySlips(Request $request): Response|BinaryFileResponse
    {
        $context = $this->salarySlipGenerationContext($request);
        $documents = $context['documents'];

        if ($context['selectedInternId'] !== 0) {
            $document = $documents->first();

            return response($document['contents'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$document['fileName'].'"',
            ]);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'salary-slips-');

        if ($temporaryPath === false) {
            throw new RuntimeException('File ZIP slip gaji gagal dibuat.');
        }

        $zip = new ZipArchive;
        $zipOpened = false;

        try {
            $openResult = $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new RuntimeException('File ZIP slip gaji gagal dibuat.');
            }

            $zipOpened = true;
            $usedFileNames = [];

            foreach ($documents as $document) {
                $fileName = $document['fileName'];

                if (isset($usedFileNames[$fileName])) {
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME).'-'.$document['recipient']->intern->intIntern_ID.'.pdf';
                }

                $usedFileNames[$fileName] = true;

                if (! $zip->addFromString($fileName, $document['contents'])) {
                    throw new RuntimeException('PDF slip gaji gagal ditambahkan ke ZIP.');
                }
            }

            if (! $zip->close()) {
                throw new RuntimeException('File ZIP slip gaji gagal diselesaikan.');
            }

            $zipOpened = false;
        } catch (Throwable $exception) {
            if ($zipOpened) {
                $zip->close();
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            throw $exception;
        }

        $fileName = 'salary-slips-'.$context['from']->toDateString().'-'.$context['to']->toDateString().'.zip';

        return response()
            ->download($temporaryPath, $fileName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @return array{authUser: MUser, selectedInternId: int, recipients: Collection<int, MUser>, now: Carbon, from: Carbon, to: Carbon, documents: Collection<int, array<string, mixed>>}
     */
    private function salarySlipGenerationContext(Request $request): array
    {
        $authUser = $this->currentUser($request);

        if (! RoleAccess::isAttendanceAdmin($authUser)) {
            abort(403, 'Slip gaji hanya dapat diproses oleh Headmaster atau HRD.');
        }

        $validated = $request->validate([
            'dtmSalarySlipPeriodStart' => ['required', 'date'],
            'dtmSalarySlipPeriodEnd' => ['required', 'date', 'after_or_equal:dtmSalarySlipPeriodStart', 'before_or_equal:today'],
            'intIntern_ID' => ['required', 'integer', 'min:0'],
        ], [
            'dtmSalarySlipPeriodEnd.after_or_equal' => 'Tanggal akhir slip gaji harus sama dengan atau setelah tanggal awal.',
            'dtmSalarySlipPeriodEnd.before_or_equal' => 'Tanggal akhir slip gaji tidak boleh melewati hari ini.',
        ]);

        $now = Carbon::now(self::TIMEZONE);
        $from = Carbon::parse($validated['dtmSalarySlipPeriodStart'], self::TIMEZONE)->startOfDay();
        $to = Carbon::parse($validated['dtmSalarySlipPeriodEnd'], self::TIMEZONE)->startOfDay();
        $periodInternUsers = $this->attendancePeriodInternUsers($from, $to);
        $selectedInternId = (int) $validated['intIntern_ID'];
        $recipients = $selectedInternId === 0
            ? $periodInternUsers
            : $periodInternUsers
                ->filter(fn (MUser $user) => $user->intern?->intIntern_ID === $selectedInternId)
                ->values();

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'intIntern_ID' => 'Pilih minimal satu intern yang aktif pada periode slip gaji.',
            ]);
        }

        $setting = $this->attendanceSetting();
        $generatedBy = $this->generatedByName($authUser);
        $documents = $recipients->map(function (MUser $recipient) use ($from, $to, $setting, $now, $generatedBy): array {
            $detailRequest = Request::create('/attendance', 'GET', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'intUser_ID' => (string) $recipient->intUser_ID,
            ]);
            $detail = $this->adminAttendanceDetail($detailRequest, collect([$recipient]), $setting, $now);
            $payload = [
                'generatedAt' => $now,
                'generatedBy' => $generatedBy,
                'filters' => $detail['filters'],
                'rows' => $detail['rows'],
                'payroll' => $detail['payroll'],
            ];
            $fileName = 'salary-slip-'.Str::slug($detail['payroll']['internName']).'-'.$from->toDateString().'-'.$to->toDateString().'.pdf';

            return [
                'recipient' => $recipient,
                'payroll' => $detail['payroll'],
                'fileName' => $fileName,
                'filePath' => 'salary-slips/'.$now->format('Y/m').'/'.Str::uuid().'.pdf',
                'contents' => $this->renderPdf('dashboard.attendance.salary-slip-pdf', ['payload' => $payload]),
            ];
        });

        return compact('authUser', 'selectedInternId', 'recipients', 'now', 'from', 'to', 'documents');
    }

    private function pdfResponse(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return response($this->renderPdf($view, $data, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function renderPdf(string $view, array $data, string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceExportPayload(Request $request): array
    {
        $authUser = $this->currentUser($request);

        if (! RoleAccess::isAttendanceAdmin($authUser)) {
            abort(403, 'Export absensi hanya untuk Headmaster atau HRD.');
        }

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        [$fromDate, $toDate] = $this->detailDateRange($request, $now);
        $teamUsers = $this->attendancePeriodInternUsers($fromDate, $toDate);
        $detail = $this->adminAttendanceDetail($request, $teamUsers, $setting, $now);
        $calendar = $this->attendanceReportCalendar($detail['filters']['from'], $detail['filters']['to']);
        $matrixInterns = $detail['selectedIntern'] ? collect([$detail['selectedIntern']]) : $detail['interns'];

        return [
            'generatedAt' => $now,
            'generatedBy' => $this->generatedByName($authUser),
            'filters' => $detail['filters'],
            'interns' => $detail['interns'],
            'rows' => $detail['rows'],
            'calendar' => $calendar,
            'matrixRows' => $this->attendanceReportMatrix($detail['rows'], $matrixInterns, $calendar),
            'legend' => $this->attendanceReportLegend(),
            'summary' => $detail['summary'],
            'selectedIntern' => $detail['selectedIntern'],
            'payroll' => $detail['payroll'],
        ];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function attendanceReportCalendar(string $from, string $to): Collection
    {
        $fromDate = Carbon::parse($from, self::TIMEZONE)->startOfDay();
        $toDate = Carbon::parse($to, self::TIMEZONE)->startOfDay();
        $dates = collect();

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            if ($this->isWorkday($date)) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, MUser>  $interns
     * @param  Collection<int, Carbon>  $calendar
     * @return Collection<int, array<string, mixed>>
     */
    private function attendanceReportMatrix(Collection $rows, Collection $interns, Collection $calendar): Collection
    {
        $rowsByInternAndDate = $rows->keyBy(fn (array $row): string => $row['intUser_ID'].'|'.$row['date']->format('Y-m-d'));

        return $interns
            ->map(function (MUser $user) use ($calendar, $rowsByInternAndDate): array {
                $dailySalary = (float) ($user->intern?->floatInternSalary ?? 0);
                $presentCount = 0;
                $notPresentCount = 0;

                $cells = $calendar->map(function (Carbon $date) use ($user, $rowsByInternAndDate, &$presentCount, &$notPresentCount): array {
                    $row = $rowsByInternAndDate->get($user->intUser_ID.'|'.$date->format('Y-m-d'));
                    $cell = $this->attendanceReportCell($row);

                    if ($cell['countsAsPresent']) {
                        $presentCount++;
                    }

                    if ($cell['countsAsNotPresent']) {
                        $notPresentCount++;
                    }

                    return $cell;
                })->values();

                return [
                    'intUser_ID' => $user->intUser_ID,
                    'intIntern_ID' => $user->intern?->intIntern_ID,
                    'internNo' => $user->intern?->txtInternNo ?: 'INT-'.str_pad((string) ($user->intern?->intIntern_ID ?? $user->intUser_ID), 3, '0', STR_PAD_LEFT),
                    'name' => $this->displayName($user),
                    'dailySalary' => $dailySalary,
                    'cells' => $cells,
                    'presentCount' => $presentCount,
                    'notPresentCount' => $notPresentCount,
                    'allowance' => $presentCount * $dailySalary,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  ?array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attendanceReportCell(?array $row): array
    {
        if (! $row || $row['status'] === 'Belum Clock In') {
            return [
                'code' => '-',
                'label' => 'Belum Clock In',
                'class' => 'pending',
                'color' => 'F2F4F7',
                'countsAsPresent' => false,
                'countsAsNotPresent' => false,
            ];
        }

        if ($row['status'] === 'Sakit') {
            return [
                'code' => 'S',
                'label' => 'Sakit',
                'class' => 'sick',
                'color' => '9BE7E4',
                'countsAsPresent' => false,
                'countsAsNotPresent' => true,
            ];
        }

        if ($row['status'] === 'Izin') {
            return [
                'code' => 'I',
                'label' => 'Izin',
                'class' => 'permission',
                'color' => 'BDBDBD',
                'countsAsPresent' => false,
                'countsAsNotPresent' => true,
            ];
        }

        if ($row['status'] === 'Tidak Masuk') {
            return [
                'code' => 'A',
                'label' => 'Absen',
                'class' => 'absent',
                'color' => 'FF5A5F',
                'countsAsPresent' => false,
                'countsAsNotPresent' => true,
            ];
        }

        if ($row['status'] === 'Terlambat') {
            return [
                'code' => 'H (Terlambat)',
                'label' => 'Hadir (Terlambat)',
                'class' => 'late',
                'color' => '7ED957',
                'countsAsPresent' => true,
                'countsAsNotPresent' => false,
            ];
        }

        if (($row['workMode'] ?? '') === 'WFH') {
            return [
                'code' => 'H (WFH)',
                'label' => 'Hadir (WFH)',
                'class' => 'wfh',
                'color' => '7ED957',
                'countsAsPresent' => true,
                'countsAsNotPresent' => false,
            ];
        }

        return [
            'code' => 'H (Tepat Waktu)',
            'label' => 'Hadir (Tepat Waktu)',
            'class' => 'present',
            'color' => '7ED957',
            'countsAsPresent' => true,
            'countsAsNotPresent' => false,
        ];
    }

    /**
     * @return array<int, array{code: string, label: string, color: string, class: string}>
     */
    private function attendanceReportLegend(): array
    {
        return [
            ['code' => 'H', 'label' => 'Hadir', 'color' => '7ED957', 'class' => 'present'],
            ['code' => 'A', 'label' => 'Absen', 'color' => 'FF5A5F', 'class' => 'absent'],
            ['code' => 'S', 'label' => 'Sakit', 'color' => '9BE7E4', 'class' => 'sick'],
            ['code' => 'I', 'label' => 'Izin', 'color' => 'BDBDBD', 'class' => 'permission'],
        ];
    }

    private function monthLabel(Carbon $date): string
    {
        $month = match ((int) $date->format('n')) {
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            default => 'Desember',
        };

        return $month.' '.$date->format('Y');
    }

    private function currentUser(Request $request): MUser
    {
        $apiUser = $request->attributes->get('kmi_api_user');

        if ($apiUser instanceof MUser) {
            return $apiUser->loadMissing(['intern', 'mentor', 'adminProfile']);
        }

        return MUser::with(['intern', 'mentor', 'adminProfile'])->findOrFail($request->session()->get('auth_user_id'));
    }

    private function attendanceActionError(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['attendance' => [$message]],
            ], 422);
        }

        return back()->withErrors(['attendance' => $message]);
    }

    private function attendanceActionSuccess(Request $request, TrAttendance $attendance, string $message): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->apiAttendanceRecord($attendance),
            ]);
        }

        return redirect()->route('attendance.index')->with('success', $message);
    }

    private function apiAttendanceRecord(TrAttendance $attendance): array
    {
        return [
            'id' => (int) $attendance->intAttendance_ID,
            'intern_id' => $attendance->intIntern_ID
                ? (int) $attendance->intIntern_ID
                : ($attendance->user?->intern?->intIntern_ID
                    ? (int) $attendance->user->intern->intIntern_ID
                    : null),
            'user_id' => $attendance->intUser_ID ? (int) $attendance->intUser_ID : null,
            'date' => $attendance->dtmAttendanceDate?->toDateString(),
            'work_mode' => $attendance->txtAttendanceWorkMode ?: 'Office',
            'status' => $attendance->txtAttendanceStatus,
            'clock_in' => $attendance->dtmAttendanceClockIn?->toISOString(),
            'clock_in_status' => $attendance->txtAttendanceClockInStatus,
            'clock_out' => $attendance->dtmAttendanceClockOut?->toISOString(),
            'clock_out_status' => $attendance->txtAttendanceClockOutStatus,
            'location' => [
                'name' => $attendance->txtAttendanceAddress,
                'url' => $attendance->txtAttendanceLocationUrl,
                'latitude' => $attendance->floatAttendanceLatitude ?? $attendance->floatLatitude,
                'longitude' => $attendance->floatAttendanceLongitude ?? $attendance->floatLongitude,
                'accuracy' => $attendance->floatAttendanceLocationAccuracy,
                'distance_meter' => $attendance->floatAttendanceDistanceMeter,
                'allowed_distance_meter' => $attendance->floatAttendanceAllowedDistanceMeter,
                'within_tolerance' => $attendance->bitAttendanceWithinTolerance,
            ],
            'clock_out_location' => [
                'name' => $attendance->txtAttendanceClockOutAddress,
                'url' => $attendance->txtAttendanceClockOutLocationUrl,
                'latitude' => $attendance->floatAttendanceClockOutLatitude,
                'longitude' => $attendance->floatAttendanceClockOutLongitude,
                'accuracy' => $attendance->floatAttendanceClockOutLocationAccuracy,
                'distance_meter' => $attendance->floatAttendanceClockOutDistanceMeter,
                'within_tolerance' => $attendance->bitAttendanceClockOutWithinTolerance,
            ],
            'face' => [
                'distance' => $attendance->floatAttendanceFaceDistance,
                'algorithm' => $attendance->txtAttendanceFaceAlgorithm,
            ],
            'clock_out_face' => [
                'distance' => $attendance->floatAttendanceClockOutFaceDistance,
                'algorithm' => $attendance->txtAttendanceClockOutFaceAlgorithm,
            ],
            'note' => $attendance->txtAttendanceNote,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function apiAdminInternGroups(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) $row['intUser_ID'])
            ->map(function (Collection $internRows): array {
                $intern = $internRows->first();

                return [
                    'intern' => [
                        'id' => $intern['intIntern_ID'] ? (int) $intern['intIntern_ID'] : null,
                        'user_id' => $intern['intUser_ID'] ? (int) $intern['intUser_ID'] : null,
                        'number' => $intern['internNo'],
                        'name' => $intern['name'],
                        'type' => $intern['internType'],
                    ],
                    'summary' => [
                        'total' => $internRows->count(),
                        'present' => $internRows->where('status', 'Hadir')->count(),
                        'late' => $internRows->where('status', 'Terlambat')->count(),
                        'absent' => $internRows->whereIn('status', ['Tidak Masuk', 'Sakit', 'Izin'])->count(),
                        'sick' => $internRows->where('status', 'Sakit')->count(),
                        'permission' => $internRows->where('status', 'Izin')->count(),
                        'pending' => $internRows->whereNotIn('status', ['Hadir', 'Terlambat', 'Tidak Masuk', 'Sakit', 'Izin'])->count(),
                    ],
                    'records' => $internRows->sortByDesc(fn (array $row): string => $row['date']->toDateString())->map(fn (array $row): array => [
                        'id' => null,
                        'intern_id' => $row['intIntern_ID'] ? (int) $row['intIntern_ID'] : null,
                        'user_id' => $row['intUser_ID'] ? (int) $row['intUser_ID'] : null,
                        'date' => $row['date']->toDateString(),
                        'work_mode' => $row['workMode'],
                        'status' => $row['status'],
                        'clock_in' => $row['clockInIso'],
                        'clock_in_status' => $row['clockInStatus'],
                        'clock_out' => $row['clockOutIso'],
                        'clock_out_status' => $row['clockOutStatus'],
                        'location' => [
                            'name' => $row['locationIn'] === '-' ? null : $row['locationIn'],
                            'url' => $row['locationInUrl'],
                        ],
                        'clock_out_location' => [
                            'name' => $row['locationOut'] === '-' ? null : $row['locationOut'],
                            'url' => $row['locationOutUrl'],
                        ],
                        'note' => null,
                        'intern' => [
                            'id' => $row['intIntern_ID'] ? (int) $row['intIntern_ID'] : null,
                            'number' => $row['internNo'],
                            'name' => $row['name'],
                            'type' => $row['internType'],
                        ],
                    ])->values(),
                ];
            })
            ->sortBy('intern.name')
            ->values();
    }

    /**
     * @param  Collection<int, MUser>  $teamUsers
     * @param  Collection<int, TrAttendance>  $todayAttendances
     * @return Collection<int, array<string, mixed>>
     */
    private function apiTeamTodayRecords(
        Collection $teamUsers,
        Collection $todayAttendances,
        MAttendanceSetting $setting,
        Carbon $now
    ): Collection {
        $windows = $this->attendanceWindows($setting, $now);
        $isWorkday = $this->isWorkday($now);
        $absenceRequests = $this->approvedAbsenceRequests($teamUsers, $now->copy()->startOfDay(), $now->copy()->startOfDay());

        return $teamUsers
            ->map(function (MUser $user) use ($todayAttendances, $windows, $now, $isWorkday, $absenceRequests): array {
                $attendance = $todayAttendances->get($user->intUser_ID);
                $absenceRequest = $absenceRequests->get(($user->intern?->intIntern_ID ?? 0).'|'.$now->format('Y-m-d'));
                $status = match (true) {
                    (bool) $attendance => $this->attendanceStatus($attendance, $windows),
                    (bool) $absenceRequest => $absenceRequest->txtWorkFromHomeRequestType,
                    ! $isWorkday => 'Tidak Ada Absensi',
                    $now->gt($windows['clockInLateEnd']) => 'Tidak Masuk',
                    default => 'Belum Clock In',
                };
                $record = $attendance
                    ? $this->apiAttendanceRecord($attendance)
                    : [
                        'id' => null,
                        'intern_id' => (int) $user->intern->intIntern_ID,
                        'user_id' => (int) $user->intUser_ID,
                        'date' => $now->toDateString(),
                        'work_mode' => $absenceRequest?->txtWorkFromHomeRequestType ?: $this->workContext($user, $now)['mode'],
                        'status' => $status,
                        'clock_in' => null,
                        'clock_in_status' => null,
                        'clock_out' => null,
                        'clock_out_status' => null,
                        'location' => [],
                        'clock_out_location' => [],
                        'face' => [],
                        'clock_out_face' => [],
                        'note' => null,
                    ];

                $record['status'] = $status;
                $record['intern'] = [
                    'id' => (int) $user->intern->intIntern_ID,
                    'number' => $user->intern->txtInternNo,
                    'name' => $this->displayName($user),
                    'type' => $user->intern->txtInternType,
                ];
                $record['face_registered'] = (bool) $user->faceEnrollment?->bitActive;

                return $record;
            })
            ->sortBy(fn (array $record) => $record['intern']['name'])
            ->values();
    }

    private function ensureInternCanAttend(MUser $authUser): void
    {
        if (! RoleAccess::isIntern($authUser)) {
            throw ValidationException::withMessages(['attendance' => 'Absensi hanya untuk intern.']);
        }

        if ($authUser->intern->hasCompletedInternship(Carbon::now(self::TIMEZONE))) {
            $endDate = $authUser->intern->effectiveEndDate()?->format('d M Y');
            throw ValidationException::withMessages([
                'attendance' => 'Masa internship sudah selesai pada '.$endDate.'. Absensi tidak lagi tersedia.',
            ]);
        }
    }

    /**
     * @return Collection<int, MUser>
     */
    private function attendanceInternUsers(): Collection
    {
        return MUser::with(['intern', 'faceEnrollment'])
            ->where('bitActive', true)
            ->whereHas('intern', fn ($query) => $query->where('bitActive', true))
            ->get()
            ->reject(fn (MUser $user) => $user->intern?->hasCompletedInternship(Carbon::now(self::TIMEZONE)))
            ->sortBy(fn (MUser $user) => $this->displayName($user))
            ->values();
    }

    /**
     * @return Collection<int, MUser>
     */
    private function attendancePeriodInternUsers(Carbon $fromDate, Carbon $toDate): Collection
    {
        $fromDate = $fromDate->copy()->startOfDay();
        $toDate = $toDate->copy()->startOfDay();
        $attendanceUserIds = TrAttendance::whereDate('dtmAttendanceDate', '>=', $fromDate->toDateString())
            ->whereDate('dtmAttendanceDate', '<=', $toDate->toDateString())
            ->pluck('intUser_ID')
            ->filter()
            ->unique()
            ->values();
        $approvedRequestInternIds = TrWorkFromHomeRequest::where('bitActive', true)
            ->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_APPROVED)
            ->whereDate('dtmWorkFromHomeRequestStartDate', '<=', $toDate->toDateString())
            ->whereDate('dtmWorkFromHomeRequestEndDate', '>=', $fromDate->toDateString())
            ->pluck('intIntern_ID')
            ->filter()
            ->unique()
            ->values();

        return MUser::with(['intern', 'faceEnrollment'])
            ->whereHas('intern')
            ->get()
            ->filter(function (MUser $user) use ($fromDate, $attendanceUserIds, $approvedRequestInternIds): bool {
                $intern = $user->intern;

                if (! $intern) {
                    return false;
                }

                $endDate = $intern->effectiveEndDate();
                $hasPeriodData = $attendanceUserIds->contains($user->intUser_ID)
                    || $approvedRequestInternIds->contains($intern->intIntern_ID);
                $isActiveNow = (bool) $user->bitActive && (bool) $intern->bitActive;
                $hasPeriodOverlap = ! $endDate || $endDate->gte($fromDate);

                return $hasPeriodOverlap && ($isActiveNow || $hasPeriodData || (bool) $endDate);
            })
            ->sortBy(fn (MUser $user) => $this->displayName($user))
            ->values();
    }

    /**
     * @return Collection<int, MUser>
     */
    private function attendanceDetailInternUsers(): Collection
    {
        $now = Carbon::now(self::TIMEZONE);

        return MUser::with(['intern', 'faceEnrollment'])
            ->withCount('attendances')
            ->whereHas('intern')
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->where('bitActive', true)
                            ->whereHas('intern', fn ($query) => $query->where('bitActive', true));
                    })
                    ->orWhereHas('attendances');
            })
            ->get()
            ->reject(fn (MUser $user) => $user->intern?->hasCompletedInternship($now) && (int) $user->attendances_count === 0)
            ->sortBy(fn (MUser $user) => $this->displayName($user))
            ->values();
    }

    private function attendanceSetting(): MAttendanceSetting
    {
        $setting = MAttendanceSetting::firstOrCreate(
            ['intAttendanceSetting_ID' => 1],
            [
                'txtAttendanceSettingStartTime' => self::DEFAULT_CLOCK_IN_START,
                'txtAttendanceSettingEndTime' => self::DEFAULT_CLOCK_OUT_END,
                'txtAttendanceSettingClockInStartTime' => self::DEFAULT_CLOCK_IN_START,
                'txtAttendanceSettingClockInEndTime' => self::DEFAULT_CLOCK_IN_END,
                'txtAttendanceSettingClockOutStartTime' => self::DEFAULT_CLOCK_OUT_START,
                'txtAttendanceSettingClockOutEndTime' => self::DEFAULT_CLOCK_OUT_END,
                'floatAttendanceSettingFaceThreshold' => 0.38,
                'bitAttendanceSettingLocationRequired' => true,
                'bitActive' => true,
                'txtInsertedBy' => 'system',
                'dtmInserted' => Carbon::now(self::TIMEZONE),
            ],
        );

        $defaults = [
            'txtAttendanceSettingStartTime' => self::DEFAULT_CLOCK_IN_START,
            'txtAttendanceSettingEndTime' => self::DEFAULT_CLOCK_OUT_END,
            'txtAttendanceSettingClockInStartTime' => self::DEFAULT_CLOCK_IN_START,
            'txtAttendanceSettingClockInEndTime' => self::DEFAULT_CLOCK_IN_END,
            'txtAttendanceSettingClockOutStartTime' => self::DEFAULT_CLOCK_OUT_START,
            'txtAttendanceSettingClockOutEndTime' => self::DEFAULT_CLOCK_OUT_END,
            'floatAttendanceSettingFaceThreshold' => 0.38,
            'bitAttendanceSettingLocationRequired' => true,
            'bitActive' => true,
        ];
        $dirty = false;

        foreach ($defaults as $field => $value) {
            if ($setting->{$field} === null || $setting->{$field} === '') {
                $setting->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $setting->save();
        }

        return $setting;
    }

    /**
     * @return array{clockInStart: Carbon, clockInEnd: Carbon, clockInLateEnd: Carbon, clockOutStart: Carbon, clockOutEnd: Carbon, clockOutLateEnd: Carbon}
     */
    private function attendanceWindows(MAttendanceSetting $setting, Carbon $now): array
    {
        $clockInStart = $this->settingTime(
            $setting->txtAttendanceSettingClockInStartTime,
            $setting->txtAttendanceSettingStartTime ?: self::DEFAULT_CLOCK_IN_START,
            self::DEFAULT_CLOCK_IN_START,
        );
        $clockInEnd = $this->settingTime(
            $setting->txtAttendanceSettingClockInEndTime,
            null,
            self::DEFAULT_CLOCK_IN_END,
        );
        $clockOutStart = $this->settingTime(
            $setting->txtAttendanceSettingClockOutStartTime,
            null,
            self::DEFAULT_CLOCK_OUT_START,
        );
        $clockOutEnd = $this->settingTime(
            $setting->txtAttendanceSettingClockOutEndTime,
            $setting->txtAttendanceSettingEndTime ?: self::DEFAULT_CLOCK_OUT_END,
            self::DEFAULT_CLOCK_OUT_END,
        );

        return [
            'clockInStart' => Carbon::parse($now->toDateString().' '.$clockInStart, self::TIMEZONE),
            'clockInEnd' => Carbon::parse($now->toDateString().' '.$clockInEnd, self::TIMEZONE),
            'clockInLateEnd' => $now->copy()->endOfDay(),
            'clockOutStart' => Carbon::parse($now->toDateString().' '.$clockOutStart, self::TIMEZONE),
            'clockOutEnd' => Carbon::parse($now->toDateString().' '.$clockOutEnd, self::TIMEZONE),
            'clockOutLateEnd' => $now->copy()->endOfDay(),
        ];
    }

    private function settingTime(mixed $primary, mixed $fallback, string $default): string
    {
        foreach ([$primary, $fallback, $default] as $value) {
            if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @param  array{clockInStart: Carbon, clockInEnd: Carbon, clockInLateEnd: Carbon, clockOutStart: Carbon, clockOutEnd: Carbon, clockOutLateEnd: Carbon}  $windows
     */
    private function attendanceWindowState(Carbon $now, array $windows): string
    {
        if ($now->lt($windows['clockInStart'])) {
            return 'before';
        }

        if ($now->lte($windows['clockInEnd'])) {
            return 'clock-in';
        }

        if ($now->lt($windows['clockOutStart'])) {
            return 'between';
        }

        if ($now->lte($windows['clockOutEnd'])) {
            return 'clock-out';
        }

        if ($now->lte($windows['clockOutLateEnd'])) {
            return 'clock-out-late';
        }

        return 'after-clock-out';
    }

    /**
     * @return array<string, mixed>
     */
    private function verifiedFaceAndLocation(Request $request, MUser $authUser, MAttendanceSetting $setting, array $workContext): array
    {
        $enrollment = $authUser->faceEnrollment()
            ->where('bitActive', true)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages(['attendance' => 'Daftarkan wajah terlebih dahulu sebelum absen.']);
        }

        if ($request->is('api/*')) {
            $request->merge([
                'txtAttendanceCapturedImage' => $request->input('image', $request->input('txtAttendanceCapturedImage')),
                'floatAttendanceLatitude' => $request->input('latitude', $request->input('floatAttendanceLatitude')),
                'floatAttendanceLongitude' => $request->input('longitude', $request->input('floatAttendanceLongitude')),
                'floatAttendanceLocationAccuracy' => $request->input('accuracy', $request->input('floatAttendanceLocationAccuracy')),
                'txtAttendanceDevice' => $request->input('device', $request->input('txtAttendanceDevice')),
            ]);
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
            throw ValidationException::withMessages([
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
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        $faceDistance = (float) ($facePayload['distance'] ?? 1.0);

        if (! (bool) ($facePayload['match'] ?? false)) {
            throw ValidationException::withMessages([
                'attendance' => 'Wajah tidak cocok dengan Face ID terdaftar. Coba ulang dengan pencahayaan yang lebih stabil.',
            ]);
        }

        $latitude = round((float) $validated['floatAttendanceLatitude'], 7);
        $longitude = round((float) $validated['floatAttendanceLongitude'], 7);
        $accuracy = isset($validated['floatAttendanceLocationAccuracy'])
            ? round((float) $validated['floatAttendanceLocationAccuracy'], 2)
            : null;
        $locationLabel = $this->coordinateLabel($latitude, $longitude, $accuracy);
        $location = null;
        $distanceMeter = null;
        $allowedDistanceMeter = null;
        $withinTolerance = null;

        if ($workContext['mode'] === 'Office') {
            $location = MAttendanceLocation::query()
                ->where('bitActive', true)
                ->orderByDesc('dtmUpdated')
                ->orderBy('intAttendanceLocation_ID')
                ->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    'attendance' => 'Lokasi kantor belum dikonfigurasi oleh HRD/Headmaster. Hubungi admin sebelum melakukan absensi normal.',
                ]);
            }

            $distanceMeter = round($this->distanceMeter(
                $latitude,
                $longitude,
                (float) $location->floatAttendanceLocationLatitude,
                (float) $location->floatAttendanceLocationLongitude,
            ), 2);
            $allowedDistanceMeter = (float) $location->intAttendanceLocationRadiusMeter
                + (float) $location->intAttendanceLocationToleranceMeter;
            $withinTolerance = $distanceMeter <= $allowedDistanceMeter;

            if ($accuracy !== null
                && $location->intAttendanceLocationMaximumAccuracyMeter
                && $accuracy > $location->intAttendanceLocationMaximumAccuracyMeter) {
                throw ValidationException::withMessages([
                    'attendance' => 'Akurasi GPS '.$accuracy.' meter belum memenuhi batas '.$location->intAttendanceLocationMaximumAccuracyMeter.' meter. Aktifkan mode akurasi tinggi lalu coba lagi.',
                ]);
            }

            if (! $withinTolerance) {
                throw ValidationException::withMessages([
                    'attendance' => 'Kamu berada '.number_format($distanceMeter, 0, ',', '.').' meter dari '.$location->txtAttendanceLocationName.'. Batas yang diizinkan '.number_format($allowedDistanceMeter, 0, ',', '.').' meter.',
                ]);
            }

            $locationLabel = $location->txtAttendanceLocationName.' · '.$locationLabel;
        } else {
            $locationLabel = 'WFH · '.$locationLabel;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'locationLabel' => $locationLabel,
            'locationUrl' => 'https://www.google.com/maps?q='.$latitude.','.$longitude,
            'locationId' => $location?->intAttendanceLocation_ID,
            'distanceMeter' => $distanceMeter,
            'allowedDistanceMeter' => $allowedDistanceMeter,
            'withinTolerance' => $withinTolerance,
            'faceDistance' => round($faceDistance, 4),
            'algorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
            'device' => Str::limit($validated['txtAttendanceDevice'] ?? $request->userAgent() ?? 'Browser', 255, ''),
            'faceMatchPayload' => [
                'distance' => round($faceDistance, 4),
                'threshold' => $threshold,
                'similarity' => $facePayload['similarity'] ?? null,
                'quality' => $facePayload['quality'] ?? null,
                'algorithm' => $facePayload['algorithm'] ?? self::FACE_ALGORITHM,
            ],
        ];
    }

    /** @return array{mode: string, request: ?TrWorkFromHomeRequest} */
    private function workContext(MUser $user, Carbon $date): array
    {
        $wfhRequest = TrWorkFromHomeRequest::where('intIntern_ID', $user->intern?->intIntern_ID ?? 0)
            ->where('bitActive', true)
            ->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_APPROVED)
            ->where('txtWorkFromHomeRequestType', TrWorkFromHomeRequest::TYPE_WFH)
            ->whereDate('dtmWorkFromHomeRequestStartDate', '<=', $date->toDateString())
            ->whereDate('dtmWorkFromHomeRequestEndDate', '>=', $date->toDateString())
            ->first();

        return [
            'mode' => $wfhRequest ? 'WFH' : 'Office',
            'request' => $wfhRequest,
        ];
    }

    private function distanceMeter(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
        $absenceRequests = $this->approvedAbsenceRequests(collect([$user]), $weekStart, $weekEnd);

        return collect(range(0, 4))
            ->map(function (int $offset) use ($records, $now, $weekStart, $setting, $user, $absenceRequests) {
                $date = $weekStart->copy()->addDays($offset);
                $endDate = $user->intern?->effectiveEndDate();

                if ($endDate && $date->gt($endDate)) {
                    return null;
                }

                $dateWindows = $this->attendanceWindows($setting, $date);
                $dateKey = $date->format('Y-m-d');
                $attendance = $records->get($dateKey);
                $absenceRequest = $absenceRequests->get(($user->intern?->intIntern_ID ?? 0).'|'.$dateKey);
                $isToday = $date->isSameDay($now);
                $isFuture = $date->gt($now->copy()->startOfDay());

                if ($attendance) {
                    $clockInStatus = $this->attendanceClockInStatus($attendance, $dateWindows);
                    $clockOutMissing = ! $attendance->dtmAttendanceClockOut;
                    $clockOutWarning = $clockOutMissing
                        && (! $isToday || $now->gt($dateWindows['clockOutEnd']));

                    return [
                        'date' => $date,
                        'status' => $this->attendanceStatus($attendance, $dateWindows),
                        'clockIn' => $this->attendanceTimeLabel($this->attendanceClockInAt($attendance)),
                        'clockInStatus' => $clockInStatus,
                        'clockInWarning' => false,
                        'clockOut' => $this->attendanceTimeLabel($this->attendanceClockOutAt($attendance)),
                        'clockOutStatus' => $attendance->txtAttendanceClockOutStatus,
                        'clockOutWarning' => $clockOutWarning,
                        'locationIn' => $attendance->txtAttendanceAddress ?: '-',
                        'locationInUrl' => $attendance->txtAttendanceLocationUrl,
                        'locationOut' => $attendance->txtAttendanceClockOutAddress ?: '-',
                        'locationOutUrl' => $attendance->txtAttendanceClockOutLocationUrl,
                        'faceDistance' => $attendance->floatAttendanceFaceDistance,
                        'clockOutFaceDistance' => $attendance->floatAttendanceClockOutFaceDistance,
                        'workMode' => $attendance->txtAttendanceWorkMode ?: 'Office',
                    ];
                }

                if ($absenceRequest) {
                    return [
                        'date' => $date,
                        'status' => $absenceRequest->txtWorkFromHomeRequestType,
                        'clockIn' => '-',
                        'clockInStatus' => null,
                        'clockInWarning' => false,
                        'clockOut' => '-',
                        'clockOutStatus' => null,
                        'clockOutWarning' => false,
                        'locationIn' => '-',
                        'locationInUrl' => null,
                        'locationOut' => '-',
                        'locationOutUrl' => null,
                        'faceDistance' => null,
                        'clockOutFaceDistance' => null,
                        'workMode' => $absenceRequest->txtWorkFromHomeRequestType,
                    ];
                }

                $status = ($isFuture || ($isToday && ! $now->gt($dateWindows['clockInLateEnd']))) ? 'Belum Clock In' : 'Tidak Masuk';
                $clockInWarning = $isToday
                    && $now->gt($dateWindows['clockInEnd'])
                    && ! $now->gt($dateWindows['clockInLateEnd']);

                return [
                    'date' => $date,
                    'status' => $status,
                    'clockIn' => '-',
                    'clockInStatus' => null,
                    'clockInWarning' => $clockInWarning,
                    'clockOut' => '-',
                    'clockOutStatus' => null,
                    'clockOutWarning' => false,
                    'locationIn' => '-',
                    'locationInUrl' => null,
                    'locationOut' => '-',
                    'locationOutUrl' => null,
                    'faceDistance' => null,
                    'clockOutFaceDistance' => null,
                    'workMode' => $this->workContext($user, $date)['mode'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MUser>  $teamUsers
     * @param  Collection<int, TrAttendance>  $todayAttendances
     * @return Collection<int, array<string, mixed>>
     */
    private function teamTodayRows(Collection $teamUsers, Collection $todayAttendances, MAttendanceSetting $setting, Carbon $now): Collection
    {
        $windows = $this->attendanceWindows($setting, $now);
        $isWorkday = $this->isWorkday($now);
        $absenceRequests = $this->approvedAbsenceRequests($teamUsers, $now->copy()->startOfDay(), $now->copy()->startOfDay());

        return $teamUsers
            ->map(function (MUser $user) use ($todayAttendances, $now, $windows, $isWorkday, $absenceRequests) {
                $attendance = $todayAttendances->get($user->intUser_ID);
                $absenceRequest = $absenceRequests->get(($user->intern?->intIntern_ID ?? 0).'|'.$now->format('Y-m-d'));
                $clockInAt = $attendance ? $this->attendanceClockInAt($attendance) : null;
                $clockOutAt = $attendance ? $this->attendanceClockOutAt($attendance) : null;
                $clockInStatus = $attendance ? $this->attendanceClockInStatus($attendance, $windows) : null;
                $status = match (true) {
                    (bool) $attendance => $this->attendanceStatus($attendance, $windows),
                    (bool) $absenceRequest => $absenceRequest->txtWorkFromHomeRequestType,
                    ! $isWorkday => 'Tidak Ada Absensi',
                    $now->gt($windows['clockInLateEnd']) => 'Tidak Masuk',
                    default => 'Belum Clock In',
                };
                $clockInWarning = ! $attendance
                    && ! $absenceRequest
                    && $isWorkday
                    && $now->gt($windows['clockInEnd'])
                    && ! $now->gt($windows['clockInLateEnd']);
                $clockOutWarning = (bool) $clockInAt
                    && ! $clockOutAt
                    && $now->gt($windows['clockOutEnd']);
                $workMode = $attendance?->txtAttendanceWorkMode ?: ($absenceRequest?->txtWorkFromHomeRequestType ?: $this->workContext($user, $now)['mode']);

                return [
                    'name' => $this->displayName($user),
                    'role' => 'Intern',
                    'workMode' => $workMode,
                    'faceRegistered' => (bool) $user->faceEnrollment?->bitActive,
                    'status' => $status,
                    'clockIn' => $this->attendanceTimeLabel($clockInAt),
                    'clockInStatus' => $clockInStatus,
                    'clockInWarning' => $clockInWarning,
                    'clockOut' => $this->attendanceTimeLabel($clockOutAt),
                    'clockOutStatus' => $attendance?->txtAttendanceClockOutStatus,
                    'clockOutWarning' => $clockOutWarning,
                    'locationIn' => $attendance?->txtAttendanceAddress ?: '-',
                    'locationInUrl' => $attendance?->txtAttendanceLocationUrl,
                    'locationOut' => $attendance?->txtAttendanceClockOutAddress ?: '-',
                    'locationOutUrl' => $attendance?->txtAttendanceClockOutLocationUrl,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, MUser>  $users
     * @return Collection<string, TrWorkFromHomeRequest>
     */
    private function approvedAbsenceRequests(Collection $users, Carbon $fromDate, Carbon $toDate): Collection
    {
        $internIds = $users
            ->map(fn (MUser $user) => $user->intern?->intIntern_ID)
            ->filter()
            ->values();

        if ($internIds->isEmpty()) {
            return collect();
        }

        $requests = TrWorkFromHomeRequest::whereIn('intIntern_ID', $internIds)
            ->where('bitActive', true)
            ->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_APPROVED)
            ->whereIn('txtWorkFromHomeRequestType', [TrWorkFromHomeRequest::TYPE_SICK, TrWorkFromHomeRequest::TYPE_PERMISSION])
            ->whereDate('dtmWorkFromHomeRequestStartDate', '<=', $toDate->toDateString())
            ->whereDate('dtmWorkFromHomeRequestEndDate', '>=', $fromDate->toDateString())
            ->get();

        $indexed = collect();

        foreach ($requests as $request) {
            $start = $request->dtmWorkFromHomeRequestStartDate?->copy()->max($fromDate) ?? $fromDate->copy();
            $end = $request->dtmWorkFromHomeRequestEndDate?->copy()->min($toDate) ?? $toDate->copy();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($this->isWorkday($date)) {
                    $indexed->put($request->intIntern_ID.'|'.$date->format('Y-m-d'), $request);
                }
            }
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, MUser>  $teamUsers
     * @return array{filters: array<string, string>, interns: Collection<int, MUser>, rows: Collection<int, array<string, mixed>>, summary: array<string, int>, selectedIntern: ?MUser, payroll: ?array<string, mixed>}
     */
    private function adminAttendanceDetail(Request $request, Collection $teamUsers, MAttendanceSetting $setting, Carbon $now): array
    {
        [$fromDate, $toDate] = $this->detailDateRange($request, $now);
        $selectedUserId = (int) $request->query('intUser_ID', 0);
        $filteredUsers = $selectedUserId > 0
            ? $teamUsers->where('intUser_ID', $selectedUserId)->values()
            : $teamUsers;

        if ($filteredUsers->isEmpty()) {
            $selectedUserId = 0;
            $filteredUsers = $teamUsers;
        }

        $selectedIntern = $selectedUserId > 0
            ? $teamUsers->firstWhere('intUser_ID', $selectedUserId)
            : null;
        $records = TrAttendance::with(['user.intern'])
            ->whereDate('dtmAttendanceDate', '>=', $fromDate->toDateString())
            ->whereDate('dtmAttendanceDate', '<=', $toDate->toDateString())
            ->when($selectedUserId > 0, fn ($query) => $query->where('intUser_ID', $selectedUserId))
            ->get()
            ->keyBy(fn (TrAttendance $attendance) => $attendance->intUser_ID.'|'.$attendance->dtmAttendanceDate?->format('Y-m-d'));
        $approvedAbsenceRequests = $this->approvedAbsenceRequests($filteredUsers, $fromDate, $toDate);
        $rows = collect();
        $summary = $this->emptyDetailSummary();

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            if (! $this->isWorkday($date)) {
                continue;
            }

            $dateWindows = $this->attendanceWindows($setting, $date);

            foreach ($filteredUsers as $user) {
                $internEndDate = $user->intern?->effectiveEndDate();
                $attendance = $records->get($user->intUser_ID.'|'.$date->format('Y-m-d'));
                $absenceRequest = $approvedAbsenceRequests->get(($user->intern?->intIntern_ID ?? 0).'|'.$date->format('Y-m-d'));

                if ($internEndDate && $date->gt($internEndDate) && ! $attendance && ! $absenceRequest) {
                    continue;
                }

                $clockInAt = $attendance ? $this->attendanceClockInAt($attendance) : null;
                $clockOutAt = $attendance ? $this->attendanceClockOutAt($attendance) : null;
                $clockInStatus = $attendance ? $this->attendanceClockInStatus($attendance, $dateWindows) : null;
                $isToday = $date->isSameDay($now);
                $status = match (true) {
                    (bool) $attendance => $this->attendanceStatus($attendance, $dateWindows),
                    (bool) $absenceRequest => $absenceRequest->txtWorkFromHomeRequestType,
                    default => $this->missingAttendanceStatus($date, $now, $dateWindows),
                };
                $clockInWarning = ! $attendance
                    && ! $absenceRequest
                    && $isToday
                    && $now->gt($dateWindows['clockInEnd'])
                    && ! $now->gt($dateWindows['clockInLateEnd']);
                $clockOutWarning = (bool) $clockInAt
                    && ! $clockOutAt
                    && (! $isToday || $now->gt($dateWindows['clockOutEnd']));

                $summary['total'] += 1;

                match ($status) {
                    'Hadir' => $summary['present'] += 1,
                    'Terlambat' => $summary['late'] += 1,
                    'Tidak Masuk' => $summary['absent'] += 1,
                    'Sakit' => $summary['sick'] += 1,
                    'Izin' => $summary['permission'] += 1,
                    default => $summary['pending'] += 1,
                };

                if ($clockOutWarning) {
                    $summary['clockOutWarnings'] += 1;
                }

                $rows->push([
                    'date' => $date->copy(),
                    'intUser_ID' => $user->intUser_ID,
                    'intIntern_ID' => $user->intern?->intIntern_ID,
                    'internNo' => $user->intern?->txtInternNo ?: 'INT-'.str_pad((string) ($user->intern?->intIntern_ID ?? $user->intUser_ID), 3, '0', STR_PAD_LEFT),
                    'name' => $this->displayName($user),
                    'internType' => $user->intern?->txtInternType ?: RoleAccess::INTERN_DIGITALISASI,
                    'internTypeLabel' => $this->internTypeLabel($user->intern?->txtInternType),
                    'dailySalary' => (float) ($user->intern?->floatInternSalary ?? 0),
                    'workMode' => $attendance?->txtAttendanceWorkMode ?: ($absenceRequest?->txtWorkFromHomeRequestType ?: '-'),
                    'status' => $status,
                    'clockIn' => $this->attendanceTimeLabel($clockInAt),
                    'clockInIso' => $clockInAt?->toISOString(),
                    'clockInStatus' => $clockInStatus,
                    'clockInWarning' => $clockInWarning,
                    'clockOut' => $this->attendanceTimeLabel($clockOutAt),
                    'clockOutIso' => $clockOutAt?->toISOString(),
                    'clockOutStatus' => $attendance?->txtAttendanceClockOutStatus,
                    'clockOutWarning' => $clockOutWarning,
                    'locationIn' => $attendance?->txtAttendanceAddress ?: '-',
                    'locationInUrl' => $attendance?->txtAttendanceLocationUrl,
                    'locationOut' => $attendance?->txtAttendanceClockOutAddress ?: '-',
                    'locationOutUrl' => $attendance?->txtAttendanceClockOutLocationUrl,
                    'faceDistance' => $attendance?->floatAttendanceFaceDistance,
                    'clockOutFaceDistance' => $attendance?->floatAttendanceClockOutFaceDistance,
                ]);
            }
        }

        return [
            'filters' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'intUser_ID' => (string) $selectedUserId,
            ],
            'interns' => $teamUsers,
            'rows' => $rows,
            'summary' => $summary,
            'selectedIntern' => $selectedIntern,
            'payroll' => $selectedIntern ? $this->payrollSummary($rows, $selectedIntern, $fromDate, $toDate) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollSummary(Collection $rows, MUser $internUser, Carbon $fromDate, Carbon $toDate): array
    {
        $dailySalary = (float) ($internUser->intern?->floatInternSalary ?? 0);
        $workdays = $rows->count();
        $presentDays = $rows->whereIn('status', ['Hadir', 'Terlambat'])->count();
        $lateDays = $rows->where('status', 'Terlambat')->count();
        $absentDays = $rows->whereIn('status', ['Tidak Masuk', 'Sakit', 'Izin'])->count();
        $pendingDays = $rows->whereNotIn('status', ['Hadir', 'Terlambat', 'Tidak Masuk', 'Sakit', 'Izin'])->count();
        $grossSalary = $dailySalary * $workdays;
        $deduction = $dailySalary * $absentDays;
        $netSalary = max(0, $grossSalary - $deduction);

        return [
            'internName' => $this->displayName($internUser),
            'internNo' => $internUser->intern?->txtInternNo ?: 'INT-'.str_pad((string) ($internUser->intern?->intIntern_ID ?? $internUser->intUser_ID), 3, '0', STR_PAD_LEFT),
            'internType' => $this->internTypeLabel($internUser->intern?->txtInternType),
            'department' => $internUser->intern?->txtDept ?: '-',
            'period' => $fromDate->format('d M Y').' - '.$toDate->format('d M Y'),
            'from' => $fromDate,
            'to' => $toDate,
            'dailySalary' => $dailySalary,
            'workdays' => $workdays,
            'presentDays' => $presentDays,
            'lateDays' => $lateDays,
            'absentDays' => $absentDays,
            'pendingDays' => $pendingDays,
            'paidDays' => max(0, $workdays - $absentDays),
            'grossSalary' => $grossSalary,
            'deduction' => $deduction,
            'netSalary' => $netSalary,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function detailDateRange(Request $request, Carbon $now): array
    {
        $from = $this->dateFromQuery($request->query('from'), $now->copy()->subDays(30));
        $to = $this->dateFromQuery($request->query('to'), $now);

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function dateFromQuery(mixed $value, Carbon $fallback): Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback->copy()->timezone(self::TIMEZONE);
        }

        try {
            return Carbon::parse($value, self::TIMEZONE);
        } catch (Throwable) {
            return $fallback->copy()->timezone(self::TIMEZONE);
        }
    }

    /**
     * @return array<string, int>
     */
    private function emptyDetailSummary(): array
    {
        return [
            'total' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'sick' => 0,
            'permission' => 0,
            'pending' => 0,
            'clockOutWarnings' => 0,
        ];
    }

    /**
     * @param  array{clockInStart: Carbon, clockInEnd: Carbon, clockInLateEnd: Carbon, clockOutStart: Carbon, clockOutEnd: Carbon, clockOutLateEnd: Carbon}  $windows
     */
    private function missingAttendanceStatus(Carbon $date, Carbon $now, array $windows): string
    {
        if ($date->gt($now->copy()->startOfDay())) {
            return 'Belum Clock In';
        }

        if ($date->isSameDay($now) && ! $now->gt($windows['clockInLateEnd'])) {
            return 'Belum Clock In';
        }

        return 'Tidak Masuk';
    }

    /**
     * @param  array{clockInStart: Carbon, clockInEnd: Carbon, clockInLateEnd: Carbon, clockOutStart: Carbon, clockOutEnd: Carbon, clockOutLateEnd: Carbon}  $windows
     */
    private function attendanceStatus(TrAttendance $attendance, array $windows): string
    {
        $clockInStatus = $this->attendanceClockInStatus($attendance, $windows);

        if ($clockInStatus === 'Terlambat') {
            return 'Terlambat';
        }

        if ($clockInStatus === 'Tepat Waktu') {
            return 'Hadir';
        }

        return $attendance->txtAttendanceStatus ?: 'Hadir';
    }

    /**
     * @param  array{clockInStart: Carbon, clockInEnd: Carbon, clockInLateEnd: Carbon, clockOutStart: Carbon, clockOutEnd: Carbon, clockOutLateEnd: Carbon}  $windows
     */
    private function attendanceClockInStatus(TrAttendance $attendance, array $windows): ?string
    {
        if ($attendance->txtAttendanceClockInStatus) {
            return $attendance->txtAttendanceClockInStatus;
        }

        $clockInAt = $this->attendanceClockInAt($attendance);

        if (! $clockInAt) {
            return null;
        }

        return $clockInAt->gt($windows['clockInEnd']) ? 'Terlambat' : 'Tepat Waktu';
    }

    private function attendanceClockInAt(TrAttendance $attendance): ?Carbon
    {
        return $this->attendanceDateTime($attendance, $attendance->dtmAttendanceClockIn);
    }

    private function attendanceClockOutAt(TrAttendance $attendance): ?Carbon
    {
        return $this->attendanceDateTime($attendance, $attendance->dtmAttendanceClockOut);
    }

    private function attendanceDateTime(TrAttendance $attendance, mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $time = $value instanceof Carbon
            ? $value->format('H:i:s')
            : Carbon::parse((string) $value)->format('H:i:s');
        $date = $attendance->dtmAttendanceDate?->format('Y-m-d')
            ?: Carbon::now(self::TIMEZONE)->toDateString();

        return Carbon::parse($date.' '.$time, self::TIMEZONE);
    }

    private function attendanceTimeLabel(?Carbon $value): string
    {
        return $value?->format('H:i') ?? '-';
    }

    private function isWorkday(Carbon $date): bool
    {
        return $date->isWeekday();
    }

    private function displayName(MUser $user): string
    {
        return $user->intern?->txtInternName
            ?? $user->mentor?->txtMentorName
            ?? $user->adminProfile?->txtAdminProfileName
            ?? $user->txtEmail
            ?? 'User';
    }

    private function generatedByName(MUser $user): string
    {
        $profileName = $user->intern?->txtInternName
            ?? $user->mentor?->txtMentorName
            ?? $user->adminProfile?->txtAdminProfileName;

        if ($profileName) {
            return $profileName;
        }

        $nameFromEmail = trim((string) preg_replace('/[._-]+/', ' ', Str::before((string) $user->txtEmail, '@')));

        return $nameFromEmail !== '' ? Str::title($nameFromEmail) : 'User';
    }

    private function internTypeLabel(?string $type): string
    {
        return match (strtolower((string) ($type ?: RoleAccess::INTERN_DIGITALISASI))) {
            RoleAccess::INTERN_REGULAR => 'Regular',
            RoleAccess::INTERN_PKL => 'PKL',
            default => 'Digitalisasi',
        };
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
        $label = 'Lat '.number_format($latitude, 6).', Lng '.number_format($longitude, 6);

        if (is_numeric($accuracy)) {
            $label .= ' +/- '.number_format((float) $accuracy, 0).' m';
        }

        return $label;
    }
}
