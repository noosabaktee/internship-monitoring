<?php

namespace App\Http\Controllers;

use App\Models\MAttendanceLocation;
use App\Models\MAttendanceSetting;
use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrWorkFromHomeRequest;
use App\Services\FaceRecognitionService;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

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
            $todayAttendances = TrAttendance::with(['user.intern', 'user.mentor'])
                ->whereDate('dtmAttendanceDate', $now->toDateString())
                ->get()
                ->keyBy('intUser_ID');

            $teamTodayRows = $this->teamTodayRows($teamUsers, $todayAttendances, $setting, $now);
            $attendanceDetail = $this->adminAttendanceDetail($request, $teamUsers, $setting, $now);
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

    public function checkIn(Request $request): RedirectResponse
    {
        $authUser = $this->currentUser($request);
        $this->ensureInternCanAttend($authUser);

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $windows = $this->attendanceWindows($setting, $now);
        $workContext = $this->workContext($authUser, $now);

        if (! $this->isWorkday($now)) {
            return back()->withErrors([
                'attendance' => 'Clock In hanya tersedia pada hari kerja Senin-Jumat.',
            ]);
        }

        if ($now->lt($windows['clockInStart'])) {
            return back()->withErrors([
                'attendance' => 'Clock In baru bisa dilakukan mulai '.$windows['clockInStart']->format('H:i').' WIB.',
            ]);
        }

        $alreadyCheckedIn = TrAttendance::where('intUser_ID', $authUser->intUser_ID)
            ->whereDate('dtmAttendanceDate', $now->toDateString())
            ->whereNotNull('dtmAttendanceClockIn')
            ->exists();

        if ($alreadyCheckedIn) {
            return back()->withErrors(['attendance' => 'Clock In hari ini sudah tercatat.']);
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

        TrAttendance::create($attendanceData);

        $message = match (true) {
            $shouldAutoClockOut => 'Clock In terlambat berhasil dicatat dan Clock Out otomatis tersimpan.',
            $status === 'Terlambat' => 'Clock In berhasil dicatat dengan status Terlambat.',
            default => 'Clock In berhasil dicatat.',
        };

        return redirect()->route('attendance.index')->with('success', $message);
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $authUser = $this->currentUser($request);
        $this->ensureInternCanAttend($authUser);

        $setting = $this->attendanceSetting();
        $now = Carbon::now(self::TIMEZONE);
        $windows = $this->attendanceWindows($setting, $now);

        if (! $this->isWorkday($now)) {
            return back()->withErrors([
                'attendance' => 'Clock Out hanya tersedia pada hari kerja Senin-Jumat.',
            ]);
        }

        $attendance = TrAttendance::where('intUser_ID', $authUser->intUser_ID)
            ->whereDate('dtmAttendanceDate', $now->toDateString())
            ->first();

        if (! $attendance?->dtmAttendanceClockIn) {
            return back()->withErrors(['attendance' => 'Clock In terlebih dahulu sebelum Clock Out.']);
        }

        if ($attendance->dtmAttendanceClockOut) {
            return back()->withErrors(['attendance' => 'Clock Out hari ini sudah tercatat.']);
        }

        if ($now->lt($windows['clockOutStart'])) {
            return back()->withErrors([
                'attendance' => 'Clock Out baru bisa dilakukan mulai '.$windows['clockOutStart']->format('H:i').' WIB.',
            ]);
        }

        $clockInAt = $this->attendanceClockInAt($attendance);

        if ($clockInAt?->gt($windows['clockOutEnd'])) {
            return back()->withErrors([
                'attendance' => 'Clock In tercatat setelah batas Clock Out, jadi Clock Out hari ini tidak tersedia.',
            ]);
        }

        if ($now->gt($windows['clockOutLateEnd'])) {
            return back()->withErrors([
                'attendance' => 'Batas Clock Out terlambat hari ini sudah lewat pada 23:59 WIB.',
            ]);
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

        return redirect()->route('attendance.index')->with('success', $message);
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

        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', 'Kalbe Internship Attendance Report');
        $sheet->setCellValue('A2', 'Period');
        $sheet->setCellValue('B2', $payload['filters']['from'].' to '.$payload['filters']['to']);
        $sheet->setCellValue('D2', 'Generated');
        $sheet->setCellValue('E2', $payload['generatedAt']->format('d M Y H:i').' WIB');
        $sheet->setCellValue('H2', 'Generated By');
        $sheet->setCellValue('I2', $payload['generatedBy']);

        $summaryLabels = ['Workdays', 'Present', 'Late', 'Absent', 'Pending', 'Out Warning'];
        $summaryValues = [
            $payload['summary']['total'],
            $payload['summary']['present'],
            $payload['summary']['late'],
            $payload['summary']['absent'],
            $payload['summary']['pending'],
            $payload['summary']['clockOutWarnings'],
        ];

        foreach ($summaryLabels as $index => $label) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'4', $label);
            $sheet->setCellValue($column.'5', $summaryValues[$index]);
        }

        if ($payload['payroll']) {
            $sheet->mergeCells('H4:L4');
            $sheet->setCellValue('H4', 'Payroll Snapshot');
            $sheet->setCellValue('H5', 'Intern');
            $sheet->setCellValue('I5', $payload['payroll']['internName']);
            $sheet->setCellValue('H6', 'Gross');
            $sheet->setCellValue('I6', $payload['payroll']['grossSalary']);
            $sheet->setCellValue('J6', 'Deduction');
            $sheet->setCellValue('K6', $payload['payroll']['deduction']);
            $sheet->setCellValue('H7', 'Net Salary');
            $sheet->setCellValue('I7', $payload['payroll']['netSalary']);
            $sheet->getStyle('I6:K7')->getNumberFormat()->setFormatCode('"Rp" #,##0');
        }

        $headers = [
            'Date',
            'Intern No',
            'Intern',
            'Type',
            'Work Mode',
            'Status',
            'Clock In',
            'Clock In Status',
            'Clock Out',
            'Clock Out Status',
            'Salary / Day',
            'Location In',
            'Location Out',
        ];
        $headerRow = 9;
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $rowNumber = $headerRow + 1;

        foreach ($payload['rows'] as $row) {
            $sheet->fromArray([
                $row['date']->format('Y-m-d'),
                $row['internNo'],
                $row['name'],
                $row['internTypeLabel'],
                $row['workMode'] === 'WFH' ? 'WFH' : 'WFO',
                $row['status'],
                $row['clockIn'],
                $row['clockInStatus'] ?: '-',
                $row['clockOut'],
                $row['clockOutStatus'] ?: '-',
                (float) $row['dailySalary'],
                $row['locationIn'],
                $row['locationOut'],
            ], null, 'A'.$rowNumber);

            $statusColor = match ($row['status']) {
                'Tidak Masuk' => 'FEE4E2',
                'Terlambat' => 'FEF0C7',
                'Hadir' => 'D1FADF',
                default => 'EAECF0',
            };
            $sheet->getStyle('F'.$rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);
            $rowNumber++;
        }

        $lastRow = max($headerRow + 1, $rowNumber - 1);
        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A'.$headerRow.':M'.$lastRow);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('12351F');
        $sheet->getStyle('A2:I2')->getFont()->setBold(true);
        $sheet->getStyle('A4:F4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('006838');
        $sheet->getStyle('A5:F5')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:F5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CFE5BD');
        $sheet->getStyle('A'.$headerRow.':M'.$headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A'.$headerRow.':M'.$headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('12351F');
        $sheet->getStyle('A'.$headerRow.':M'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E2D0');
        $sheet->getStyle('K10:K'.$lastRow)->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle('A4:M'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('L10:M'.$lastRow)->getAlignment()->setWrapText(true);

        if ($payload['payroll']) {
            $sheet->getStyle('H4:L4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('H4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('006838');
            $sheet->getStyle('H5:L7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CFE5BD');
            $sheet->getStyle('H5:H7')->getFont()->setBold(true);
            $sheet->getStyle('J6')->getFont()->setBold(true);
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getColumnDimension('L')->setWidth(38);
        $sheet->getColumnDimension('M')->setWidth(38);
        $sheet->getStyle('A1:M'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
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

        return $this->pdfResponse('dashboard.attendance.report-pdf', ['payload' => $payload], $filename);
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

    private function pdfResponse(string $view, array $data, string $filename): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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
        $teamUsers = $this->attendanceInternUsers();
        $detail = $this->adminAttendanceDetail($request, $teamUsers, $setting, $now);

        return [
            'generatedAt' => $now,
            'generatedBy' => $this->generatedByName($authUser),
            'filters' => $detail['filters'],
            'interns' => $detail['interns'],
            'rows' => $detail['rows'],
            'summary' => $detail['summary'],
            'selectedIntern' => $detail['selectedIntern'],
            'payroll' => $detail['payroll'],
        ];
    }

    private function currentUser(Request $request): MUser
    {
        return MUser::with(['intern', 'mentor', 'adminProfile'])->findOrFail($request->session()->get('auth_user_id'));
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

        return collect(range(0, 4))
            ->map(function (int $offset) use ($records, $now, $weekStart, $setting, $user) {
                $date = $weekStart->copy()->addDays($offset);
                $endDate = $user->intern?->effectiveEndDate();

                if ($endDate && $date->gt($endDate)) {
                    return null;
                }

                $dateWindows = $this->attendanceWindows($setting, $date);
                $dateKey = $date->format('Y-m-d');
                $attendance = $records->get($dateKey);
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

        return $teamUsers
            ->map(function (MUser $user) use ($todayAttendances, $now, $windows, $isWorkday) {
                $attendance = $todayAttendances->get($user->intUser_ID);
                $clockInAt = $attendance ? $this->attendanceClockInAt($attendance) : null;
                $clockOutAt = $attendance ? $this->attendanceClockOutAt($attendance) : null;
                $clockInStatus = $attendance ? $this->attendanceClockInStatus($attendance, $windows) : null;
                $status = match (true) {
                    (bool) $attendance => $this->attendanceStatus($attendance, $windows),
                    ! $isWorkday => 'Tidak Ada Absensi',
                    $now->gt($windows['clockInLateEnd']) => 'Tidak Masuk',
                    default => 'Belum Clock In',
                };
                $clockInWarning = ! $attendance
                    && $isWorkday
                    && $now->gt($windows['clockInEnd'])
                    && ! $now->gt($windows['clockInLateEnd']);
                $clockOutWarning = (bool) $clockInAt
                    && ! $clockOutAt
                    && $now->gt($windows['clockOutEnd']);
                $workMode = $attendance?->txtAttendanceWorkMode ?: $this->workContext($user, $now)['mode'];

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
        $rows = collect();
        $summary = $this->emptyDetailSummary();

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            if (! $this->isWorkday($date)) {
                continue;
            }

            $dateWindows = $this->attendanceWindows($setting, $date);

            foreach ($filteredUsers as $user) {
                $internEndDate = $user->intern?->effectiveEndDate();

                if ($internEndDate && $date->gt($internEndDate)) {
                    continue;
                }

                $attendance = $records->get($user->intUser_ID.'|'.$date->format('Y-m-d'));
                $clockInAt = $attendance ? $this->attendanceClockInAt($attendance) : null;
                $clockOutAt = $attendance ? $this->attendanceClockOutAt($attendance) : null;
                $clockInStatus = $attendance ? $this->attendanceClockInStatus($attendance, $dateWindows) : null;
                $isToday = $date->isSameDay($now);
                $status = $attendance
                    ? $this->attendanceStatus($attendance, $dateWindows)
                    : $this->missingAttendanceStatus($date, $now, $dateWindows);
                $clockInWarning = ! $attendance
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
                    'workMode' => $attendance?->txtAttendanceWorkMode ?: '-',
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
        $absentDays = $rows->where('status', 'Tidak Masuk')->count();
        $pendingDays = $rows->whereNotIn('status', ['Hadir', 'Terlambat', 'Tidak Masuk'])->count();
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
        $from = $this->dateFromQuery($request->query('from'), $now->copy()->startOfMonth());
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
        } catch (\Throwable) {
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
