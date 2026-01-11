/**
 * Optimized API service with caching, debouncing, and performance monitoring
 */

import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { cacheService } from './cache';
import { loadingService } from './loading';
import { errorHandler } from './errorHandler';

interface ApiConfig {
  baseURL?: string;
  timeout?: number;
  retryAttempts?: number;
  retryDelay?: number;
  cacheEnabled?: boolean;
  cacheTtl?: number;
  debounceMs?: number;
}

interface RequestOptions extends AxiosRequestConfig {
  cache?: boolean;
  cacheTtl?: number;
  debounce?: boolean;
  debounceMs?: number;
  loadingId?: string;
  retries?: number;
}

class OptimizedApiService {
  private axiosInstance: AxiosInstance;
  private config: Required<ApiConfig>;
  private pendingRequests = new Map<string, Promise<any>>();
  private debounceTimers = new Map<string, NodeJS.Timeout>();
  private requestStats = {
    total: 0,
    cached: 0,
    failed: 0,
    avgResponseTime: 0,
  };

  constructor(config: ApiConfig = {}) {
    this.config = {
      baseURL: config.baseURL || '/api',
      timeout: config.timeout || 10000,
      retryAttempts: config.retryAttempts || 3,
      retryDelay: config.retryDelay || 1000,
      cacheEnabled: config.cacheEnabled ?? true,
      cacheTtl: config.cacheTtl || 5 * 60 * 1000, // 5 minutes
      debounceMs: config.debounceMs || 300,
    };

    this.axiosInstance = axios.create({
      baseURL: this.config.baseURL,
      timeout: this.config.timeout,
    });

    this.setupInterceptors();
  }

