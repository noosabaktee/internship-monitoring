<?php

namespace App\Http\Controllers\Api;

use App\Models\TrNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $query = TrNotification::where('intUser_ID', $user->intUser_ID)
            ->where('bitActive', true)
            ->when($request->query('filter') === 'unread', fn ($query) => $query->whereNull('dtmNotificationRead'))
            ->orderByDesc('dtmInserted');

        return $this->paginated(
            $query->paginate(min(100, max(1, $request->integer('per_page', 20)))),
            fn ($notification) => $this->notification($notification),
            'Daftar notifikasi berhasil diambil.',
        );
    }

    public function read(Request $request, TrNotification $notification): JsonResponse
    {
        $user = $this->user($request);
        abort_unless((int) $notification->intUser_ID === (int) $user->intUser_ID, 403);
        $notification->update([
            'dtmNotificationRead' => $notification->dtmNotificationRead ?: now(),
            'txtUpdatedBy' => $user->txtEmail,
            'dtmUpdated' => now(),
        ]);

        return $this->success($this->notification($notification->fresh()), 'Notifikasi ditandai sudah dibaca.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->user($request);
        TrNotification::where('intUser_ID', $user->intUser_ID)
            ->where('bitActive', true)
            ->whereNull('dtmNotificationRead')
            ->update(['dtmNotificationRead' => now(), 'txtUpdatedBy' => $user->txtEmail, 'dtmUpdated' => now()]);

        return $this->success(['unread_count' => 0], 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function notification(TrNotification $notification): array
    {
        return [
            'id' => (int) $notification->intNotification_ID,
            'type' => $notification->txtNotificationType,
            'title' => $notification->txtNotificationTitle,
            'message' => $notification->txtNotificationMessage,
            'link' => $notification->txtNotificationLink,
            'read_at' => $notification->dtmNotificationRead?->toISOString(),
            'is_read' => (bool) $notification->dtmNotificationRead,
            'created_at' => $notification->dtmInserted?->toISOString(),
        ];
    }
}
