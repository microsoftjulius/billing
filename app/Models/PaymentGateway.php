<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PaymentGateway extends BaseModel
{
    use HasFactory;
    protected $table = 'payment_gateways';

    protected $fillable = [
        'uuid',
        'name',
        'provider',
        'webhook_url',
        'is_active',
        'configuration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'configuration' => 'array',
    ];

    protected $appends = [
        'supported_currencies',
        'supported_methods',
        'statistics'
    ];

    /**
     * Relationships
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'provider', 'provider');
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Accessors
     */
    public function getSupportedCurrenciesAttribute(): array
    {
        return match ($this->provider) {
            'collectug' => ['UGX'],
            'stripe' => ['USD', 'EUR', 'UGX'],
            'paypal' => ['USD', 'EUR'],
            default => []
        };
    }

    public function getSupportedMethodsAttribute(): array
    {
        return match ($this->provider) {
            'collectug' => ['mobile_money'],
            'stripe' => ['card', 'bank_transfer'],
            'paypal' => ['paypal', 'card'],
            default => []
        };
    }

    public function getStatisticsAttribute(): ?array
    {
        // This would typically calculate real statistics from payments
        // For now, return mock data
        if (!$this->is_active) {
            return null;
        }

        return [
            'success_rate' => rand(85, 98),
            'total_transactions' => rand(100, 1000),
            'total_volume' => rand(1000000, 10000000),
        ];
    }

    /**
     * Business Logic
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function updateConfiguration(array $configuration): bool
    {
        return $this->update(['configuration' => $configuration]);
    }

    public function isConfigured(): bool
    {
        $requiredFields = $this->getRequiredConfigurationFields();
        
        foreach ($requiredFields as $field) {
            if (empty($this->configuration[$field])) {
                return false;
            }
        }

        return true;
    }

    public function getRequiredConfigurationFields(): array
    {
        return match ($this->provider) {
            'collectug' => ['api_key', 'base_url'],
            'stripe' => ['secret_key'],
            'paypal' => ['client_id', 'client_secret', 'environment'],
            default => []
        };
    }

    public function canProcessCurrency(string $currency): bool
    {
        return in_array($currency, $this->supported_currencies);
    }

    public function canProcessMethod(string $method): bool
    {
        return in_array($method, $this->supported_methods);
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhook_url ?: $this->getDefaultWebhookUrl();
    }

    private function getDefaultWebhookUrl(): string
    {
        return route('api.payment.callback', ['provider' => $this->provider]);
    }
}