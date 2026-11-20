<?php

namespace App\Providers;

use App\Models\AppNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower($request->input('login')).'|'.$request->ip()));

        View::composer('layouts._shell', function ($view): void {
            static $hasNotificationsTable = null;
            $unreadCount = 0;
            $headerNotifications = collect();

            if (Auth::check()) {
                $hasNotificationsTable ??= Schema::hasTable('app_notifications');
                if ($hasNotificationsTable) {
                    $userId = Auth::id();
                    $unreadCount = AppNotification::where('user_id', $userId)->unread()->count();
                    $headerNotifications = AppNotification::where('user_id', $userId)
                        ->select(['id', 'user_id', 'sender_id', 'title', 'message', 'type', 'action_url', 'read_at', 'created_at'])
                        ->latest()
                        ->limit(5)
                        ->get();
                }
            }

            $view->with([
                'unreadNotificationsCount' => $unreadCount,
                'headerNotifications' => $headerNotifications,
            ]);
        });
    }
}
