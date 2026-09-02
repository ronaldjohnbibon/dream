<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\NotificationData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('modules/notifications/Index', [
            'notifications' => $user->notifications()
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn ($notification) => NotificationData::from($notification)),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);

        if (! $item->read_at) {
            $item->markAsRead();
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
