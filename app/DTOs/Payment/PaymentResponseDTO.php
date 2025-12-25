<?php

namespace App\DTOs\Payment;

readonly class PaymentResponseDTO
{
    public function __construct(
        public bool    $success,
        public string  $transactionId,
        public ?string $reference = null,
        public string  $message = '',
        public array   $providerResponse = [],
        public ?string $redirectUrl = null,
        public ?float  $amount = null,
        public ?string $paidAt = null,
        public bool    $requiresMobileConfirmation = false,
        public ?string $paymentMethod = null
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'reference' => $this->reference,
            'message' => $this->message,
            'redirect_url' => $this->redirectUrl,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt,
            'requires_mobile_confirmation' => $this->requiresMobileConfirmation,
            'payment_method' => $this->paymentMethod
        ];
    }
}
