/**
 * Composable for API request caching with debouncing and optimization
 */

import { ref, computed } from 'vue';
import { cacheService } from '@/services/cache';
import { loadingService } from '@/services/loading';

interface ApiCacheOptions {
  ttl?: number; // Cache TTL in milliseconds
  storage?: 'memory' | 'localStorage';
  debounceMs?: number; // Debounce delay for requests
  retryAttempts?: number; // Number of retry attempts
  retryDelay?: number; // Delay between retries
}

interface ApiRequest<T> {
  url: string;
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  data?: any;
  headers?: Record<string, string>;
  transform?: (data: any) => T;
}

export function useApiCache<T = any>(options: ApiCacheOptions = {}) {
  const {
    ttl = 5 * 60 * 1000, // 5 minutes default
    storage = 'memory',
    debounceMs = 300,
    retryAttempts = 3,
    retryDelay = 1000,
  } = options;

  const loading = ref(false);
  const error = ref<Error | null>(null);
  const data = ref<T | null>(null);

  // Debounced request map to prevent duplicate requests
  const pendingRequests = new Map<string, Promise<T>>();
  const debounceTimers = new Map<string, NodeJS.Timeout>();

  /**
   * Generate cache key from request
   */
  const getCacheKey = (request: ApiRequest<T>): string => {
    const { url, method = 'GET', data } = request;
    const dataHash = data ? btoa(JSON.stringify(data)) : '';
    return `api:${method}:${url}:${dataHash}`;
  };

  /**
   * Execute HTTP request with retry logic
   */
  const executeRequest = async (request: ApiRequest<T>, attempt = 1): Promise<T> => {
    try {
      const { url, method = 'GET', data: requestData, headers = {}, transform } = request;
      
      // Use axios or fetch for actual HTTP request
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          ...headers,
        },
        body: requestData ? JSON.stringify(requestData) : undefined,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const responseData = await response.json();
      return transform ? transform(responseData) : responseData;
      
    } catch (err) {
      if (attempt < retryAttempts) {
        // Wait before retry with exponential backoff
        await new Promise(resolve => setTimeout(resolve, retryDelay * Math.pow(2, attempt - 1)));
        return executeRequest(request, attempt + 1);
      }
      throw err;
    }
  };

  /**
   * Make cached API request with debouncing
   */
  const request = async (apiRequest: ApiRequest<T>): Promise<T> => {
    const cacheKey = getCacheKey(apiRequest);
    
    // Check cache first
    const cached = cacheService.get<T>(cacheKey, storage);
    if (cached !== null) {
      data.value = cached;
      return cached;
    }

    // Check if request is already pending
    if (pendingRequests.has(cacheKey)) {
      return pendingRequests.get(cacheKey)!;
    }

    // Clear existing debounce timer
    const existingTimer = debounceTimers.get(cacheKey);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }

    // Create debounced request promise
    const requestPromise = new Promise<T>((resolve, reject) => {
      const timer = setTimeout(async () => {
        try {
          loading.value = true;
          error.value = null;
          
          const loadingId = `api-request-${cacheKey}`;
          loadingService.start(loadingId, 'Loading data...');

          const result = await executeRequest(apiRequest);
          
          // Cache the result
          cacheService.set(cacheKey, result, { ttl, storage });
          
          data.value = result;
          resolve(result);
          
          await loadingService.stop(loadingId);
        } catch (err) {
          error.value = err as Error;
          reject(err);
          await loadingService.stop(`api-request-${cacheKey}`);
        } finally {
          loading.value = false;
          pendingRequests.delete(cacheKey);
          debounceTimers.delete(cacheKey);
        }
      }, debounceMs);

      debounceTimers.set(cacheKey, timer);
    });

    pendingRequests.set(cacheKey, requestPromise);
    return requestPromise;
  };

  /**
   * Invalidate cache for specific request or pattern
   */
  const invalidateCache = (pattern?: string) => {
    if (pattern) {
      // Clear specific cache entries matching pattern
      const stats = cacheService.getStats();
      // Note: This is a simplified implementation
      // In a real implementation, you'd need to iterate through cache keys
      console.warn('Pattern-based cache invalidation not fully implemented');
    } else {
      // Clear all cache
      cacheService.clear(storage);
    }
  };

  /**
   * Prefetch data for future use
   */
  const prefetch = async (apiRequest: ApiRequest<T>): Promise<void> => {
    const cacheKey = getCacheKey(apiRequest);
    
    // Only prefetch if not already cached
    if (!cacheService.has(cacheKey, storage)) {
      try {
        await request(apiRequest);
      } catch (err) {
        // Silently fail prefetch requests
        console.warn('Prefetch failed:', err);
      }
    }
  };

  /**
   * Get cached data without making a request
   */
  const getCached = (apiRequest: ApiRequest<T>): T | null => {
    const cacheKey = getCacheKey(apiRequest);
    return cacheService.get<T>(cacheKey, storage);
  };

  /**
   * Check if data is cached
   */
  const isCached = (apiRequest: ApiRequest<T>): boolean => {
    const cacheKey = getCacheKey(apiRequest);
    return cacheService.has(cacheKey, storage);
  };

  /**
   * Refresh data (bypass cache)
   */
  const refresh = async (apiRequest: ApiRequest<T>): Promise<T> => {
    const cacheKey = getCacheKey(apiRequest);
    
    // Clear cache for this request
    cacheService.delete(cacheKey, storage);
    
    // Make fresh request
    return request(apiRequest);
  };

  return {
    // State
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    data: computed(() => data.value),
    
    // Methods
    request,
    prefetch,
    getCached,
    isCached,
    refresh,
    invalidateCache,
    
    // Utilities
    getCacheKey,
  };
}