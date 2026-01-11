<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;

class SmsTemplateService
{
    /**
     * Get all available SMS templates
     */
    public function getTemplates(): array
    {
        return Cache::remember('sms.templates', 3600, function () {
            return [
                [
                    'id' => 'voucher_delivery',
                    'name' => 'Voucher Delivery',
                    'content' => 'Your internet voucher: Code: {{voucher_code}}, Password: {{voucher_password}}, Valid for: {{validity_hours}} hours. Expires: {{expires_at}}. Thank you!',
                    'variables' => ['voucher_code', 'voucher_password', 'validity_hours', 'expires_at']
                ],
                [
                    'id' => 'payment_confirmation',
                    'name' => 'Payment Confirmation',
                    'content' => 'Payment of {{currency}} {{amount}} received. Transaction ID: {{transaction_id}}. Your voucher will be sent shortly.',
                    'variables' => ['currency', 'amount', 'transaction_id']
                ],
                [
                    'id' => 'payment_reminder',
                    'name' => 'Payment Reminder',
                    'content' => 'Dear {{customer_name}}, your payment is due. Please make payment to continue your internet service.',
                    'variables' => ['customer_name']
                ],
                [
                    'id' => 'service_activation',
                    'name' => 'Service Activation',
                    'content' => 'Hello {{customer_name}}, your internet service has been activated. Thank you for your payment!',
                    'variables' => ['customer_name']
                ],
                [
                    'id' => 'service_suspension',
                    'name' => 'Service Suspension',
                    'content' => 'Dear {{customer_name}}, your internet service has been suspended due to non-payment. Please contact support.',
                    'variables' => ['customer_name']
                ],
                [
                    'id' => 'low_balance_alert',
                    'name' => 'Low Balance Alert',
                    'content' => 'ALERT: SMS balance is low. Current balance: {{currency}} {{balance}}. Please top up to continue service.',
                    'variables' => ['currency', 'balance']
                ],
                [
                    'id' => 'voucher_expiry_warning',
                    'name' => 'Voucher Expiry Warning',
                    'content' => 'Dear {{customer_name}}, your voucher {{voucher_code}} will expire in {{hours_remaining}} hours. Please use it before {{expires_at}}.',
                    'variables' => ['customer_name', 'voucher_code', 'hours_remaining', 'expires_at']
                ],
                [
                    'id' => 'welcome_message',
                    'name' => 'Welcome Message',
                    'content' => 'Welcome to our internet service, {{customer_name}}! Your account has been created. Contact support for assistance.',
                    'variables' => ['customer_name']
                ]
            ];
        });
    }

    /**
     * Get a specific template by ID
     */
    public function getTemplate(string $templateId): ?array
    {
        $templates = $this->getTemplates();
        
        foreach ($templates as $template) {
            if ($template['id'] === $templateId) {
                return $template;
            }
        }
        
        return null;
    }

    /**
     * Process template variables
     */
    public function processTemplate(string $templateId, array $variables): string
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateId}' not found");
        }
        
        $content = $template['content'];
        
        // Replace variables in the template
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }

    /**
     * Validate template variables
     */
    public function validateTemplateVariables(string $templateId, array $variables): array
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateId}' not found");
        }
        
        $errors = [];
        $requiredVariables = $template['variables'] ?? [];
        
        // Check for missing required variables
        foreach ($requiredVariables as $requiredVar) {
            if (!isset($variables[$requiredVar]) || $variables[$requiredVar] === '') {
                $errors[] = "Missing required variable: {$requiredVar}";
            }
        }
        
        return $errors;
    }

    /**
     * Get template preview with sample data
     */
    public function getTemplatePreview(string $templateId): string
    {
        $sampleData = [
            'customer_name' => 'John Doe',
            'voucher_code' => 'ABC123XYZ',
            'voucher_password' => 'pass123',
            'validity_hours' => '24',
            'expires_at' => now()->addHours(24)->format('Y-m-d H:i'),
            'currency' => 'UGX',
            'amount' => '10,000',
            'transaction_id' => 'TXN123456789',
            'balance' => '5,000',
            'hours_remaining' => '6'
        ];
        
        return $this->processTemplate($templateId, $sampleData);
    }
}