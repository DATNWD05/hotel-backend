<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // ✅ Link đặt lại mật khẩu dùng API hoặc frontend của bạn
        $url = url("/reset-password?token={$this->token}&email={$notifiable->getEmailForPasswordReset()}");

        return (new MailMessage)
            ->subject('Yêu cầu đặt lại mật khẩu')
            ->line('Bạn nhận được email này vì đã yêu cầu đặt lại mật khẩu.')
            ->action('Đặt lại mật khẩu', $url)
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này.');
    }
}