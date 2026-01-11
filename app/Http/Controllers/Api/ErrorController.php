<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ErrorController extends Controller
{
    /**
     * Log frontend errors with enhanced tracking
     */
    public function logError(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'error.message' => 'required|string|max:1000',
            'error.stack' => 'nullable|string|max:5000',
            'context.component' => 'nullable|string|max:100',
            'context.action' => 'nullable|string|max:100',
            'context.url' => 'nullable|string|max:500',
            'context.userAgent' => 'nullable|string|max:500',
            'context.userId' => 'nullable|string|max:50',
            'context.additionalData' => 'nullable|array',
            'timestamp' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid error data',
                'errors' => $validator->errors()
            ], 422);
        }

        $errorData = $validator->validated();
        
        // Generate unique error ID
        $errorId = uniqid('fe_', true);
        
        // Sanitize and prepare error data for logging
        $logData = [
            'error_id' => $errorId,
            'type' => 'frontend_error',
            'message' => $errorData['error']['message'],
            'stack' => $errorData['error']['stack'] ?? null,
            'component' => $errorData['context']['component'] ?? 'unknown',
            'action' => $errorData['context']['action'] ?? 'unknown',
            'url' => $errorData['context']['url'] ?? null,
            'user_agent' => $errorData['context']['userAgent'] ?? null,
            'user_id' => $errorData['context']['userId'] ?? null,
            'additional_data' => $errorData['context']['additionalData'] ?? null,
            'timestamp' => $errorData['timestamp'],
            'ip_address' => $request->ip(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'tenant_id' => tenant('id') ?? null,
        ];

        // Determine log level and severity
        $logLevel = $this->determineLogLevel($errorData['error']['message']);
        $severity = $this->determineSeverity($errorData);
        
        // Check for error rate limiting to prevent spam
        if ($this->isErrorRateLimited($errorData['error']['message'], $request->ip())) {
            return response()->json([
                'message' => 'Error logged (rate limited)',
                'error_id' => $errorId
            ]);
        }

        // Log to Laravel log with appropriate level
        Log::log($logLevel, 'Frontend Error: ' . $errorData['error']['message'], $logData);

        // Store error in database for analytics (if enabled)
        if (config('app.store_frontend_errors', false)) {
            $this->storeErrorInDatabase($logData, $severity);
        }

        // Update error statistics
        $this->updateErrorStatistics($errorData, $severity);

        // Send to external monitoring service in production
        if (app()->environment('production')) {
            $this->sendToExternalService($logData, $severity);
        }

        // Send alerts for critical errors
        if ($severity === 'critical') {
            $this->sendCriticalErrorAlert($logData);
        }

        return response()->json([
            'message' => 'Error logged successfully',
            'error_id' => $errorId
        ]);
    }

    /**
     * Get comprehensive error statistics for monitoring
     */
    public function getErrorStats(Request $request): JsonResponse
    {
        $timeframe = $request->get('timeframe', '24h');
        $cacheKey = "error_stats_{$timeframe}_" . (tenant('id') ?? 'central');
        
        $stats = Cache::remember($cacheKey, 300, function () use ($timeframe) {
            $hours = $this->parseTimeframe($timeframe);
            $since = now()->subHours($hours);
            
            // Get error counts from cache (in production, this would query your error database)
            $totalErrors = Cache::get('error_count_total', 0);
            $recentErrors = Cache::get('error_count_recent', 0);
            $criticalErrors = Cache::get('error_count_critical', 0);
            
            // Calculate error rate (errors per hour)
            $errorRate = $hours > 0 ? $recentErrors / $hours : 0;
            
            // Get most common errors
            $commonErrors = Cache::get('common_errors', []);
            
            // Get error trends (mock data - in production, query your database)
            $trends = $this->generateErrorTrends($hours);
            
            // Get affected users count
            $affectedUsers = Cache::get('affected_users_count', 0);
            
            // Get error distribution by component
            $componentDistribution = Cache::get('error_component_distribution', []);
            
            return [
                'total_errors_period' => $recentErrors,
                'total_errors_all_time' => $totalErrors,
                'critical_errors' => $criticalErrors,
                'error_rate_per_hour' => round($errorRate, 2),
                'most_common_errors' => array_slice($commonErrors, 0, 10),
                'error_trends' => $trends,
                'affected_users' => $affectedUsers,
                'component_distribution' => $componentDistribution,
                'health_score' => $this->calculateHealthScore($recentErrors, $criticalErrors, $hours),
                'timeframe' => $timeframe,
                'last_updated' => now()->toISOString()
            ];
        });

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get detailed error information for debugging
     */
    public function getErrorDetails(Request $request, string $errorId): JsonResponse
    {
        // In production, this would query your error database
        $errorDetails = Cache::get("error_details_{$errorId}");
        
        if (!$errorDetails) {
            return response()->json([
                'message' => 'Error not found'
            ], 404);
        }
        
        return response()->json([
            'data' => $errorDetails
        ]);
    }

    /**
     * Mark error as resolved or add notes
     */
    public function updateErrorStatus(Request $request, string $errorId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:resolved,investigating,ignored',
            'notes' => 'nullable|string|max:1000',
            'assigned_to' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid data',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // In production, update your error database
        Cache::put("error_status_{$errorId}", [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'updated_by' => auth()->id(),
            'updated_at' => now()->toISOString()
        ], 86400);

        Log::info("Error status updated", [
            'error_id' => $errorId,
            'status' => $data['status'],
            'updated_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Error status updated successfully'
        ]);
    }

    /**
     * Determine appropriate log level based on error message and context
     */
    private function determineLogLevel(string $message): string
    {
        $message = strtolower($message);
        
        if (str_contains($message, 'network') || str_contains($message, 'timeout')) {
            return 'warning';
        }
        
        if (str_contains($message, 'validation') || str_contains($message, 'user input')) {
            return 'info';
        }
        
        if (str_contains($message, 'critical') || str_contains($message, 'fatal') || str_contains($message, 'crash')) {
            return 'critical';
        }
        
        if (str_contains($message, 'websocket') || str_contains($message, 'connection')) {
            return 'warning';
        }
        
        return 'error';
    }

    /**
     * Determine error severity for alerting and prioritization
     */
    private function determineSeverity(array $errorData): string
    {
        $message = strtolower($errorData['error']['message']);
        $component = strtolower($errorData['context']['component'] ?? '');
        
        // Critical errors that need immediate attention
        if (str_contains($message, 'critical') || 
            str_contains($message, 'fatal') || 
            str_contains($message, 'crash') ||
            str_contains($message, 'security') ||
            $component === 'payment') {
            return 'critical';
        }
        
        // High priority errors
        if (str_contains($message, 'api') || 
            str_contains($message, 'database') ||
            str_contains($message, 'authentication') ||
            $component === 'auth') {
            return 'high';
        }
        
        // Medium priority errors
        if (str_contains($message, 'network') || 
            str_contains($message, 'timeout') ||
            str_contains($message, 'websocket')) {
            return 'medium';
        }
        
        // Low priority errors
        return 'low';
    }

    /**
     * Check if error should be rate limited to prevent spam
     */
    private function isErrorRateLimited(string $message, string $ip): bool
    {
        $key = 'error_rate_limit_' . md5($message . $ip);
        $count = Cache::get($key, 0);
        
        if ($count >= 10) { // Max 10 identical errors per hour from same IP
            return true;
        }
        
        Cache::put($key, $count + 1, 3600); // 1 hour
        return false;
    }

    /**
     * Store error in database for analytics
     */
    private function storeErrorInDatabase(array $logData, string $severity): void
    {
        try {
            // In production, you would have an errors table
            DB::table('frontend_errors')->insert([
                'error_id' => $logData['error_id'],
                'message' => $logData['message'],
                'stack' => $logData['stack'],
                'component' => $logData['component'],
                'action' => $logData['action'],
                'url' => $logData['url'],
                'user_id' => $logData['user_id'],
                'ip_address' => $logData['ip_address'],
                'user_agent' => $logData['user_agent'],
                'severity' => $severity,
                'additional_data' => json_encode($logData['additional_data']),
                'tenant_id' => $logData['tenant_id'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            // Don't let database errors break error logging
            Log::warning('Failed to store error in database', [
                'error' => $e->getMessage(),
                'original_error_id' => $logData['error_id']
            ]);
        }
    }

    /**
     * Update error statistics in cache
     */
    private function updateErrorStatistics(array $errorData, string $severity): void
    {
        // Increment total error count
        Cache::increment('error_count_total');
        Cache::increment('error_count_recent');
        
        if ($severity === 'critical') {
            Cache::increment('error_count_critical');
        }
        
        // Update common errors list
        $commonErrors = Cache::get('common_errors', []);
        $errorKey = $errorData['error']['message'];
        $commonErrors[$errorKey] = ($commonErrors[$errorKey] ?? 0) + 1;
        arsort($commonErrors);
        Cache::put('common_errors', $commonErrors, 3600);
        
        // Update component distribution
        $component = $errorData['context']['component'] ?? 'unknown';
        $distribution = Cache::get('error_component_distribution', []);
        $distribution[$component] = ($distribution[$component] ?? 0) + 1;
        Cache::put('error_component_distribution', $distribution, 3600);
        
        // Update affected users count
        if (!empty($errorData['context']['userId'])) {
            $affectedUsers = Cache::get('affected_users', []);
            $affectedUsers[$errorData['context']['userId']] = true;
            Cache::put('affected_users', $affectedUsers, 3600);
            Cache::put('affected_users_count', count($affectedUsers), 3600);
        }
    }

    /**
     * Send error data to external monitoring service
     */
    private function sendToExternalService(array $errorData, string $severity): void
    {
        try {
            // Example: Send to Sentry, Bugsnag, or custom monitoring service
            // This is where you'd integrate with your preferred error tracking service
            
            $payload = [
                'error_id' => $errorData['error_id'],
                'message' => $errorData['message'],
                'severity' => $severity,
                'environment' => app()->environment(),
                'timestamp' => $errorData['timestamp'],
                'user_id' => $errorData['user_id'],
                'context' => [
                    'component' => $errorData['component'],
                    'action' => $errorData['action'],
                    'url' => $errorData['url'],
                    'user_agent' => $errorData['user_agent'],
                    'additional_data' => $errorData['additional_data']
                ]
            ];
            
            // For demonstration, we'll just log that we would send it
            Log::info('Would send error to external service', [
                'service' => 'external_monitoring',
                'payload_size' => strlen(json_encode($payload)),
                'error_id' => $errorData['error_id']
            ]);
            
        } catch (\Exception $e) {
            // Don't let external service failures break error logging
            Log::warning('Failed to send error to external service', [
                'error' => $e->getMessage(),
                'original_error_id' => $errorData['error_id']
            ]);
        }
    }

    /**
     * Send alert for critical errors
     */
    private function sendCriticalErrorAlert(array $errorData): void
    {
        try {
            // In production, send email/Slack/SMS alerts for critical errors
            Log::critical('CRITICAL ERROR ALERT', [
                'error_id' => $errorData['error_id'],
                'message' => $errorData['message'],
                'component' => $errorData['component'],
                'user_id' => $errorData['user_id'],
                'url' => $errorData['url'],
                'timestamp' => $errorData['timestamp']
            ]);
            
            // Example: Send to Slack webhook, email notification, etc.
            
        } catch (\Exception $e) {
            Log::error('Failed to send critical error alert', [
                'error' => $e->getMessage(),
                'original_error_id' => $errorData['error_id']
            ]);
        }
    }

    /**
     * Parse timeframe string to hours
     */
    private function parseTimeframe(string $timeframe): int
    {
        return match($timeframe) {
            '1h' => 1,
            '6h' => 6,
            '12h' => 12,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24
        };
    }

    /**
     * Generate error trends data
     */
    private function generateErrorTrends(int $hours): array
    {
        $trends = [];
        $interval = max(1, intval($hours / 24)); // Show up to 24 data points
        
        for ($i = $hours; $i >= 0; $i -= $interval) {
            $trends[] = [
                'timestamp' => now()->subHours($i)->toISOString(),
                'count' => rand(0, 10), // Mock data - in production, query your database
                'critical_count' => rand(0, 2)
            ];
        }
        
        return $trends;
    }

    /**
     * Calculate overall system health score
     */
    private function calculateHealthScore(int $recentErrors, int $criticalErrors, int $hours): int
    {
        if ($criticalErrors > 0) {
            return max(0, 50 - ($criticalErrors * 10));
        }
        
        $errorRate = $hours > 0 ? $recentErrors / $hours : 0;
        
        if ($errorRate === 0) return 100;
        if ($errorRate < 1) return 90;
        if ($errorRate < 5) return 75;
        if ($errorRate < 10) return 60;
        if ($errorRate < 20) return 40;
        
        return 20;
    }
}