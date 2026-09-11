<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\PushSubscriptionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate(PushSubscriptionValidator::rules());
        $user      = $request->user();
        $model     = config('webpush.model');
        $model::query()->upsert([
            'endpoint'          => $validated['endpoint'],
            'public_key'        => $validated['keys']['p256dh'],
            'auth_token'        => $validated['keys']['auth'],
            'content_encoding'  => null,
            'subscribable_id'   => $user->getKey(),
            'subscribable_type' => $user->getMorphClass(),
        ], ['endpoint'], ['public_key', 'auth_token', 'content_encoding', 'subscribable_id', 'subscribable_type', 'updated_at']);
        $request->session()->put('push_endpoint', $validated['endpoint']);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);
        if ($request->session()->get('push_endpoint') === $validated['endpoint']) {
            $request->session()->forget('push_endpoint');
        }

        return response()->noContent();
    }
}
