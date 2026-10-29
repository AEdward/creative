<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class EngagementNotification extends Notification
{
    public function __construct(public array $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->message;
    }
}
