<?php

namespace App\Providers;

use App\Models\MUser;
use App\Models\TrNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.topbar', function ($view): void {
            $userId = session('auth_user_id');
            $notifications = collect();
            $unreadCount = 0;

            if ($userId && Schema::hasTable('trNotification')) {
                $user = MUser::with(['intern', 'mentor'])->find($userId);

                if ($user) {
                    app(NotificationService::class)->syncFor($user);
                    $notifications = TrNotification::where('intUser_ID', $user->intUser_ID)
                        ->where('bitActive', true)
                        ->orderByDesc('dtmInserted')
                        ->limit(5)
                        ->get();
                    $unreadCount = TrNotification::where('intUser_ID', $user->intUser_ID)
                        ->where('bitActive', true)
                        ->whereNull('dtmNotificationRead')
                        ->count();
                }
            }

            $view->with([
                'topbarNotifications' => $notifications,
                'topbarUnreadCount' => $unreadCount,
            ]);
        });
    }
}
