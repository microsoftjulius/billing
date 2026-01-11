import { describe, it, expect, beforeEach, vi } from 'vitest';
import fc from 'fast-check';
import { CacheService, cacheService } from '@/services/cache';

// Mock localStorage for consistent testing
const mockLocalStorage = (() => {
  let store: Record<string, string> = {};
  return {
    getItem: (key: string) => store[key] || null,
    setItem: (key: string, value: string) => { store[key] = value; },
    removeItem: (key: string) => { delete store[key]; },
    clear: () => { store = {}; },
    get length() { return Object.keys(store).length; },
    key: (index: number) => Object.keys(store)[index] || null,
  };
})();

// Replace global localStorage with mock
Object.defineProperty(window, 'localStorage', { value: mockLocalStorage });

describe('Data Caching Behavior Property Tests', () => {
  let cache: CacheService;

  beforeEach(() => {
    cache = new CacheService();
    // Clear mock localStorage before each test
    mockLocalStorage.clear();
    vi.clearAllMocks();
  });

  it('Property 34: Data Caching Behavior - For any frequently accessed data, the system should cache it locally and retrieve from cache when available to improve performance', () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 5 }),
          fc.record({ name: fc.string(), value: fc.integer() })
        ), // serializable data to cache
        fc.integer({ min: 1000, max: 60000 }), // TTL in milliseconds
        fc.constantFrom('memory', 'localStorage'), // storage type
        (key, data, ttl, storage) => {
          // Skip undefined values as they can't be properly serialized
          fc.pre(data !== undefined);
          
          // Store data in cache
          cache.set(key, data, { ttl, storage });
          
          // Verify data can be retrieved immediately
          const retrieved = cache.get(key, storage);
          expect(retrieved).toEqual(data);
          
          // Verify cache hit detection
          expect(cache.has(key, storage)).toBe(true);
          
          // Verify cache statistics are updated
          const stats = cache.getStats();
          if (storage === 'memory') {
            expect(stats.memorySize).toBeGreaterThanOrEqual(1);
          } else {
            // For localStorage, we need to check if the item was actually stored
            const stored = localStorage.getItem(`cache_${key}`);
            if (stored) {
              expect(stats.localStorageSize).toBeGreaterThanOrEqual(1);
            }
          }
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 34a: Cache expiration behavior - expired entries should not be retrievable', async () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    await fc.assert(
      fc.asyncProperty(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // serializable data to cache
        fc.constantFrom('memory', 'localStorage'), // storage type
        async (key, data, storage) => {
          // Skip undefined values
          fc.pre(data !== undefined);
          
          // Set data with very short TTL (10ms)
          cache.set(key, data, { ttl: 10, storage });
          
          // Verify data is initially retrievable
          expect(cache.get(key, storage)).toEqual(data);
          
          // Wait for expiration
          await new Promise(resolve => setTimeout(resolve, 50));
          
          // Verify expired data is not retrievable
          const retrieved = cache.get(key, storage);
          expect(retrieved).toBeNull();
          
          // Verify cache miss detection
          expect(cache.has(key, storage)).toBe(false);
        }
      ),
      { numRuns: 20 } // Reduced runs due to async nature and delays
    );
  }, 10000); // Increase test timeout

  it('Property 34b: Cache replacement behavior - new data should replace old data for same key', () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // original serializable data
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // new serializable data
        fc.constantFrom('memory', 'localStorage'), // storage type
        (key, originalData, newData, storage) => {
          // Skip undefined values and ensure data is different for meaningful test
          fc.pre(originalData !== undefined && newData !== undefined);
          fc.pre(JSON.stringify(originalData) !== JSON.stringify(newData));
          
          // Store original data
          cache.set(key, originalData, { storage });
          
          // Verify original data is retrievable
          expect(cache.get(key, storage)).toEqual(originalData);
          
          // Replace with new data
          cache.set(key, newData, { storage });
          
          // Verify new data is retrievable and old data is replaced
          const retrieved = cache.get(key, storage);
          expect(retrieved).toEqual(newData);
          expect(retrieved).not.toEqual(originalData);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 34c: Cache deletion behavior - deleted entries should not be retrievable', () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // serializable data to cache
        fc.constantFrom('memory', 'localStorage'), // storage type
        (key, data, storage) => {
          // Skip undefined values
          fc.pre(data !== undefined);
          
          // Store data in cache
          cache.set(key, data, { storage });
          
          // Verify data is retrievable
          expect(cache.get(key, storage)).toEqual(data);
          expect(cache.has(key, storage)).toBe(true);
          
          // Delete the entry
          const deleted = cache.delete(key, storage);
          expect(deleted).toBe(true);
          
          // Verify data is no longer retrievable
          expect(cache.get(key, storage)).toBeNull();
          expect(cache.has(key, storage)).toBe(false);
          
          // Trying to delete again should return false
          const deletedAgain = cache.delete(key, storage);
          expect(deletedAgain).toBe(false);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 34d: GetOrSet pattern behavior - should use cache when available, fetch when not', async () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    await fc.assert(
      fc.asyncProperty(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string({ minLength: 1 }),
          fc.integer(),
          fc.boolean(),
          fc.array(fc.string(), { minLength: 1, maxLength: 3 })
        ), // serializable data to return from fetch function (excluding null to avoid confusion)
        fc.constantFrom('memory', 'localStorage'), // storage type
        async (key, data, storage) => {
          // Skip undefined values
          fc.pre(data !== undefined);
          
          let fetchCallCount = 0;
          const mockFetch = vi.fn(async () => {
            fetchCallCount++;
            return data;
          });
          
          // First call should fetch and cache
          const result1 = await cache.getOrSet(key, mockFetch, { storage });
          expect(result1).toEqual(data);
          expect(fetchCallCount).toBe(1);
          
          // Second call should use cache, not fetch
          const result2 = await cache.getOrSet(key, mockFetch, { storage });
          expect(result2).toEqual(data);
          expect(fetchCallCount).toBe(1); // Should still be 1, not 2
          
          // Verify both results are identical
          expect(result1).toEqual(result2);
        }
      ),
      { numRuns: 30 } // Reduced runs due to async nature
    );
  }, 10000); // Increase test timeout

  it('Property 34e: Cache isolation between storage types - memory and localStorage should be independent', () => {
    // Feature: vue-frontend-enhancement, Property 34: Data Caching Behavior
    
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }), // cache key
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // memory data
        fc.oneof(
          fc.string(),
          fc.integer(),
          fc.boolean(),
          fc.constant(null),
          fc.array(fc.string(), { maxLength: 3 })
        ), // localStorage data
        (key, memoryData, localStorageData) => {
          // Skip undefined values and ensure different data for meaningful test
          fc.pre(memoryData !== undefined && localStorageData !== undefined);
          fc.pre(JSON.stringify(memoryData) !== JSON.stringify(localStorageData));
          
          // Store different data in each storage type
          cache.set(key, memoryData, { storage: 'memory' });
          cache.set(key, localStorageData, { storage: 'localStorage' });
          
          // Verify each storage type returns its own data
          expect(cache.get(key, 'memory')).toEqual(memoryData);
          expect(cache.get(key, 'localStorage')).toEqual(localStorageData);
          
          // Verify they are different
          expect(cache.get(key, 'memory')).not.toEqual(cache.get(key, 'localStorage'));
        }
      ),
      { numRuns: 100 }
    );
  });
});