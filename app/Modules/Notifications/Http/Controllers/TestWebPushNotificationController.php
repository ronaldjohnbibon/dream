<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\WebPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestWebPushNotificationController extends Controller
{
    public function __construct(private readonly SystemLogger $systemLogs) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->routeNotificationForWebPush()->isEmpty()) {
            return response()->json([
                'message' => 'Enable notifications on this device before sending a test.',
            ], 422);
        }

        try {
            $user->notify(new WebPushNotification(
                title: 'aRICE notifications are working',
                body: 'You will receive important order, payment, and points updates on this device.',
                url: route('dashboard'),
                data: ['type' => 'test_notification'],
            ));
        } catch (\Throwable $exception) {
            Log::warning('Unable to queue test web push notification.', [
                'customer_id'     => $user->id,
                'exception_class' => $exception::class,
            ]);
            $this->systemLogs->record(
                type: 'system',
                action: 'push_notification_failed',
                description: 'Test Web Push notification could not be queued.',
                module: 'notifications',
                status: 'failed',
                metadata: [
                    'notification_type' => 'test_notification',
                    'customer_id'       => $user->id,
                    'destination_url'   => route('dashboard'),
                    'error_message'     => 'Test Web Push notification could not be queued.',
                    'exception_class'   => $exception::class,
                ],
            );

            return response()->json(['message' => 'We could not queue a test notification. Please try again.'], 503);
        }

        return response()->json([
            'message' => 'Test notification queued to your enabled devices.',
        ]);
    }
}
