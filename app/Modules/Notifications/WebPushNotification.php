<?php

namespace App\Modules\Notifications;

use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WebPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly array $data = [],
    ) {
        $this->afterCommit();
    }

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->iconUrl())
            ->data([...$this->data, 'url' => $this->url]);
    }

    private function iconUrl(): string
    {
        $settings = SystemSetting::current();

        return $settings->logo_path
            ? Storage::disk('r2-public')->url($settings->logo_path)
            : asset('images/arice-icon-192.png');
    }
}
