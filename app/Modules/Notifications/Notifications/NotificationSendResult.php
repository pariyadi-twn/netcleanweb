<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationStatus;

final readonly class NotificationSendResult
{
    public function __construct(
        public NotificationStatus $status,
        public ?string $providerReference = null,
        public ?string $errorCode = null,
    ) {}
}
