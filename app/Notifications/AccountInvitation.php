<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when an admin creates an account: a password-reset token, worded as a welcome. */
class AccountInvitation extends Notification
{
    public function __construct(public string $token, public string $role) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $roleLine = match ($this->role) {
            'admin' => __('You have been added as an administrator.'),
            'instructor' => __('You have been added as an instructor.'),
            default => __('An account has been created for you.'),
        };

        return (new MailMessage)
            ->subject(__('Welcome to Creative Lab Academy'))
            ->greeting(__('Hi :name,', ['name' => strtok($notifiable->name, ' ')]))
            ->line($roleLine)
            ->line(__('Choose your password to sign in:'))
            ->action(__('Set my password'), $url)
            ->line(__('This link expires in :minutes minutes. If it has expired, use “Forgot password” on the sign-in page.', ['minutes' => $minutes]));
    }
}
