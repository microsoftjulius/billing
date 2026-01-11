<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TenantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'data_type',
        'updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'updated_by' => 'string', // UUID is stored as string
    ];

    /**
     * Relationship to tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship to user who updated the setting
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Automatically encrypt sensitive values when storing
     */
    public function setValueAttribute($value)
    {
        // Encrypt API keys and passwords
        if ($this->isSensitiveKey($this->key)) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Automatically decrypt sensitive values when retrieving
     */
    public function getValueAttribute($value)
    {
        if ($this->isSensitiveKey($this->key)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If decryption fails, return the original value
                return $value;
            }
        }

        return $value;
    }

    /**
     * Get masked value for display in UI
     */
    public function getMaskedValueAttribute()
    {
        if (!$this->isSensitiveKey($this->key)) {
            return $this->value;
        }

        $value = $this->value;
        
        if (strlen($value) <= 8) {
            // For short values, show first 2 and last 2 characters
            return substr($value, 0, 2) . str_repeat('*', max(1, strlen($value) - 4)) . substr($value, -2);
        } else {
            // For longer values, show first 4 and last 4 characters
            return substr($value, 0, 4) . str_repeat('*', max(1, strlen($value) - 8)) . substr($value, -4);
        }
    }

    /**
     * Check if a key contains sensitive information
     */
    private function isSensitiveKey(?string $key): bool
    {
        if (!$key) {
            return false;
        }

        $sensitivePatterns = [
            'api_key',
            'api_secret',
            'password',
            'secret',
            'token',
            'private_key',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains(strtolower($key), $pattern)) {
                return true;
            }
        }

        return false;
    }
}