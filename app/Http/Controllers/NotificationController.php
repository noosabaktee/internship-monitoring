<?php

namespace App\Http\Controllers;

use App\Models\MUser;
use App\Models\TrNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $service): View
    {
        $user = $this->currentUser($request);
        $service->syncFor($user);

        $query = TrNotification::where('intUser_ID', $user->intUser_ID)
            ->where('bitActive', true);

        if ($request->query('filter') === 'unread') {
            $query->whereNull('dtmNotificationRead');
        }

        return view('dashboard.notifications', [
            'notifications' => $query->orderByDesc('dtmInserted')->paginate(20)->withQueryString(),
            'unreadCount' => TrNotification::where('intUser_ID', $user->intUser_ID)->where('bitActive', true)->whereNull('dtmNotificationRead')->count(),
        ]);
    }

    public function read(Request $request, TrNotification $notification): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless($notification->intUser_ID === $user->intUser_ID, 403);

        if (! $notification->dtmNotificationRead) {
            $notification->update([
                'dtmNotificationRead' => now(),
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);
        }

        $link = $notification->txtNotificationLink;

        return $link && str_starts_with($link, url('/'))
            ? redirect()->to($link)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        TrNotification::where('intUser_ID', $user->intUser_ID)
            ->where('bitActive', true)
            ->whereNull('dtmNotificationRead')
            ->update([
                'dtmNotificationRead' => now(),
                'txtUpdatedBy' => $user->txtEmail,
                'dtmUpdated' => now(),
            ]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function currentUser(Request $request): MUser
    {
        return MUser::with(['intern', 'mentor'])->findOrFail($request->session()->get('auth_user_id'));
    }
}
