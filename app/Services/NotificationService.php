<?php

namespace App\Services;

use App\Models\MIntern;
use App\Models\MUser;
use App\Models\TrInternProject;
use App\Models\TrNotification;
use App\Models\TrWorkFromHomeRequest;
use App\Support\RoleAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    public function syncFor(MUser $user): void
    {
        $user->loadMissing(['intern', 'mentor']);

        if (RoleAccess::isIntern($user)) {
            $this->syncIntern($user);
        }

        if (RoleAccess::isMentor($user)) {
            $this->syncMentor($user);
        }

        if (RoleAccess::isAttendanceAdmin($user)) {
            $this->syncAdministrator($user);
        }
    }

    public function send(MUser $user, string $type, string $title, string $message, ?string $link, string $fingerprint): TrNotification
    {
        $fullFingerprint = 'user:'.$user->intUser_ID.':'.$fingerprint;
        $values = [
            'intUser_ID' => $user->intUser_ID,
            'txtNotificationType' => $type,
            'txtNotificationTitle' => $title,
            'txtNotificationMessage' => $message,
            'txtNotificationLink' => $link,
            'bitActive' => true,
            'txtUpdatedBy' => 'notification-service',
            'dtmUpdated' => now(),
        ];

        $existing = TrNotification::where('txtNotificationFingerprint', $fullFingerprint)->first();

        if ($existing) {
            $existing->update($values);

            return $existing;
        }

        try {
            return TrNotification::create([
                'txtNotificationFingerprint' => $fullFingerprint,
                ...$values,
                'txtInsertedBy' => 'notification-service',
                'dtmInserted' => now(),
            ]);
        } catch (QueryException) {
            $notification = TrNotification::where('txtNotificationFingerprint', $fullFingerprint)->firstOrFail();
            $notification->update($values);

            return $notification;
        }
    }

    private function syncIntern(MUser $user): void
    {
        $today = Carbon::today('Asia/Jakarta');
        $intern = $user->intern;

        TrInternProject::with('project')
            ->where('intIntern_ID', $intern->intIntern_ID)
            ->where('bitActive', true)
            ->get()
            ->filter(fn (TrInternProject $assignment) => $assignment->project?->bitActive && $assignment->project?->dtmProjectEndDate)
            ->each(function (TrInternProject $assignment) use ($user, $today): void {
                $endDate = $assignment->project->dtmProjectEndDate->copy()->startOfDay();
                $days = $this->wholeDaysUntil($today, $endDate);

                if ($days < 0 || $days > 7 || (float) $assignment->floatProgress >= 100) {
                    return;
                }

                $this->send(
                    $user,
                    'project',
                    $days === 0 ? 'Deadline project hari ini' : 'Deadline project makin dekat',
                    $assignment->project->txtProjectName.' berakhir '.($days === 0 ? 'hari ini' : 'dalam '.$days.' hari').'. Progress saat ini '.number_format((float) $assignment->floatProgress, 0).'%.',
                    RoleAccess::can($user, 'projects')
                        ? route('projects.show', $assignment->intProject_ID)
                        : route('dashboard.index'),
                    'project-deadline:'.$assignment->intProject_ID.':'.$endDate->format('Y-m-d'),
                );
            });

        $endDate = $intern->effectiveEndDate();

        if ($endDate) {
            $days = $this->wholeDaysUntil($today, $endDate);

            if ($days >= 0 && $days <= 14) {
                $this->send(
                    $user,
                    'internship',
                    'Masa internship segera selesai',
                    $days === 0 ? 'Hari ini adalah hari terakhir internship kamu.' : 'Masa internship kamu tersisa '.$days.' hari. Pastikan project dan administrasi akhir sudah lengkap.',
                    route('profile.show'),
                    'internship-ending:'.$endDate->format('Y-m-d'),
                );
            }
        }

        $evaluation = $intern->evaluations()->where('bitActive', true)->latest('dtmEvaluationCompleted')->first();

        if ($evaluation) {
            $this->send(
                $user,
                'internship',
                'Rapor akhir internship tersedia',
                'Rapor akhir internship telah selesai. Sertifikat akan tampil setelah diterbitkan oleh mentor atau Headmaster.',
                route('analytics.index'),
                'evaluation-ready:'.$evaluation->intEvaluation_ID,
            );

            if ($evaluation->bitEvaluationCertificatePublished) {
                $this->send(
                    $user,
                    'certificate',
                    'Sertifikat internship sudah diterbitkan',
                    'Sertifikat internship resmi kamu sudah tersedia dan dapat diunduh dari halaman Rapor Intern.',
                    route('analytics.index'),
                    'certificate-published:'.$evaluation->intEvaluation_ID,
                );
            }
        }
    }

    private function syncMentor(MUser $user): void
    {
        $interns = $this->mentorInterns($user);
        $today = Carbon::today('Asia/Jakarta');

        $interns->each(function (MIntern $intern) use ($user, $today): void {
            $endDate = $intern->effectiveEndDate();

            if (! $endDate) {
                return;
            }

            $days = $this->wholeDaysUntil($today, $endDate);

            if ($days < 0 || $days > 14) {
                return;
            }

            $hasReport = $intern->evaluations()->where('bitActive', true)->exists();
            $this->send(
                $user,
                'internship',
                'Intern mendekati masa selesai',
                $intern->txtInternName.' akan menyelesaikan internship '.($days === 0 ? 'hari ini' : 'dalam '.$days.' hari').($hasReport ? '' : ' Rapor akhir belum diisi.'),
                $hasReport ? route('profile.intern.show', $intern) : route('analytics.create', ['intern' => $intern->intIntern_ID]),
                'mentored-intern-ending:'.$intern->intIntern_ID.':'.$endDate->format('Y-m-d'),
            );
        });
    }

    private function syncAdministrator(MUser $user): void
    {
        $today = Carbon::today('Asia/Jakarta');

        TrWorkFromHomeRequest::with('intern')
            ->where('bitActive', true)
            ->where('txtWorkFromHomeRequestStatus', TrWorkFromHomeRequest::STATUS_PENDING)
            ->get()
            ->each(fn (TrWorkFromHomeRequest $request) => $this->send(
                $user,
                'wfh',
                'Pengajuan WFH baru',
                ($request->intern?->txtInternName ?? 'Intern').' mengajukan WFH '.$request->dtmWorkFromHomeRequestStartDate?->format('d M').'–'.$request->dtmWorkFromHomeRequestEndDate?->format('d M Y').'.',
                route('work-from-home.index'),
                'wfh-submitted:'.$request->intWorkFromHomeRequest_ID.':'.$user->intUser_ID,
            ));

        MIntern::with('evaluations')
            ->where('bitActive', true)
            ->get()
            ->each(function (MIntern $intern) use ($user, $today): void {
                $endDate = $intern->effectiveEndDate();

                if (! $endDate) {
                    return;
                }

                $days = $this->wholeDaysUntil($today, $endDate);

                if ($days < 0 || $days > 7) {
                    return;
                }

                $this->send(
                    $user,
                    'internship',
                    'Intern segera lulus',
                    $intern->txtInternName.' memiliki sisa masa internship '.$days.' hari. '.($intern->evaluations->where('bitActive', true)->isEmpty() ? 'Rapor akhir belum tersedia.' : 'Rapor akhir sudah tersedia.'),
                    route('analytics.index'),
                    'admin-intern-ending:'.$intern->intIntern_ID.':'.$endDate->format('Y-m-d'),
                );
            });
    }

    /** @return Collection<int, MIntern> */
    private function mentorInterns(MUser $user): Collection
    {
        if (! $user->mentor) {
            return collect();
        }

        $mentorId = $user->mentor->intMentor_ID;
        $projectIds = $user->mentor->projectMentors()
            ->where('bitActive', true)
            ->pluck('intProject_ID');

        $internIds = TrInternProject::query()
            ->where('bitActive', true)
            ->where(function ($query) use ($mentorId, $projectIds): void {
                $query->where('intMentor_ID', $mentorId);

                if ($projectIds->isNotEmpty()) {
                    $query->orWhereIn('intProject_ID', $projectIds);
                }
            })
            ->pluck('intIntern_ID')
            ->unique();

        return MIntern::with('evaluations')
            ->where('bitActive', true)
            ->whereIn('intIntern_ID', $internIds)
            ->get();
    }

    private function wholeDaysUntil(Carbon $from, Carbon $until): int
    {
        $fromDate = Carbon::parse($from->toDateString(), 'Asia/Jakarta')->startOfDay();
        $untilDate = Carbon::parse($until->toDateString(), 'Asia/Jakarta')->startOfDay();

        return (int) $fromDate->diffInDays($untilDate, false);
    }
}
