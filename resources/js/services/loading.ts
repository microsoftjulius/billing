/**
 * Loading Service for managing loading indicators across the application
 * Provides centralized loading state management with automatic cleanup
 */

import { ref, computed } from 'vue';

export interface LoadingState {
  id: string;
  message?: string;
  startTime: number;
  minDuration?: number; // Minimum duration to show loading (prevents flashing)
}

class LoadingService {
  private loadingStates = ref<Map<string, LoadingState>>(new Map());
  private defaultMinDuration = 300; // 300ms minimum duration

  /**
   * Check if any loading operation is active
   */
  get isLoading() {
    return computed(() => this.loadingStates.value.size > 0);
  }

  /**
   * Get all active loading states
   */
  get activeStates() {
    return computed(() => Array.from(this.loadingStates.value.values()));
  }

  /**
   * Get loading state count
   */
  get loadingCount() {
    return computed(() => this.loadingStates.value.size);
  }

  /**
   * Start a loading operation
   */
  start(id: string, message?: string, minDuration?: number): void {
    const state: LoadingState = {
      id,
      message,
      startTime: Date.now(),
      minDuration: minDuration || this.defaultMinDuration,
    };

    this.loadingStates.value.set(id, state);
  }

  /**
   * Stop a loading operation
   */
  async stop(id: string): Promise<void> {
    const state = this.loadingStates.value.get(id);
    if (!state) {
      return;
    }

    const elapsed = Date.now() - state.startTime;
    const minDuration = state.minDuration || this.defaultMinDuration;

    if (elapsed < minDuration) {
      // Wait for minimum duration to prevent flashing
      await new Promise(resolve => setTimeout(resolve, minDuration - elapsed));
    }

    this.loadingStates.value.delete(id);
  }

  /**
   * Check if a specific loading operation is active
   */
  isActive(id: string): boolean {
    return this.loadingStates.value.has(id);
  }

  /**
   * Get a specific loading state
   */
  getState(id: string): LoadingState | undefined {
    return this.loadingStates.value.get(id);
  }

  /**
   * Clear all loading states
   */
  clear(): void {
    this.loadingStates.value.clear();
  }

  /**
   * Wrap an async operation with loading indicator
   */
  async withLoading<T>(
    id: string,
    operation: () => Promise<T>,
    message?: string,
    minDuration?: number
  ): Promise<T> {
    this.start(id, message, minDuration);
    
    try {
      const result = await operation();
      await this.stop(id);
      return result;
    } catch (error) {
      await this.stop(id);
      throw error;
    }
  }

  /**
   * Create a scoped loading manager for a specific context
   */
  createScope(prefix: string) {
    return {
      start: (id: string, message?: string, minDuration?: number) => 
        this.start(`${prefix}:${id}`, message, minDuration),
      stop: (id: string) => 
        this.stop(`${prefix}:${id}`),
      isActive: (id: string) => 
        this.isActive(`${prefix}:${id}`),
      withLoading: <T>(id: string, operation: () => Promise<T>, message?: string, minDuration?: number) =>
        this.withLoading(`${prefix}:${id}`, operation, message, minDuration),
    };
  }

  /**
   * Get loading statistics
   */
  getStats() {
    const states = Array.from(this.loadingStates.value.values());
    const now = Date.now();
    
    return {
      totalActive: states.length,
      averageDuration: states.length > 0 
        ? states.reduce((sum, state) => sum + (now - state.startTime), 0) / states.length 
        : 0,
      longestRunning: states.length > 0 
        ? Math.max(...states.map(state => now - state.startTime)) 
        : 0,
    };
  }
}

// Export singleton instance
export const loadingService = new LoadingService();

// Export class for testing
export { LoadingService };