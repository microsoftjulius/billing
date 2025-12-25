<?php

namespace App\DTOs\Payment;

readonly class PaymentRequestDTO
{
    public function __construct(
        public float   $amount,
        public string  $currency,
        public string  $customerPhone,
        public ?string $customerEmail,
        public string  $description,
        public array   $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'description' => $this->description,
            'metadata' => $this->metadata,
        ];
    }
}
