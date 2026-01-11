/**
 * Cache Service for managing local data caching
 * Provides in-memory and localStorage caching capabilities
 */

export interface CacheEntry<T = any> {
  data: T;
  timestamp: number;
  ttl: number; // Time to live in milliseconds
}

export interface CacheOptions {
  ttl?: number; // Default TTL in milliseconds
  storage?: 'memory' | 'localStorage';
  maxSize?: number; // Maximum number of entries for memory cache
}

class CacheService {
  private memoryCache = new Map<string, CacheEntry>();
  private defaultTTL = 5 * 60 * 1000; // 5 minutes default
  private maxSize = 100; // Default max size for memory cache

  constructor(options: CacheOptions = {}) {
    this.defaultTTL = options.ttl || this.defaultTTL;
    this.maxSize = options.maxSize || this.maxSize;
  }

  /**
   * Set data in cache
   */
  set<T>(key: string, data: T, options: CacheOptions = {}): void {
    // Skip undefined values as they can't be properly serialized
    if (data === undefined) {
      return;
    }

    const ttl = options.ttl || this.defaultTTL;
    const storage = options.storage || 'memory';
    const entry: CacheEntry<T> = {
      data,
      timestamp: Date.now(),
      ttl,
    };

    if (storage === 'localStorage') {
      try {
        localStorage.setItem(`cache_${key}`, JSON.stringify(entry));
      } catch (error) {
        console.warn('Failed to store in localStorage:', error);
        // Fallback to memory cache
        this._setMemoryCache(key, entry);
      }
    } else {
      this._setMemoryCache(key, entry);
    }
  }

  /**
   * Get data from cache
   */
  get<T>(key: string, storage: 'memory' | 'localStorage' = 'memory'): T | null {
    let entry: CacheEntry<T> | null = null;

    if (storage === 'localStorage') {
      try {
        const stored = localStorage.getItem(`cache_${key}`);
        if (stored) {
          entry = JSON.parse(stored);
        }
      } catch (error) {
        console.warn('Failed to read from localStorage:', error);
        return null;
      }
    } else {
      entry = this.memoryCache.get(key) || null;
    }

    if (!entry) {
      return null;
    }

    // Check if entry has expired
    if (Date.now() - entry.timestamp > entry.ttl) {
      this.delete(key, storage);
      return null;
    }

    return entry.data;
  }

  /**
   * Check if key exists and is not expired
   */
  has(key: string, storage: 'memory' | 'localStorage' = 'memory'): boolean {
    if (storage === 'localStorage') {
      try {
        const stored = localStorage.getItem(`cache_${key}`);
        if (!stored) {
          return false;
        }
        const entry = JSON.parse(stored);
        // Check if entry has expired
        if (Date.now() - entry.timestamp > entry.ttl) {
          this.delete(key, storage);
          return false;
        }
        return true;
      } catch (error) {
        console.warn('Failed to check localStorage:', error);
        return false;
      }
    } else {
      const entry = this.memoryCache.get(key);
      if (!entry) {
        return false;
      }
      // Check if entry has expired
      if (Date.now() - entry.timestamp > entry.ttl) {
        this.memoryCache.delete(key);
        return false;
      }
      return true;
    }
  }

  /**
   * Delete entry from cache
   */
  delete(key: string, storage: 'memory' | 'localStorage' = 'memory'): boolean {
    if (storage === 'localStorage') {
      try {
        const existed = localStorage.getItem(`cache_${key}`) !== null;
        if (existed) {
          localStorage.removeItem(`cache_${key}`);
        }
        return existed;
      } catch (error) {
        console.warn('Failed to remove from localStorage:', error);
        return false;
      }
    } else {
      return this.memoryCache.delete(key);
    }
  }

  /**
   * Clear all cache entries
   */
  clear(storage: 'memory' | 'localStorage' = 'memory'): void {
    if (storage === 'localStorage') {
      try {
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
          if (key.startsWith('cache_')) {
            localStorage.removeItem(key);
          }
        });
      } catch (error) {
        console.warn('Failed to clear localStorage cache:', error);
      }
    } else {
      this.memoryCache.clear();
    }
  }

  /**
   * Get or set pattern - retrieve from cache or execute function and cache result
   */
  async getOrSet<T>(
    key: string,
    fetchFn: () => Promise<T>,
    options: CacheOptions = {}
  ): Promise<T> {
    const storage = options.storage || 'memory';
    const cached = this.get<T>(key, storage);
    
    if (cached !== null) {
      return cached;
    }

    const data = await fetchFn();
    this.set(key, data, options);
    return data;
  }

  /**
   * Get cache statistics
   */
  getStats(): { memorySize: number; localStorageSize: number } {
    let localStorageSize = 0;
    try {
      // Count only cache entries in localStorage
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('cache_')) {
          localStorageSize++;
        }
      }
    } catch (error) {
      // localStorage not available
    }

    return {
      memorySize: this.memoryCache.size,
      localStorageSize,
    };
  }

  private _setMemoryCache<T>(key: string, entry: CacheEntry<T>): void {
    // Implement LRU eviction if cache is full
    if (this.memoryCache.size >= this.maxSize) {
      const firstKey = this.memoryCache.keys().next().value;
      if (firstKey) {
        this.memoryCache.delete(firstKey);
      }
    }
    
    this.memoryCache.set(key, entry);
  }
}

// Export singleton instance
export const cacheService = new CacheService();

// Export class for testing
export { CacheService };