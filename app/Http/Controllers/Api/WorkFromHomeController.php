<?php

namespace App\Http\Controllers\Api;

use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrWorkFromHomeRequest;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkFromHomeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $isAdmin = RoleAccess::isAttendanceAdmin($user);
        abort_unless($isAdmin || RoleAccess::isIntern($user), 403, 'Kamu tidak memiliki akses pengajuan.');
        $query = TrWorkFromHomeRequest::with(['intern.user', 'approver'])
            ->where('bitActive', true)
            ->when(! $isAdmin, fn ($query) => $query->where('intIntern_ID', $user->intern->intIntern_ID))
            ->when($request->filled('status'), fn ($query) => $query->where('txtWorkFromHomeRequestStatus', $request->query('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('txtWorkFromHomeRequestType', $request->query('type')))
            ->orderByDesc('dtmInserted');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($wfh) => $this->wfh($wfh),
            'Daftar pengajuan berhasil diambil.',
        );
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Pengajuan hanya dapat dibuat oleh intern.');
        abort_if($user->intern->hasCompletedInternship(), 422, 'Masa internship sudah selesai sehingga pengajuan tidak tersedia.');

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', TrWorkFromHomeRequest::TYPES)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1500'],
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $type = $validated['type'] ?? TrWorkFromHomeRequest::TYPE_WFH;
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        $effectiveEnd = $user->intern->effectiveEndDate();

        if ($effectiveEnd && $end->gt($effectiveEnd)) {
            throw ValidationException::withMessages(['end_date' => ['Tanggal pengajuan melewati akhir internship.']]);
        }

        $overlap = TrWorkFromHomeRequest::where('intIntern_ID', $user->intern->intIntern_ID)
            ->where('bitActive', true)
            ->whereIn('txtWorkFromHomeRequestStatus', [TrWorkFromHomeRequest::STATUS_PENDING, TrWorkFromHomeRequest::STATUS_APPROVED])
            ->whereDate('dtmWorkFromHomeRequestStartDate', '<=', $end->toDateString())
            ->whereDate('dtmWorkFromHomeRequestEndDate', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => ['Sudah ada pengajuan yang beririsan dengan tanggal tersebut.']]);
        }

        $path = $request->file('attachment')->store('wfh-attachments', 'local');
        $wfh = TrWorkFromHomeRequest::create([
            'intIntern_ID' => $user->intern->intIntern_ID,
            'txtWorkFromHomeRequestType' => $type,
            'dtmWorkFromHomeRequestStartDate' => $start->toDateString(),
            'dtmWorkFromHomeRequestEndDate' => $end->toDateString(),
            'txtWorkFromHomeRequestReason' => $validated['reason'],
            'txtWorkFromHomeRequestAttachment' => $path,
            'txtWorkFromHomeRequestStatus' => TrWorkFromHomeRequest::STATUS_PENDING,
            'bitActive' => true,
            'txtInsertedBy' => $user->txtEmail,
            'dtmInserted' => now(),
        ]);

        MUser::where('bitActive', true)
            ->whereIn('txtRole', [RoleAccess::ROLE_HRD, RoleAccess::ROLE_HEADMASTER])
            ->get()
            ->each(fn (MUser $admin) => $notifications->send(
                $admin,
                'wfh',
                'Pengajuan '.$type.' baru',
                $user->intern->txtInternName.' mengajukan '.$type.'.',
                '/work-from-home',
                'api-wfh-submitted:'.$wfh->intWorkFromHomeRequest_ID.':'.$admin->intUser_ID,
            ));

        return $this->success($this->wfh($wfh->fresh(['intern.user', 'approver'])), 'Pengajuan '.$type.' berhasil dibuat.', [], 201);
    }

    public function approve(Request $request, TrWorkFromHomeRequest $workFromHomeRequest, NotificationService $notifications): JsonResponse
    {
        return $this->review($request, $workFromHomeRequest, TrWorkFromHomeRequest::STATUS_APPROVED, $notifications);
    }

    public function reject(Request $request, TrWorkFromHomeRequest $workFromHomeRequest, NotificationService $notifications): JsonResponse
    {
        return $this->review($request, $workFromHomeRequest, TrWorkFromHomeRequest::STATUS_REJECTED, $notifications);
    }

    public function cancel(Request $request, TrWorkFromHomeRequest $workFromHomeRequest): JsonResponse
    {
        $user = $this->user($request);
        $isOwner = RoleAccess::isIntern($user) && (int) $workFromHomeRequest->intIntern_ID === (int) $user->intern->intIntern_ID;
        abort_unless($isOwner || RoleAccess::isAttendanceAdmin($user), 403, 'Kamu tidak dapat membatalkan pengajuan ini.');
        abort_if($workFromHomeRequest->txtWorkFromHomeRequestStatus !== TrWorkFromHomeRequest::STATUS_PENDING, 422, 'Hanya pengajuan Pending yang dapat dibatalkan.');
        $workFromHomeRequest->update([
            'txtWorkFromHomeRequestStatus' => TrWorkFromHomeRequest::STATUS_CANCELLED,
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return $this->success($this->wfh($workFromHomeRequest->fresh(['intern.user', 'approver'])), 'Pengajuan '.$workFromHomeRequest->typeLabel().' dibatalkan.');
    }

    public function attachment(Request $request, TrWorkFromHomeRequest $workFromHomeRequest): StreamedResponse
    {
        $user = $this->user($request);
        $isOwner = RoleAccess::isIntern($user) && (int) $workFromHomeRequest->intIntern_ID === (int) $user->intern->intIntern_ID;
        abort_unless($isOwner || RoleAccess::isAttendanceAdmin($user), 403);
        $path = $workFromHomeRequest->txtWorkFromHomeRequestAttachment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'lampiran-pengajuan-'.$workFromHomeRequest->intWorkFromHomeRequest_ID.'.'.pathinfo($path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    private function review(Request $request, TrWorkFromHomeRequest $requestModel, string $status, NotificationService $notifications): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(RoleAccess::isAttendanceAdmin($user), 403, 'Hanya HRD atau Headmaster yang dapat meninjau pengajuan.');
        $validated = $request->validate([
            'review_note' => [$status === TrWorkFromHomeRequest::STATUS_REJECTED ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($requestModel, $validated, $user, $status): void {
            $locked = TrWorkFromHomeRequest::lockForUpdate()->findOrFail($requestModel->intWorkFromHomeRequest_ID);
            if ($locked->txtWorkFromHomeRequestStatus === TrWorkFromHomeRequest::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['wfh' => ['Pengajuan yang sudah dibatalkan tidak dapat ditinjau.']]);
            }
            if ($locked->txtWorkFromHomeRequestStatus === TrWorkFromHomeRequest::STATUS_APPROVED
                && $status !== TrWorkFromHomeRequest::STATUS_APPROVED
                && TrAttendance::where('intWorkFromHomeRequest_ID', $locked->intWorkFromHomeRequest_ID)->exists()) {
                throw ValidationException::withMessages(['wfh' => ['Persetujuan tidak dapat dibatalkan karena sudah digunakan untuk absensi WFH.']]);
            }
            if (($locked->txtWorkFromHomeRequestType ?: TrWorkFromHomeRequest::TYPE_WFH) === TrWorkFromHomeRequest::TYPE_WFH
                && $status === TrWorkFromHomeRequest::STATUS_APPROVED
                && $locked->txtWorkFromHomeRequestStatus !== TrWorkFromHomeRequest::STATUS_APPROVED
                && TrAttendance::whereHas('user.intern', fn ($query) => $query->where('intIntern_ID', $locked->intIntern_ID))
                    ->whereBetween('dtmAttendanceDate', [$locked->dtmWorkFromHomeRequestStartDate, $locked->dtmWorkFromHomeRequestEndDate])->exists()) {
                throw ValidationException::withMessages(['wfh' => ['Pengajuan tidak dapat disetujui karena sudah ada absensi pada rentang tersebut.']]);
            }
            $locked->update([
                'txtWorkFromHomeRequestStatus' => $status,
                'intApproverUser_ID' => $user->intUser_ID,
                'dtmWorkFromHomeRequestReviewed' => now(),
                'txtWorkFromHomeRequestReviewNote' => $validated['review_note'] ?? null,
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);
        });

        $updated = $requestModel->fresh(['intern.user', 'approver']);
        $notifications->send(
            $updated->intern->user,
            'wfh',
            $status === TrWorkFromHomeRequest::STATUS_APPROVED ? 'Pengajuan '.$updated->typeLabel().' disetujui' : 'Pengajuan '.$updated->typeLabel().' ditolak',
            $status === TrWorkFromHomeRequest::STATUS_APPROVED ? 'Pengajuan '.$updated->typeLabel().' kamu disetujui.' : 'Pengajuan '.$updated->typeLabel().' kamu ditolak.',
            '/work-from-home',
            'api-wfh-reviewed:'.$updated->intWorkFromHomeRequest_ID.':'.$status.':'.$updated->dtmUpdated?->format('YmdHis'),
        );

        return $this->success($this->wfh($updated), 'Status pengajuan '.$updated->typeLabel().' berhasil diperbarui.');
    }

    private function wfh(TrWorkFromHomeRequest $wfh): array
    {
        $wfh->loadMissing(['intern.user', 'approver']);

        return [
            'id' => (int) $wfh->intWorkFromHomeRequest_ID,
            'intern' => $wfh->intern ? $this->internPayload($wfh->intern) : null,
            'type' => $wfh->txtWorkFromHomeRequestType ?: TrWorkFromHomeRequest::TYPE_WFH,
            'start_date' => $wfh->dtmWorkFromHomeRequestStartDate?->toDateString(),
            'end_date' => $wfh->dtmWorkFromHomeRequestEndDate?->toDateString(),
            'reason' => $wfh->txtWorkFromHomeRequestReason,
            'status' => $wfh->txtWorkFromHomeRequestStatus,
            'review_note' => $wfh->txtWorkFromHomeRequestReviewNote,
            'approver' => $this->person($wfh->approver),
            'reviewed_at' => $wfh->dtmWorkFromHomeRequestReviewed?->toISOString(),
            'attachment_available' => (bool) $wfh->txtWorkFromHomeRequestAttachment,
            'attachment_url' => route('api.v1.wfh.attachment', $wfh->intWorkFromHomeRequest_ID),
            'created_at' => $wfh->dtmInserted?->toISOString(),
        ];
    }
}
