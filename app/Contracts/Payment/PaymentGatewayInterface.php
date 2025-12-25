<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\PaymentRequestDTO;
use App\DTOs\Payment\PaymentResponseDTO;

interface PaymentGatewayInterface
{
    public function initializePayment(PaymentRequestDTO $paymentRequest): PaymentResponseDTO;
    public function verifyPayment(string $transactionId): PaymentResponseDTO;
    public function refundPayment(string $transactionId, float $amount): bool;
    public function getSupportedCurrencies(): array;
}
