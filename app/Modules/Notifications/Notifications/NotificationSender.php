<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class NotificationSender
{
    public function send(NotificationDelivery $delivery): NotificationSendResult
    {
        return match ($delivery->channel) {
            NotificationChannel::Email => $this->email($delivery),
            NotificationChannel::Whatsapp => $this->whatsapp($delivery),
            NotificationChannel::Push => new NotificationSendResult(NotificationStatus::Failed, errorCode: 'PUSH_NOT_CONFIGURED'),
        };
    }

    private function email(NotificationDelivery $delivery): NotificationSendResult
    {
        try {
            Mail::raw($delivery->message, fn ($mail) => $mail->to($delivery->recipient_ref)->subject($delivery->subject));
        } catch (Throwable $exception) {
            return new NotificationSendResult(NotificationStatus::Failed, errorCode: 'EMAIL_SEND_FAILED');
        }

        return new NotificationSendResult(NotificationStatus::Sent, 'mail:'.Str::uuid());
    }

    private function whatsapp(NotificationDelivery $delivery): NotificationSendResult
    {
        $endpoint = (string) config('notifications.whatsapp_endpoint', '');
        $token = (string) config('notifications.whatsapp_token', '');
        if ($endpoint === '' || $token === '') {
            return new NotificationSendResult(NotificationStatus::Failed, errorCode: 'WHATSAPP_NOT_CONFIGURED');
        }

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(10)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to' => $this->e164($delivery->recipient_ref),
                'type' => 'text',
                'text' => ['body' => $delivery->message],
            ]);
        } catch (Throwable $exception) {
            return new NotificationSendResult(NotificationStatus::Unknown, errorCode: 'WHATSAPP_OUTCOME_UNKNOWN');
        }

        if ($response->failed()) {
            return new NotificationSendResult(NotificationStatus::Failed, errorCode: 'WHATSAPP_PROVIDER_REJECTED');
        }

        return new NotificationSendResult(NotificationStatus::Sent, (string) ($response->json('messages.0.id') ?? $response->json('id') ?? Str::uuid()));
    }

    private function e164(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return '+'.$digits;
    }
}
