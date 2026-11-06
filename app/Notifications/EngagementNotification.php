<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EngagementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public array $message) {}

    /** Always in-app; also by email for verified, active accounts that haven't switched email off. */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $wantsEmail = NotificationPreference::where('user_id', $notifiable->id)->value('email') !== false;
        if ($wantsEmail && $notifiable->hasVerifiedEmail() && ($notifiable->status ?? 'active') === 'active') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->message;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = (string) ($this->message['url'] ?? '');
        $url = str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : (str_starts_with($url, url('/')) ? $url : route('notifications.index'));

        return (new MailMessage)
            ->subject((string) $this->message['title'])
            ->greeting(__('Hi :name,', ['name' => strtok($notifiable->name, ' ')]))
            ->line((string) ($this->message['body'] ?? ''))
            ->action(__('Open Creative Lab'), $url)
            ->line(__('You can turn off these emails in your notification settings.'));
    }
}