  /**
   * Setup request and response interceptors
   */
  private setupInterceptors() {
    // Request interceptor
    this.axiosInstance.interceptors.request.use(
      (config) => {
        // Add timestamp for performance tracking
        config.metadata = { startTime: Date.now() };
        
        // Add request ID for tracking
        config.headers['X-Request-ID'] = `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        
        return config;
      },
      (error) => {
        // Handle request setup errors
        errorHandler.handleApiError(error, {
          context: {
            component: 'OptimizedAPI',
            action: 'request_setup_error'
          }
        });
        return Promise.reject(error);
      }
    );

    // Response interceptor
    this.axiosInstance.interceptors.response.use(
      (response) => {
        // Track response time
        const duration = Date.now() - response.config.metadata?.startTime;
        this.updateStats(duration, false, false);
        
        // Log slow requests in development
        if (import.meta.env.DEV && duration > 2000) {
          console.warn(`Slow API request: ${response.config.url} took ${duration}ms`);
        }
        
        return response;
      },
      async (error) => {
        const duration = Date.now() - error.config?.metadata?.startTime;
        this.updateStats(duration, false, true);

        // Enhanced retry logic with better error handling
        const retries = error.config?.retries || 0;
        const shouldRetry = this.shouldRetry(error);
        
        if (retries < this.config.retryAttempts && shouldRetry) {
          error.config.retries = retries + 1;
          
          // Exponential backoff with jitter
          const baseDelay = this.config.retryDelay * Math.pow(2, retries);
          const jitter = Math.random() * 0.1 * baseDelay;
          const delay = baseDelay + jitter;
          
          console.log(`Retrying request (${retries + 1}/${this.config.retryAttempts}) after ${delay}ms:`, error.config.url);
          
          await new Promise(resolve => setTimeout(resolve, delay));
          
          return this.axiosInstance.request(error.config);
        }

        // Handle different types of errors with appropriate user feedback
        this.handleApiError(error);
        
        return Promise.reject(error);
      }
    );
  }

  /**
   * Determine if request should be retried
   */
  private shouldRetry(error: any): boolean {
    // Don't retry on client errors (4xx) except for specific cases
    if (error.response?.status >= 400 && error.response?.status < 500) {
      // Retry on 408 (timeout), 429 (rate limit), and 499 (client closed request)
      return [408, 429, 499].includes(error.response.status);
    }
    
    // Retry on network errors or 5xx status codes
    return !error.response || (error.response.status >= 500 && error.response.status < 600);
  }

  /**
   * Handle API errors with enhanced error reporting
   */
  private handleApiError(error: any): void {
    const context = {
      component: 'OptimizedAPI',
      action: 'api_error',
      additionalData: {
        url: error.config?.url,
        method: error.config?.method?.toUpperCase(),
        status: error.response?.status,
        statusText: error.response?.statusText,
        requestId: error.config?.headers?.['X-Request-ID'],
        retries: error.config?.retries || 0,
        duration: Date.now() - (error.config?.metadata?.startTime || Date.now())
      }
    };

    // Determine if we should show user notification based on error type
    const showNotification = this.shouldShowNotificationForError(error);
    
    // Get recovery actions based on error type
    const recoveryActions = this.getRecoveryActionsForError(error);

    errorHandler.handleApiError(error, {
      showNotification,
      context,
      recoveryActions
    });
  }

  /**
   * Determine if error should show user notification
   */
  private shouldShowNotificationForError(error: any): boolean {
    // Don't show notifications for validation errors (handled by forms)
    if (error.response?.status === 422) {
      return false;
    }
    
    // Don't show notifications for unauthorized requests (handled by auth)
    if (error.response?.status === 401) {
      return false;
    }
    
    // Show notifications for server errors and network issues
    return true;
  }

  /**
   * Get recovery actions for specific error types
   */
  private getRecoveryActionsForError(error: any): Array<{label: string, action: () => void, primary?: boolean}> {
    const actions: Array<{label: string, action: () => void, primary?: boolean}> = [];
    
    // Add retry action for retryable errors
    if (this.shouldRetry(error)) {
      actions.push({
        label: 'Retry Request',
        action: () => {
          // Reset retries and try again
          if (error.config) {
            error.config.retries = 0;
            this.axiosInstance.request(error.config);
          }
        },
        primary: true
      });
    }
    
    // Add refresh action for server errors
    if (error.response?.status >= 500) {
      actions.push({
        label: 'Refresh Page',
        action: () => {
          window.location.reload();
        }
      });
    }
    
    // Add clear cache action for cache-related issues
    if (error.response?.status === 304 || error.message?.includes('cache')) {
      actions.push({
        label: 'Clear Cache',
        action: () => {
          this.invalidateCache();
          window.location.reload();
        }
      });
    }
    
    return actions;
  }

  /**
   * Update request statistics
   */
  private updateStats(duration: number, cached: boolean, failed: boolean) {
    this.requestStats.total++;
    if (cached) this.requestStats.cached++;
    if (failed) this.requestStats.failed++;
    
    // Update average response time
    this.requestStats.avgResponseTime = 
      (this.requestStats.avgResponseTime * (this.requestStats.total - 1) + duration) / this.requestStats.total;
  }

  /**
   * Generate cache key for request
   */
  private getCacheKey(config: AxiosRequestConfig): string {
    const { method = 'GET', url = '', params, data } = config;
    const paramsStr = params ? JSON.stringify(params) : '';
    const dataStr = data ? JSON.stringify(data) : '';
    return `api:${method}:${url}:${paramsStr}:${dataStr}`;
  }

  /**
   * Make optimized API request
   */
  async request<T = any>(config: RequestOptions): Promise<T> {
    const {
      cache = this.config.cacheEnabled,
      cacheTtl = this.config.cacheTtl,
      debounce = true,
      debounceMs = this.config.debounceMs,
      loadingId,
      ...axiosConfig
    } = config;

    const cacheKey = this.getCacheKey(axiosConfig);

    // Check cache first
    if (cache && axiosConfig.method?.toUpperCase() === 'GET') {
      const cached = cacheService.get<T>(cacheKey);
      if (cached !== null) {
        this.updateStats(0, true, false);
        return cached;
      }
    }

    // Check for pending request
    if (this.pendingRequests.has(cacheKey)) {
      return this.pendingRequests.get(cacheKey);
    }

    // Create request promise
    const requestPromise = new Promise<T>((resolve, reject) => {
      const executeRequest = async () => {
        try {
          // Start loading indicator
          if (loadingId) {
            loadingService.start(loadingId, 'Loading...');
          }

          const response: AxiosResponse<T> = await this.axiosInstance.request(axiosConfig);
          const data = response.data;

          // Cache successful GET requests
          if (cache && axiosConfig.method?.toUpperCase() === 'GET') {
            cacheService.set(cacheKey, data, { ttl: cacheTtl });
          }

          resolve(data);
        } catch (error) {
          reject(error);
        } finally {
          // Stop loading indicator
          if (loadingId) {
            loadingService.stop(loadingId);
          }
          
          // Clean up
          this.pendingRequests.delete(cacheKey);
          this.debounceTimers.delete(cacheKey);
        }
      };

      if (debounce && axiosConfig.method?.toUpperCase() === 'GET') {
        // Clear existing timer
        const existingTimer = this.debounceTimers.get(cacheKey);
        if (existingTimer) {
          clearTimeout(existingTimer);
        }

        // Set new timer
        const timer = setTimeout(executeRequest, debounceMs);
        this.debounceTimers.set(cacheKey, timer);
      } else {
        // Execute immediately for non-GET requests
        executeRequest();
      }
    });

    this.pendingRequests.set(cacheKey, requestPromise);
    return requestPromise;
  }

  /**
   * GET request with optimizations
   */
  async get<T = any>(url: string, config: RequestOptions = {}): Promise<T> {
    return this.request<T>({ ...config, method: 'GET', url });
  }

  /**
   * POST request
   */
  async post<T = any>(url: string, data?: any, config: RequestOptions = {}): Promise<T> {
    return this.request<T>({ ...config, method: 'POST', url, data, cache: false });
  }

  /**
   * PUT request
   */
  async put<T = any>(url: string, data?: any, config: RequestOptions = {}): Promise<T> {
    return this.request<T>({ ...config, method: 'PUT', url, data, cache: false });
  }

  /**
   * DELETE request
   */
  async delete<T = any>(url: string, config: RequestOptions = {}): Promise<T> {
    return this.request<T>({ ...config, method: 'DELETE', url, cache: false });
  }

  /**
   * Batch requests with concurrency control
   */
  async batch<T = any>(
    requests: RequestOptions[],
    options: { concurrency?: number; failFast?: boolean } = {}
  ): Promise<T[]> {
    const { concurrency = 5, failFast = false } = options;
    const results: T[] = [];
    const errors: Error[] = [];

    // Process requests in batches
    for (let i = 0; i < requests.length; i += concurrency) {
      const batch = requests.slice(i, i + concurrency);
      const batchPromises = batch.map(async (config, index) => {
        try {
          const result = await this.request<T>(config);
          return { index: i + index, result, error: null };
        } catch (error) {
          return { index: i + index, result: null, error: error as Error };
        }
      });

      const batchResults = await Promise.all(batchPromises);
      
      for (const { index, result, error } of batchResults) {
        if (error) {
          errors.push(error);
          if (failFast) {
            throw error;
          }
        } else {
          results[index] = result!;
        }
      }
    }

    if (errors.length > 0 && !failFast) {
      console.warn(`Batch request completed with ${errors.length} errors:`, errors);
    }

    return results;
  }

  /**
   * Prefetch data for future use
   */
  async prefetch(requests: RequestOptions[]): Promise<void> {
    const prefetchPromises = requests.map(config => 
      this.request({ ...config, loadingId: undefined }).catch(() => {
        // Silently fail prefetch requests
      })
    );

    await Promise.allSettled(prefetchPromises);
  }

  /**
   * Invalidate cache
   */
  invalidateCache(pattern?: string): void {
    if (pattern) {
      // For now, clear all cache - in production, implement pattern matching
      cacheService.clear();
    } else {
      cacheService.clear();
    }
  }

  /**
   * Get request statistics
   */
  getStats() {
    return {
      ...this.requestStats,
      cacheHitRate: this.requestStats.total > 0 
        ? this.requestStats.cached / this.requestStats.total 
        : 0,
      errorRate: this.requestStats.total > 0 
        ? this.requestStats.failed / this.requestStats.total 
        : 0,
      pendingRequests: this.pendingRequests.size,
    };
  }

  /**
   * Cancel all pending requests
   */
  cancelPendingRequests(): void {
    // Clear debounce timers
    this.debounceTimers.forEach(timer => clearTimeout(timer));
    this.debounceTimers.clear();
    
    // Clear pending requests
    this.pendingRequests.clear();
  }

  /**
   * Set authentication token
   */
  setAuthToken(token: string): void {
    this.axiosInstance.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  }

  /**
   * Remove authentication token
   */
  removeAuthToken(): void {
    delete this.axiosInstance.defaults.headers.common['Authorization'];
  }

  /**
   * Update base configuration
   */
  updateConfig(newConfig: Partial<ApiConfig>): void {
    Object.assign(this.config, newConfig);
    
    if (newConfig.baseURL) {
      this.axiosInstance.defaults.baseURL = newConfig.baseURL;
    }
    
    if (newConfig.timeout) {
      this.axiosInstance.defaults.timeout = newConfig.timeout;
    }
  }
}

// Export singleton instance
export const optimizedApi = new OptimizedApiService();

// Export class for custom instances
export { OptimizedApiService };