/**
 * Composable for performance monitoring and optimization
 */

import { ref, computed, onMounted, onUnmounted } from 'vue';

interface PerformanceMetrics {
  renderTime: number;
  memoryUsage: number;
  componentCount: number;
  apiCallCount: number;
  cacheHitRate: number;
  lastUpdate: number;
}

interface PerformanceThresholds {
  renderTime: number; // ms
  memoryUsage: number; // MB
  apiCallsPerMinute: number;
  cacheHitRate: number; // percentage
}

export function usePerformanceMonitor(thresholds: PerformanceThresholds = {
  renderTime: 16, // 60fps = 16ms per frame
  memoryUsage: 100, // 100MB
  apiCallsPerMinute: 60,
  cacheHitRate: 0.8, // 80%
}) {
  const metrics = ref<PerformanceMetrics>({
    renderTime: 0,
    memoryUsage: 0,
    componentCount: 0,
    apiCallCount: 0,
    cacheHitRate: 0,
    lastUpdate: Date.now(),
  });

  const warnings = ref<string[]>([]);
  const isMonitoring = ref(false);
  const observer = ref<PerformanceObserver | null>(null);

  // API call tracking
  const apiCalls = ref<{ timestamp: number; duration: number; cached: boolean }[]>([]);
  const renderTimes = ref<number[]>([]);

  /**
   * Start performance monitoring
   */
  const startMonitoring = () => {
    if (isMonitoring.value) return;

    isMonitoring.value = true;
    warnings.value = [];

    // Monitor paint and navigation timing
    if ('PerformanceObserver' in window) {
      observer.value = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        
        entries.forEach((entry) => {
          if (entry.entryType === 'paint') {
            if (entry.name === 'first-contentful-paint') {
              metrics.value.renderTime = entry.startTime;
            }
          } else if (entry.entryType === 'measure') {
            if (entry.name.startsWith('vue-render')) {
              renderTimes.value.push(entry.duration);
              if (renderTimes.value.length > 100) {
                renderTimes.value.shift(); // Keep only last 100 measurements
              }
              
              const avgRenderTime = renderTimes.value.reduce((a, b) => a + b, 0) / renderTimes.value.length;
              metrics.value.renderTime = avgRenderTime;
              
              if (avgRenderTime > thresholds.renderTime) {
                addWarning(`Slow rendering detected: ${avgRenderTime.toFixed(2)}ms (threshold: ${thresholds.renderTime}ms)`);
              }
            }
          }
        });
      });

      try {
        observer.value.observe({ entryTypes: ['paint', 'measure', 'navigation'] });
      } catch (error) {
        console.warn('Performance observer not fully supported:', error);
      }
    }

    // Start memory monitoring
    startMemoryMonitoring();
    
    // Start periodic updates
    startPeriodicUpdates();
  };

  /**
   * Stop performance monitoring
   */
  const stopMonitoring = () => {
    isMonitoring.value = false;
    
    if (observer.value) {
      observer.value.disconnect();
      observer.value = null;
    }
  };

  /**
   * Monitor memory usage
   */
  const startMemoryMonitoring = () => {
    const updateMemoryUsage = () => {
      if ('memory' in performance) {
        const memory = (performance as any).memory;
        const usedMB = memory.usedJSHeapSize / 1024 / 1024;
        metrics.value.memoryUsage = usedMB;
        
        if (usedMB > thresholds.memoryUsage) {
          addWarning(`High memory usage: ${usedMB.toFixed(2)}MB (threshold: ${thresholds.memoryUsage}MB)`);
        }
      }
    };

    // Update memory usage every 5 seconds
    const memoryInterval = setInterval(updateMemoryUsage, 5000);
    
    onUnmounted(() => {
      clearInterval(memoryInterval);
    });
  };

  /**
   * Start periodic metric updates
   */
  const startPeriodicUpdates = () => {
    const updateInterval = setInterval(() => {
      updateMetrics();
    }, 10000); // Update every 10 seconds

    onUnmounted(() => {
      clearInterval(updateInterval);
    });
  };

  /**
   * Update all metrics
   */
  const updateMetrics = () => {
    // Update API call metrics
    const now = Date.now();
    const oneMinuteAgo = now - 60000;
    
    // Filter API calls from last minute
    const recentCalls = apiCalls.value.filter(call => call.timestamp > oneMinuteAgo);
    metrics.value.apiCallCount = recentCalls.length;
    
    // Calculate cache hit rate
    const cachedCalls = recentCalls.filter(call => call.cached).length;
    metrics.value.cacheHitRate = recentCalls.length > 0 ? cachedCalls / recentCalls.length : 0;
    
    // Check thresholds
    if (metrics.value.apiCallCount > thresholds.apiCallsPerMinute) {
      addWarning(`High API call rate: ${metrics.value.apiCallCount} calls/min (threshold: ${thresholds.apiCallsPerMinute})`);
    }
    
    if (metrics.value.cacheHitRate < thresholds.cacheHitRate) {
      addWarning(`Low cache hit rate: ${(metrics.value.cacheHitRate * 100).toFixed(1)}% (threshold: ${(thresholds.cacheHitRate * 100)}%)`);
    }
    
    metrics.value.lastUpdate = now;
    
    // Clean up old API calls
    apiCalls.value = recentCalls;
  };

  /**
   * Track API call
   */
  const trackApiCall = (duration: number, cached: boolean = false) => {
    apiCalls.value.push({
      timestamp: Date.now(),
      duration,
      cached,
    });
  };

  /**
   * Track component render
   */
  const trackRender = (componentName: string, renderFn: () => void) => {
    const startTime = performance.now();
    
    performance.mark(`${componentName}-render-start`);
    renderFn();
    performance.mark(`${componentName}-render-end`);
    
    performance.measure(
      `vue-render-${componentName}`,
      `${componentName}-render-start`,
      `${componentName}-render-end`
    );
    
    const endTime = performance.now();
    const duration = endTime - startTime;
    
    renderTimes.value.push(duration);
    if (renderTimes.value.length > 100) {
      renderTimes.value.shift();
    }
  };

  /**
   * Add performance warning
   */
  const addWarning = (message: string) => {
    if (!warnings.value.includes(message)) {
      warnings.value.push(message);
      console.warn(`Performance Warning: ${message}`);
      
      // Keep only last 10 warnings
      if (warnings.value.length > 10) {
        warnings.value.shift();
      }
    }
  };

  /**
   * Clear warnings
   */
  const clearWarnings = () => {
    warnings.value = [];
  };

  /**
   * Get performance score (0-100)
   */
  const performanceScore = computed(() => {
    let score = 100;
    
    // Render time score (0-25 points)
    const renderScore = Math.max(0, 25 - (metrics.value.renderTime / thresholds.renderTime) * 25);
    
    // Memory usage score (0-25 points)
    const memoryScore = Math.max(0, 25 - (metrics.value.memoryUsage / thresholds.memoryUsage) * 25);
    
    // API call rate score (0-25 points)
    const apiScore = Math.max(0, 25 - (metrics.value.apiCallCount / thresholds.apiCallsPerMinute) * 25);
    
    // Cache hit rate score (0-25 points)
    const cacheScore = metrics.value.cacheHitRate * 25;
    
    return Math.round(renderScore + memoryScore + apiScore + cacheScore);
  });

  /**
   * Get performance recommendations
   */
  const recommendations = computed(() => {
    const recs: string[] = [];
    
    if (metrics.value.renderTime > thresholds.renderTime) {
      recs.push('Consider using v-memo or lazy loading for heavy components');
      recs.push('Optimize computed properties and watchers');
    }
    
    if (metrics.value.memoryUsage > thresholds.memoryUsage) {
      recs.push('Check for memory leaks in event listeners');
      recs.push('Consider using object pooling for frequently created objects');
    }
    
    if (metrics.value.apiCallCount > thresholds.apiCallsPerMinute) {
      recs.push('Implement request debouncing');
      recs.push('Use pagination or lazy loading for large datasets');
    }
    
    if (metrics.value.cacheHitRate < thresholds.cacheHitRate) {
      recs.push('Increase cache TTL for stable data');
      recs.push('Implement more aggressive caching strategies');
    }
    
    return recs;
  });

  /**
   * Export performance report
   */
  const exportReport = () => {
    const report = {
      timestamp: new Date().toISOString(),
      metrics: metrics.value,
      score: performanceScore.value,
      warnings: warnings.value,
      recommendations: recommendations.value,
      thresholds,
    };
    
    const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.href = url;
    link.download = `performance-report-${Date.now()}.json`;
    link.click();
    
    URL.revokeObjectURL(url);
  };

  onMounted(() => {
    startMonitoring();
  });

  onUnmounted(() => {
    stopMonitoring();
  });

  return {
    // State
    metrics: computed(() => metrics.value),
    warnings: computed(() => warnings.value),
    isMonitoring: computed(() => isMonitoring.value),
    performanceScore,
    recommendations,
    
    // Methods
    startMonitoring,
    stopMonitoring,
    trackApiCall,
    trackRender,
    clearWarnings,
    exportReport,
    updateMetrics,
  };
}