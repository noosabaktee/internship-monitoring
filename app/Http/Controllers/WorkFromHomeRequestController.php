<?php

namespace App\Http\Controllers;

use App\Models\MUser;
use App\Models\TrAttendance;
use App\Models\TrWorkFromHomeRequest;
use App\Services\NotificationService;
use App\Support\RoleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkFromHomeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->currentUser($request);
        $isAdmin = RoleAccess::isAttendanceAdmin($user);
        $requestTypes = $this->requestTypeOptions();
        $query = TrWorkFromHomeRequest::with(['intern.user', 'approver'])
            ->where('bitActive', true);

        if (! $isAdmin) {
            abort_unless(RoleAccess::isIntern($user), 403);
            $query->where('intIntern_ID', $user->intern->intIntern_ID);
        }

        if ($request->filled('status')) {
            $query->where('txtWorkFromHomeRequestStatus', $request->query('status'));
        }

        if ($request->filled('type') && array_key_exists($request->query('type'), $requestTypes)) {
            $query->where('txtWorkFromHomeRequestType', $request->query('type'));
        }

        $requests = $query->orderByDesc('dtmInserted')->paginate(20)->withQueryString();
        $statsQuery = TrWorkFromHomeRequest::where('bitActive', true);

        if (! $isAdmin) {
            $statsQuery->where('intIntern_ID', $user->intern->intIntern_ID);
        }

        return view('dashboard.work-from-home', [
            'authUser' => $user,
            'isAdmin' => $isAdmin,
            'requests' => $requests,
            'requestTypes' => $requestTypes,
            'stats' => [
                'pending' => (clone $statsQuery)->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_PENDING)->count(),
                'approved' => (clone $statsQuery)->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_APPROVED)->count(),
                'rejected' => (clone $statsQuery)->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_REJECTED)->count(),
            ],
            'typeStats' => collect(array_keys($requestTypes))
                ->mapWithKeys(fn (string $type) => [
                    $type => (clone $statsQuery)->where('txtWorkFromHomeRequestType', $type)->count(),
                ])
                ->all(),
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::isIntern($user), 403, 'Pengajuan hanya dapat dibuat oleh intern.');

        if ($user->intern->hasCompletedInternship()) {
            throw ValidationException::withMessages(['wfh' => 'Masa internship sudah selesai sehingga pengajuan tidak tersedia.']);
        }

        $validated = $request->validate([
            'txtWorkFromHomeRequestType' => ['nullable', 'string', 'in:'.implode(',', TrWorkFromHomeRequest::TYPES)],
            'dtmWorkFromHomeRequestStartDate' => ['required', 'date', 'after_or_equal:today'],
            'dtmWorkFromHomeRequestEndDate' => ['required', 'date', 'after_or_equal:dtmWorkFromHomeRequestStartDate'],
            'txtWorkFromHomeRequestReason' => ['required', 'string', 'max:1500'],
            'txtWorkFromHomeRequestAttachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $type = $validated['txtWorkFromHomeRequestType'] ?? TrWorkFromHomeRequest::TYPE_WFH;
        $typeLabel = $this->requestTypeLabel($type);
        $start = Carbon::parse($validated['dtmWorkFromHomeRequestStartDate'])->startOfDay();
        $end = Carbon::parse($validated['dtmWorkFromHomeRequestEndDate'])->startOfDay();
        $effectiveEnd = $user->intern->effectiveEndDate();

        if ($effectiveEnd && $end->gt($effectiveEnd)) {
            throw ValidationException::withMessages([
                'dtmWorkFromHomeRequestEndDate' => 'Tanggal pengajuan tidak boleh melewati akhir internship '.$effectiveEnd->format('d M Y').'.',
            ]);
        }

        $overlap = TrWorkFromHomeRequest::where('intIntern_ID', $user->intern->intIntern_ID)
            ->where('bitActive', true)
            ->whereIn('txtWorkFromHomeRequestStatus', [TrWorkFromHomeRequest::STATUS_PENDING, TrWorkFromHomeRequest::STATUS_APPROVED])
            ->whereDate('dtmWorkFromHomeRequestStartDate', '<=', $end->toDateString())
            ->whereDate('dtmWorkFromHomeRequestEndDate', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['dtmWorkFromHomeRequestStartDate' => 'Sudah ada pengajuan yang beririsan dengan tanggal tersebut.']);
        }

        $attachment = $request->file('txtWorkFromHomeRequestAttachment')?->store('wfh-attachments', 'local');

        if (! $attachment) {
            throw ValidationException::withMessages(['txtWorkFromHomeRequestAttachment' => 'Lampiran gagal disimpan.']);
        }

        $wfhRequest = TrWorkFromHomeRequest::create([
            'intIntern_ID' => $user->intern->intIntern_ID,
            'txtWorkFromHomeRequestType' => $type,
            'dtmWorkFromHomeRequestStartDate' => $start->toDateString(),
            'dtmWorkFromHomeRequestEndDate' => $end->toDateString(),
            'txtWorkFromHomeRequestReason' => $validated['txtWorkFromHomeRequestReason'],
            'txtWorkFromHomeRequestAttachment' => $attachment,
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
                'Pengajuan '.$typeLabel.' baru',
                $user->intern->txtInternName.' mengajukan '.$typeLabel.' '.$start->format('d M').'–'.$end->format('d M Y').'.',
                route('work-from-home.index'),
                'wfh-submitted:'.$wfhRequest->intWorkFromHomeRequest_ID.':'.$admin->intUser_ID,
            ));

        return back()->with('success', 'Pengajuan '.$typeLabel.' berhasil dikirim untuk ditinjau HRD/Headmaster.');
    }

    public function approve(Request $request, TrWorkFromHomeRequest $workFromHomeRequest, NotificationService $notifications): RedirectResponse
    {
        return $this->review($request, $workFromHomeRequest, TrWorkFromHomeRequest::STATUS_APPROVED, $notifications);
    }

    public function reject(Request $request, TrWorkFromHomeRequest $workFromHomeRequest, NotificationService $notifications): RedirectResponse
    {
        return $this->review($request, $workFromHomeRequest, TrWorkFromHomeRequest::STATUS_REJECTED, $notifications);
    }

    public function cancel(Request $request, TrWorkFromHomeRequest $workFromHomeRequest): RedirectResponse
    {
        $user = $this->currentUser($request);
        $isOwner = RoleAccess::isIntern($user) && $user->intern->intIntern_ID === $workFromHomeRequest->intIntern_ID;
        abort_unless($isOwner || RoleAccess::isAttendanceAdmin($user), 403);

        if ($workFromHomeRequest->txtWorkFromHomeRequestStatus !== TrWorkFromHomeRequest::STATUS_PENDING) {
            return back()->withErrors(['wfh' => 'Hanya pengajuan Pending yang dapat dibatalkan.']);
        }

        $workFromHomeRequest->update([
            'txtWorkFromHomeRequestStatus' => TrWorkFromHomeRequest::STATUS_CANCELLED,
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return back()->with('success', 'Pengajuan '.$this->requestTypeLabel($workFromHomeRequest->txtWorkFromHomeRequestType).' dibatalkan.');
    }

    public function attachment(Request $request, TrWorkFromHomeRequest $workFromHomeRequest): StreamedResponse
    {
        $user = $this->currentUser($request);
        $isOwner = RoleAccess::isIntern($user) && $user->intern->intIntern_ID === $workFromHomeRequest->intIntern_ID;
        abort_unless($isOwner || RoleAccess::isAttendanceAdmin($user), 403);

        $path = $workFromHomeRequest->txtWorkFromHomeRequestAttachment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            'lampiran-pengajuan-'.$workFromHomeRequest->intWorkFromHomeRequest_ID.'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['X-Content-Type-Options' => 'nosniff'],
            'inline',
        );
    }

    private function review(Request $request, TrWorkFromHomeRequest $workFromHomeRequest, string $status, NotificationService $notifications): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(RoleAccess::isAttendanceAdmin($user), 403);
        $validated = $request->validate([
            'txtWorkFromHomeRequestReviewNote' => [$status === TrWorkFromHomeRequest::STATUS_REJECTED ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($workFromHomeRequest, $validated, $user, $status): void {
            $locked = TrWorkFromHomeRequest::lockForUpdate()->findOrFail($workFromHomeRequest->intWorkFromHomeRequest_ID);

            if ($locked->txtWorkFromHomeRequestStatus === TrWorkFromHomeRequest::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['wfh' => 'Pengajuan yang sudah dibatalkan tidak dapat ditinjau ulang.']);
            }

            if ($locked->txtWorkFromHomeRequestStatus === TrWorkFromHomeRequest::STATUS_APPROVED
                && $status !== TrWorkFromHomeRequest::STATUS_APPROVED) {
                $hasUsedWfhAttendance = TrAttendance::where('intWorkFromHomeRequest_ID', $locked->intWorkFromHomeRequest_ID)->exists();

                if ($hasUsedWfhAttendance) {
                    throw ValidationException::withMessages([
                        'wfh' => 'Persetujuan tidak dapat dibatalkan karena sudah digunakan untuk absensi WFH.',
                    ]);
                }
            }

            if (($locked->txtWorkFromHomeRequestType ?: TrWorkFromHomeRequest::TYPE_WFH) === TrWorkFromHomeRequest::TYPE_WFH
                && $status === TrWorkFromHomeRequest::STATUS_APPROVED
                && $locked->txtWorkFromHomeRequestStatus !== TrWorkFromHomeRequest::STATUS_APPROVED) {
                $hasAttendance = TrAttendance::whereHas('user.intern', fn ($query) => $query->where('intIntern_ID', $locked->intIntern_ID))
                    ->whereBetween('dtmAttendanceDate', [$locked->dtmWorkFromHomeRequestStartDate, $locked->dtmWorkFromHomeRequestEndDate])
                    ->exists();

                if ($hasAttendance) {
                    throw ValidationException::withMessages(['wfh' => 'Pengajuan tidak dapat disetujui karena sudah ada absensi pada rentang tersebut.']);
                }
            }

            $locked->update([
                'txtWorkFromHomeRequestStatus' => $status,
                'intApproverUser_ID' => $user->intUser_ID,
                'dtmWorkFromHomeRequestReviewed' => now(),
                'txtWorkFromHomeRequestReviewNote' => $validated['txtWorkFromHomeRequestReviewNote'] ?? null,
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);
        });

        $workFromHomeRequest->refresh()->load('intern.user');
        $typeLabel = $this->requestTypeLabel($workFromHomeRequest->txtWorkFromHomeRequestType);
        $notifications->send(
            $workFromHomeRequest->intern->user,
            'wfh',
            $status === TrWorkFromHomeRequest::STATUS_APPROVED ? 'Pengajuan '.$typeLabel.' disetujui' : 'Pengajuan '.$typeLabel.' ditolak',
            match (true) {
                $status !== TrWorkFromHomeRequest::STATUS_APPROVED => 'Buka detail pengajuan untuk melihat catatan peninjau.',
                ($workFromHomeRequest->txtWorkFromHomeRequestType ?: TrWorkFromHomeRequest::TYPE_WFH) === TrWorkFromHomeRequest::TYPE_WFH => 'Kamu dapat melakukan absensi dari mana saja selama periode WFH yang disetujui.',
                default => 'Kamu tidak perlu melakukan absensi pada periode '.$typeLabel.' yang disetujui.',
            },
            route('work-from-home.index'),
            'wfh-reviewed:'.$workFromHomeRequest->intWorkFromHomeRequest_ID.':'.$status.':'.$workFromHomeRequest->dtmUpdated?->format('YmdHis'),
        );

        return back()->with('success', $status === TrWorkFromHomeRequest::STATUS_APPROVED
            ? 'Keputusan pengajuan diperbarui menjadi Disetujui.'
            : 'Keputusan pengajuan diperbarui menjadi Ditolak.');
    }

    private function currentUser(Request $request): MUser
    {
        return MUser::with(['intern', 'mentor', 'adminProfile'])->findOrFail($request->session()->get('auth_user_id'));
    }

    /**
     * @return array<string, array{label: string, icon: string, help: string}>
     */
    private function requestTypeOptions(): array
    {
        return [
            TrWorkFromHomeRequest::TYPE_WFH => [
                'label' => 'WFH',
                'icon' => 'fa-house-laptop',
                'help' => 'Tetap wajib clock in dan clock out dari lokasi pilihan.',
            ],
            TrWorkFromHomeRequest::TYPE_SICK => [
                'label' => 'Sakit',
                'icon' => 'fa-briefcase-medical',
                'help' => 'Jika disetujui, tidak perlu melakukan absensi.',
            ],
            TrWorkFromHomeRequest::TYPE_PERMISSION => [
                'label' => 'Izin',
                'icon' => 'fa-person-walking-arrow-right',
                'help' => 'Jika disetujui, tidak perlu melakukan absensi.',
            ],
        ];
    }

    private function requestTypeLabel(?string $type): string
    {
        return $this->requestTypeOptions()[$type ?: TrWorkFromHomeRequest::TYPE_WFH]['label'] ?? 'WFH';
    }
}
