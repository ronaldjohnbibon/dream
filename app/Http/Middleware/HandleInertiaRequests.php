<?php

namespace App\Http\Middleware;

use App\Modules\Notifications\NotificationData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'is_admin']),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'notifications' => fn () => $this->notifications($request),
        ]);
    }

    /** @return array<string, mixed> */
    private function notifications(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return ['unread_count' => 0, 'recent' => []];
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($notification) => NotificationData::from($notification))
                ->values(),
        ];
    }
}
