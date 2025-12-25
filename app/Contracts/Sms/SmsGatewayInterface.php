<?php

namespace App\Contracts\Sms;

use App\DTOs\Sms\SmsMessageDTO;

interface SmsGatewayInterface
{
    public function send(SmsMessageDTO $message): bool;
    public function getBalance(): float;
    public function getDeliveryStatus(string $messageId): string;
}
