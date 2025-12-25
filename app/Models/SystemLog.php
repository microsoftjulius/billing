<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SystemLog extends BaseModel
{
    protected $table = 'system_logs';

    protected $fillable = [
        'uuid',
        'level',
        'module',
        'action',
        'message',
        'user_id',
        'ip_address',
        'user_agent',
        'data',
        'metadata'
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Scopes
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeErrors($query)
    {
        return $query->whereIn('level', ['error', 'critical']);
    }

    public function scopeWarnings($query)
    {
        return $query->where('level', 'warning');
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('message', 'ilike', "%{$search}%")
                ->orWhere('module', 'ilike', "%{$search}%")
                ->orWhere('action', 'ilike', "%{$search}%");
        });
    }

    /**
     * Accessors
     */
    public function getFormattedDataAttribute(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function getIsErrorAttribute(): bool
    {
        return in_array($this->level, ['error', 'critical']);
    }

    public function getIsWarningAttribute(): bool
    {
        return $this->level === 'warning';
    }

    public function getIsInfoAttribute(): bool
    {
        return $this->level === 'info';
    }

    /**
     * Business Logic
     */
    public static function log(string $level, string $module, string $action, string $message, array $data = [], array $metadata = []): self
    {
        return self::create([
            'level' => $level,
            'module' => $module,
            'action' => $action,
            'message' => $message,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data,
            'metadata' => $metadata
        ]);
    }

    public static function info(string $module, string $action, string $message, array $data = []): self
    {
        return self::log('info', $module, $action, $message, $data);
    }

    public static function warning(string $module, string $action, string $message, array $data = []): self
    {
        return self::log('warning', $module, $action, $message, $data);
    }

    public static function error(string $module, string $action, string $message, array $data = []): self
    {
        return self::log('error', $module, $action, $message, $data);
    }
}
