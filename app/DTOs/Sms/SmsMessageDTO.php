<?php

namespace App\DTOs\Sms;

readonly class SmsMessageDTO
{
    public function __construct(
        public string  $recipient,
        public string  $content,
        public ?string $senderId = null,
        public bool    $isUnicode = false,
        public array   $options = []
    ) {}
}
