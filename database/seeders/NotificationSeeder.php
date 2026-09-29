<?php

namespace Database\Seeders;

use App\Models\MUser;
use App\Models\TrNotification;
use App\Support\RoleAccess;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notificationTemplates = [
            ['attendance', 'Rekap absensi siap ditinjau', 'Rekap absensi intern hari ini sudah tersedia untuk ditinjau.', '/attendance?tab=detail'],
            ['wfh', 'Pengajuan WFH baru', 'Terdapat pengajuan WFH intern yang menunggu persetujuan.', '/work-from-home'],
            ['internship', 'Intern baru telah ditambahkan', 'Data intern baru sudah masuk ke sistem dan siap dilengkapi.', '/interns'],
            ['project', 'Progress project diperbarui', 'Seorang intern baru saja memperbarui progress project.', '/projects'],
            ['attendance', 'Intern belum melakukan clock in', 'Masih ada intern aktif yang belum melakukan clock in hari ini.', '/attendance'],
            ['calendar', 'Agenda sharing mendatang', 'Agenda calendar sharing akan berlangsung dalam tiga hari.', '/calendar-sharing'],
            ['certificate', 'Evaluasi akhir siap ditinjau', 'Evaluasi akhir intern telah lengkap dan siap diproses.', '/analytics'],
            ['wfh', 'Pengajuan izin baru', 'Terdapat pengajuan izin yang menunggu keputusan HRD atau Headmaster.', '/work-from-home'],
            ['attendance', 'Data clock out belum lengkap', 'Beberapa intern belum mencatat clock out pada hari kerja terakhir.', '/attendance?tab=detail'],
            ['project', 'Deadline project mendekat', 'Salah satu project intern akan mencapai deadline dalam tujuh hari.', '/projects'],
            ['internship', 'Masa internship akan berakhir', 'Terdapat intern yang masa internship-nya berakhir dalam 14 hari.', '/interns'],
            ['achievement', 'Achievement baru diajukan', 'Pencapaian intern baru telah dicatat dan dapat ditinjau.', '/achievements'],
            ['attendance', 'Laporan pembayaran tersedia', 'Laporan transport berdasarkan kehadiran sudah dapat diekspor.', '/attendance?tab=detail'],
            ['calendar', 'Jadwal sharing diperbarui', 'Perubahan jadwal calendar sharing telah disimpan.', '/calendar-sharing'],
            ['project', 'Project membutuhkan review', 'Progress project telah memasuki tahap review.', '/projects'],
            ['wfh', 'Pengajuan sakit baru', 'Dokumen pengajuan sakit intern tersedia untuk ditinjau.', '/work-from-home'],
            ['internship', 'Perpanjangan internship', 'Periode internship seorang intern baru saja diperpanjang.', '/interns'],
            ['certificate', 'Sertifikat siap diterbitkan', 'Nilai akhir intern sudah lengkap dan sertifikat dapat diterbitkan.', '/analytics'],
            ['attendance', 'Koreksi absensi diajukan', 'Terdapat data absensi yang memerlukan pemeriksaan administrator.', '/attendance?tab=detail'],
            ['project', 'Project telah selesai', 'Seorang intern telah menyelesaikan seluruh target project.', '/projects'],
        ];

        MUser::query()
            ->whereIn('txtRole', [RoleAccess::ROLE_HRD, RoleAccess::ROLE_HEADMASTER])
            ->where('bitActive', true)
            ->orderBy('intUser_ID')
            ->each(function (MUser $user) use ($notificationTemplates): void {
                for ($index = 0; $index < 50; $index++) {
                    [$type, $title, $message, $link] = $notificationTemplates[$index % count($notificationTemplates)];
                    $batch = intdiv($index, count($notificationTemplates)) + 1;
                    $insertedAt = now()->subHours($index * 6);

                    if ($batch > 1) {
                        $message .= ' Pembaruan data ke-'.$batch.'.';
                    }

                    TrNotification::updateOrCreate(
                        ['txtNotificationFingerprint' => 'user:'.$user->intUser_ID.':seed-admin-notification:'.($index + 1)],
                        [
                            'intUser_ID' => $user->intUser_ID,
                            'txtNotificationType' => $type,
                            'txtNotificationTitle' => $title,
                            'txtNotificationMessage' => $message,
                            'txtNotificationLink' => $link,
                            'dtmNotificationRead' => $index % 4 === 0 ? $insertedAt->copy()->addHour() : null,
                            'txtInsertedBy' => 'seeder',
                            'dtmInserted' => $insertedAt,
                            'bitActive' => true,
                        ],
                    );
                }
            });
    }
}
