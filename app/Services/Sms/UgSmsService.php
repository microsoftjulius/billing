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
    private int $retryAttempts;
    private int $retryDelay;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->senderId = $config['sender_id'] ?? null;
        $this->retryAttempts = $config['retry_attempts'] ?? 2;
        $this->retryDelay = $config['retry_delay'] ?? 500;
        $this->timeout = $config['timeout'] ?? 15;
    }

    public function send(SmsMessageDTO $message): bool
    {
        try {
            // Validate phone number before sending
            $validatedPhone = $this->validateAndFormatPhoneNumber($message->recipient);
            if (!$validatedPhone) {
                Log::channel('sms')->error('Invalid phone number format', [
                    'recipient' => $message->recipient,
                    'message_length' => strlen($message->content)
                ]);
                return false;
            }

            $payload = [
                'api_key' => $this->apiKey,
                'numbers' => $validatedPhone,
                'message_body' => $message->content,
                'sender_id' => $message->senderId ?? $this->senderId ?? 'BILLING',
                'unicode' => $message->isUnicode
            ];

            // Add optional parameters
            if (!empty($message->options)) {
                $payload = array_merge($payload, $message->options);
            }

            Log::channel('sms')->debug('UGSMS sending request', [
                'recipient' => $validatedPhone,
                'payload' => array_merge($payload, ['api_key' => '***']), // Hide API key in logs
                'message_length' => strlen($message->content)
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->retry($this->retryAttempts, $this->retryDelay)
                ->post($this->baseUrl . '/api/v2/sms/send', $payload);

            $responseData = $response->json();

            if (!$response->successful()) {
                $this->logError('HTTP error', $message->recipient, $response->status(), $responseData);
                return false;
            }

            if (!($responseData['success'] ?? false)) {
                $this->logError('API error', $message->recipient, null, $responseData);
                return false;
            }

            Log::channel('sms')->info('SMS sent successfully', [
                'recipient' => $validatedPhone,
                'message_id' => $responseData['data']['message_id'] ?? null,
                'estimated_cost' => $responseData['data']['estimated_cost'] ?? 0,
                'remaining_balance' => $responseData['data']['remaining_balance'] ?? 0
            ]);

            // Cache the message ID for future reference
            if (isset($responseData['data']['message_id'])) {
                Cache::put('sms.message.' . $responseData['data']['message_id'], [
                    'recipient' => $validatedPhone,
                    'content' => $message->content,
                    'sent_at' => now(),
                    'status' => 'sent'
                ], now()->addDays(7));
            }

            return true;

        } catch (\Exception $e) {
            $this->logError('Exception occurred', $message->recipient, null, null, $e);
            return false;
        }
    }

    public function sendBulk(array $messages): array
    {
        $results = [];

        foreach ($messages as $index => $message) {
            if (!$message instanceof SmsMessageDTO) {
                $results[$index] = [
                    'success' => false,
                    'recipient' => 'unknown',
                    'error' => 'Invalid message format'
                ];
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
                    ->timeout($this->timeout)
                    ->post($this->baseUrl . '/api/v2/account/balance', $payload);

                $responseData = $response->json();

                if ($response->successful() && ($responseData['success'] ?? false)) {
                    return (float) ($responseData['data']['remaining_balance'] ?? 0);
                }

                Log::channel('sms')->warning('Failed to fetch balance', [
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

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
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/api/v2/sms/status', $payload);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['success'] ?? false)) {
                return $responseData['data']['status'] ?? 'unknown';
            }

            Log::channel('sms')->warning('Failed to fetch delivery status', [
                'message_id' => $messageId,
                'status' => $response->status(),
                'response' => $responseData
            ]);

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

    /**
     * Validate and format phone number for Uganda
     */
    private function validateAndFormatPhoneNumber(string $phone): ?string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if phone number is empty after cleaning
        if (empty($phone)) {
            return null;
        }

        // Handle different formats
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            // Format: 701234567 -> 256701234567
            return '256' . $phone;
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            // Format: 0701234567 -> 256701234567
            return '256' . substr($phone, 1);
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '256')) {
            // Already in international format
            return $phone;
        }

        // Check for other country codes or invalid formats
        if (strlen($phone) >= 10 && strlen($phone) <= 15) {
            // Could be international format from other countries
            // For Uganda-specific service, we might want to validate this
            if (str_starts_with($phone, '256')) {
                return $phone;
            }
        }

        // Invalid format
        return null;
    }

    /**
     * Log SMS errors with detailed information
     */
    private function logError(string $errorType, string $recipient, ?int $httpStatus = null, ?array $responseData = null, ?\Exception $exception = null): void
    {
        $logData = [
            'error_type' => $errorType,
            'recipient' => $recipient,
            'timestamp' => now()->toISOString()
        ];

        if ($httpStatus) {
            $logData['http_status'] = $httpStatus;
        }

        if ($responseData) {
            $logData['response'] = $responseData;
        }

        if ($exception) {
            $logData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        Log::channel('sms')->error('Failed to send SMS via UGSMS', $logData);
    }

    /**
     * Test SMS service connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/api/v2/account/balance', [
                    'api_key' => $this->apiKey
                ]);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'balance' => $responseData['data']['remaining_balance'] ?? 0,
                    'response_time' => $response->transferStats?->getTransferTime() ?? 0
                ];
            }

            return [
                'success' => false,
                'message' => 'API error: ' . ($responseData['message'] ?? 'Unknown error'),
                'error_code' => $responseData['error_code'] ?? null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }
}
