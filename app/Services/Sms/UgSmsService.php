<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsGatewayInterface;
use App\DTOs\Sms\SmsMessageDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UgSmsService implements SmsGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;
    private ?string $senderId;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->senderId = $config['sender_id'] ?? null;
    }

    public function send(SmsMessageDTO $message): bool
    {
        try {
            $payload = [
                'api_key' => $this->apiKey,
                'numbers' => $this->formatPhoneNumber($message->recipient),
                'message_body' => $message->content,
                'sender_id' => $message->senderId ?? $this->senderId ?? 'BILLING',
                'unicode' => $message->isUnicode
            ];

            // Add optional parameters
            if (!empty($message->options)) {
                $payload = array_merge($payload, $message->options);
            }

            Log::channel('sms')->debug('UGSMS sending request', [
                'recipient' => $message->recipient,
                'payload' => array_merge($payload, ['api_key' => '***']), // Hide API key in logs
                'message_length' => strlen($message->content)
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(15)
                ->retry(2, 500)
                ->post($this->baseUrl . '/api/v2/sms/send', $payload);

            $responseData = $response->json();

            if (!$response->successful()) {
                throw new \Exception($responseData['message'] ?? 'SMS sending failed');
            }

            if (!$responseData['success']) {
                throw new \Exception($responseData['message'] ?? 'SMS gateway returned error');
            }

            Log::channel('sms')->info('SMS sent successfully', [
                'recipient' => $message->recipient,
                'message_id' => $responseData['data']['message_id'] ?? null,
                'estimated_cost' => $responseData['data']['estimated_cost'] ?? 0,
                'remaining_balance' => $responseData['data']['remaining_balance'] ?? 0
            ]);

            // Cache the message ID for future reference
            if (isset($responseData['data']['message_id'])) {
                Cache::put('sms.message.' . $responseData['data']['message_id'], [
                    'recipient' => $message->recipient,
                    'content' => $message->content,
                    'sent_at' => now(),
                    'status' => 'sent'
                ], now()->addDays(7));
            }

            return true;

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to send SMS via UGSMS', [
                'recipient' => $message->recipient,
                'error' => $e->getMessage(),
                'response' => $responseData ?? null
            ]);

            return false;
        }
    }

    public function sendBulk(array $messages): array
    {
        $results = [];

        foreach ($messages as $index => $message) {
            if (!$message instanceof SmsMessageDTO) {
                continue;
            }

            $results[$index] = [
                'success' => $this->send($message),
                'recipient' => $message->recipient
            ];

            // Small delay between messages to avoid rate limiting
            if ($index < count($messages) - 1) {
                usleep(100000); // 100ms delay
            }
        }

        return $results;
    }

    public function getBalance(): float
    {
        return Cache::remember('ugsms.balance', 300, function () {
            try {
                $payload = [
                    'api_key' => $this->apiKey
                ];

                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(10)
                    ->post($this->baseUrl . '/api/v2/account/balance', $payload);

                $responseData = $response->json();

                if ($response->successful() && $responseData['success']) {
                    return (float) ($responseData['data']['remaining_balance'] ?? 0);
                }

                return 0.0;

            } catch (\Exception $e) {
                Log::channel('sms')->error('Failed to fetch UGSMS balance', [
                    'error' => $e->getMessage()
                ]);

                return 0.0;
            }
        });
    }

    public function getDeliveryStatus(string $messageId): string
    {
        try {
            $payload = [
                'api_key' => $this->apiKey,
                'message_id' => $messageId
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post($this->baseUrl . '/api/v2/sms/status', $payload);

            $responseData = $response->json();

            if ($response->successful() && $responseData['success']) {
                return $responseData['data']['status'] ?? 'unknown';
            }

            return 'unknown';

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to fetch SMS delivery status', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);

            return 'unknown';
        }
    }

    public function getMessageCost(int $messageLength, bool $isUnicode = false): float
    {
        // UGSMS typically charges per SMS segment
        // Standard SMS: 160 chars per segment, Unicode: 70 chars per segment
        $charsPerSegment = $isUnicode ? 70 : 160;
        $segments = ceil($messageLength / $charsPerSegment);

        // Assuming 20 UGX per segment (adjust based on your UGSMS pricing)
        return $segments * 20;
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure it starts with 256 (Uganda country code)
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            return '256' . $phone;
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '256' . substr($phone, 1);
        }

        return $phone;
    }
}
