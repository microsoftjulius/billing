<?php

namespace App\Services;

use App\Contracts\Sms\SmsGatewayInterface;
use App\DTOs\Sms\SmsMessageDTO;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private SmsGatewayInterface $smsGateway;

    public function __construct(SmsGatewayInterface $smsGateway)
    {
        $this->smsGateway = $smsGateway;
    }

    public function sendVoucher(string $phone, Voucher $voucher): bool
    {
        try {
            $message = $this->generateVoucherMessage($voucher);

            $smsDTO = new SmsMessageDTO(
                recipient: $phone,
                content: $message,
                senderId: config('services.ugsms.sender_id', 'BILLING'),
                isUnicode: false,
                options: [
                    'message_type' => 'voucher_delivery',
                    'voucher_id' => $voucher->id
                ]
            );

            $sent = $this->smsGateway->send($smsDTO);

            if ($sent) {
                Log::channel('sms')->info('Voucher SMS sent', [
                    'voucher_id' => $voucher->id,
                    'phone' => $phone,
                    'voucher_code' => $voucher->code
                ]);

                $voucher->update(['sms_sent_at' => now()]);
            }

            return $sent;

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to send voucher SMS', [
                'voucher_id' => $voucher->id,
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send voucher transfer notification
     */
    public function sendVoucherTransfer(string $phone, Voucher $voucher, ?string $fromCustomer = null): bool
    {
        try {
            $message = $this->generateVoucherTransferMessage($voucher, $fromCustomer);

            $smsDTO = new SmsMessageDTO(
                recipient: $phone,
                content: $message,
                senderId: config('services.ugsms.sender_id', 'BILLING'),
                isUnicode: false,
                options: [
                    'message_type' => 'voucher_transfer',
                    'voucher_id' => $voucher->id,
                    'from_customer' => $fromCustomer
                ]
            );

            $sent = $this->smsGateway->send($smsDTO);

            if ($sent) {
                Log::channel('sms')->info('Voucher transfer SMS sent', [
                    'voucher_id' => $voucher->id,
                    'phone' => $phone,
                    'voucher_code' => $voucher->code,
                    'from_customer' => $fromCustomer
                ]);
            }

            return $sent;

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to send voucher transfer SMS', [
                'voucher_id' => $voucher->id,
                'phone' => $phone,
                'from_customer' => $fromCustomer,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendPaymentConfirmation(string $phone, float $amount, string $transactionId): bool
    {
        $message = "Payment of UGX " . number_format($amount) . " received. Transaction ID: {$transactionId}. Voucher will be sent shortly.";

        $smsDTO = new SmsMessageDTO(
            recipient: $phone,
            content: $message,
            senderId: config('services.ugsms.sender_id', 'BILLING')
        );

        return $this->smsGateway->send($smsDTO);
    }

    public function sendLowBalanceAlert(float $balance): bool
    {
        $adminPhone = config('app.admin_phone');

        if (!$adminPhone) {
            return false;
        }

        $message = "ALERT: SMS balance is low. Current balance: UGX " . number_format($balance) . ". Please top up.";

        $smsDTO = new SmsMessageDTO(
            recipient: $adminPhone,
            content: $message,
            senderId: 'ALERT'
        );

        return $this->smsGateway->send($smsDTO);
    }

    public function send(SmsMessageDTO $smsMessage): bool
    {
        return $this->smsGateway->send($smsMessage);
    }

    private function generateVoucherMessage(Voucher $voucher): string
    {
        $expiryText = $voucher->expires_at 
            ? $voucher->expires_at->format('Y-m-d H:i')
            : 'Not set';
            
        return "Your internet voucher:\n"
            . "Code: {$voucher->code}\n"
            . "Password: {$voucher->password}\n"
            . "Valid for: {$voucher->validity_hours} hours\n"
            . "Profile: {$voucher->profile}\n"
            . "Expires: {$expiryText}\n"
            . "Thank you for your payment!";
    }

    /**
     * Generate voucher transfer message
     */
    private function generateVoucherTransferMessage(Voucher $voucher, ?string $fromCustomer = null): string
    {
        $expiryText = $voucher->expires_at 
            ? $voucher->expires_at->format('Y-m-d H:i')
            : 'Not set';
            
        $transferText = $fromCustomer ? "from {$fromCustomer}" : "from another customer";
            
        return "Voucher transferred to you {$transferText}:\n"
            . "Code: {$voucher->code}\n"
            . "Password: {$voucher->password}\n"
            . "Valid for: {$voucher->validity_hours} hours\n"
            . "Profile: {$voucher->profile}\n"
            . "Expires: {$expiryText}\n"
            . "Enjoy your internet access!";
    }

    public function checkBalance(): float
    {
        return $this->smsGateway->getBalance();
    }

    public function getMessageCost(string $message): float
    {
        return $this->smsGateway->getMessageCost(strlen($message));
    }
}