/**
 * Composable for implementing lazy loading in DataTable and other components
 */

import { ref, computed, onMounted, onUnmounted } from 'vue';

interface LazyLoadingOptions {
  pageSize?: number;
  threshold?: number; // Distance from bottom to trigger load
  initialLoad?: number; // Number of items to load initially
  loadIncrement?: number; // Number of items to load on each trigger
}

interface LazyLoadingState<T> {
  items: T[];
  loading: boolean;
  hasMore: boolean;
  error: Error | null;
}

export function useLazyLoading<T = any>(
  loadFunction: (offset: number, limit: number) => Promise<{ data: T[]; hasMore: boolean }>,
  options: LazyLoadingOptions = {}
) {
  const {
    pageSize = 50,
    threshold = 200,
    initialLoad = 20,
    loadIncrement = 20,
  } = options;

  const state = ref<LazyLoadingState<T>>({
    items: [],
    loading: false,
    hasMore: true,
    error: null,
  });

  const containerRef = ref<HTMLElement | null>(null);
  const observer = ref<IntersectionObserver | null>(null);
  const sentinelRef = ref<HTMLElement | null>(null);

  /**
   * Load more items
   */
  const loadMore = async () => {
    if (state.value.loading || !state.value.hasMore) {
      return;
    }

    try {
      state.value.loading = true;
      state.value.error = null;

      const offset = state.value.items.length;
      const limit = offset === 0 ? initialLoad : loadIncrement;

      const result = await loadFunction(offset, limit);

      state.value.items.push(...result.data);
      state.value.hasMore = result.hasMore;
    } catch (error) {
      state.value.error = error as Error;
    } finally {
      state.value.loading = false;
    }
  };

  /**
   * Reset and reload from beginning
   */
  const reset = async () => {
    state.value.items = [];
    state.value.hasMore = true;
    state.value.error = null;
    await loadMore();
  };

  /**
   * Setup intersection observer for automatic loading
   */
  const setupIntersectionObserver = () => {
    if (!sentinelRef.value) return;

    observer.value = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (entry.isIntersecting && state.value.hasMore && !state.value.loading) {
          loadMore();
        }
      },
      {
        root: containerRef.value,
        rootMargin: `${threshold}px`,
        threshold: 0.1,
      }
    );

    observer.value.observe(sentinelRef.value);
  };

  /**
   * Setup scroll-based lazy loading
   */
  const setupScrollListener = () => {
    if (!containerRef.value) return;

    const handleScroll = () => {
      if (!containerRef.value || state.value.loading || !state.value.hasMore) {
        return;
      }

      const { scrollTop, scrollHeight, clientHeight } = containerRef.value;
      const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

      if (distanceFromBottom < threshold) {
        loadMore();
      }
    };

    containerRef.value.addEventListener('scroll', handleScroll, { passive: true });

    return () => {
      containerRef.value?.removeEventListener('scroll', handleScroll);
    };
  };

  /**
   * Virtualized rendering for large datasets
   */
  const useVirtualization = (itemHeight: number, containerHeight: number) => {
    const scrollTop = ref(0);
    const visibleStart = computed(() => Math.floor(scrollTop.value / itemHeight));
    const visibleEnd = computed(() => {
      const visibleCount = Math.ceil(containerHeight / itemHeight);
      return Math.min(visibleStart.value + visibleCount + 5, state.value.items.length); // +5 for buffer
    });

    const visibleItems = computed(() => {
      return state.value.items.slice(visibleStart.value, visibleEnd.value).map((item, index) => ({
        item,
        index: visibleStart.value + index,
        top: (visibleStart.value + index) * itemHeight,
      }));
    });

    const totalHeight = computed(() => state.value.items.length * itemHeight);

    const handleScroll = (event: Event) => {
      const target = event.target as HTMLElement;
      scrollTop.value = target.scrollTop;
    };

    return {
      visibleItems,
      totalHeight,
      handleScroll,
      visibleStart,
      visibleEnd,
    };
  };

  /**
   * Chunked processing for large datasets
   */
  const processInChunks = async <R>(
    items: T[],
    processor: (chunk: T[]) => Promise<R[]>,
    chunkSize = 100
  ): Promise<R[]> => {
    const results: R[] = [];
    
    for (let i = 0; i < items.length; i += chunkSize) {
      const chunk = items.slice(i, i + chunkSize);
      const chunkResults = await processor(chunk);
      results.push(...chunkResults);
      
      // Allow UI to update between chunks
      await new Promise(resolve => setTimeout(resolve, 0));
    }
    
    return results;
  };

  /**
   * Debounced search for lazy loaded data
   */
  const createDebouncedSearch = (
    searchFunction: (query: string, offset: number, limit: number) => Promise<{ data: T[]; hasMore: boolean }>,
    debounceMs = 300
  ) => {
    let searchTimeout: NodeJS.Timeout;
    const searchState = ref<LazyLoadingState<T>>({
      items: [],
      loading: false,
      hasMore: true,
      error: null,
    });

    const search = (query: string) => {
      clearTimeout(searchTimeout);
      
      if (!query.trim()) {
        searchState.value.items = [];
        searchState.value.hasMore = true;
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          searchState.value.loading = true;
          searchState.value.error = null;
          searchState.value.items = [];

          const result = await searchFunction(query, 0, initialLoad);
          searchState.value.items = result.data;
          searchState.value.hasMore = result.hasMore;
        } catch (error) {
          searchState.value.error = error as Error;
        } finally {
          searchState.value.loading = false;
        }
      }, debounceMs);
    };

    const loadMoreSearch = async (query: string) => {
      if (searchState.value.loading || !searchState.value.hasMore) {
        return;
      }

      try {
        searchState.value.loading = true;
        const offset = searchState.value.items.length;
        const result = await searchFunction(query, offset, loadIncrement);
        
        searchState.value.items.push(...result.data);
        searchState.value.hasMore = result.hasMore;
      } catch (error) {
        searchState.value.error = error as Error;
      } finally {
        searchState.value.loading = false;
      }
    };

    return {
      searchState: computed(() => searchState.value),
      search,
      loadMoreSearch,
    };
  };

  onMounted(() => {
    // Initial load
    loadMore();
  });

  onUnmounted(() => {
    if (observer.value) {
      observer.value.disconnect();
    }
  });

  return {
    // State
    state: computed(() => state.value),
    items: computed(() => state.value.items),
    loading: computed(() => state.value.loading),
    hasMore: computed(() => state.value.hasMore),
    error: computed(() => state.value.error),
    
    // Refs for DOM elements
    containerRef,
    sentinelRef,
    
    // Methods
    loadMore,
    reset,
    setupIntersectionObserver,
    setupScrollListener,
    
    // Advanced features
    useVirtualization,
    processInChunks,
    createDebouncedSearch,
  };
}