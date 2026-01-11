<template>
  <div v-if="hasError" class="error-boundary">
    <div class="error-boundary__container">
      <div class="error-boundary__icon">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="currentColor">
          <path d="M24 4C12.96 4 4 12.96 4 24s8.96 20 20 20 20-8.96 20-20S35.04 4 24 4zm2 30h-4v-4h4v4zm0-8h-4V14h4v12z"/>
        </svg>
      </div>
      
      <div class="error-boundary__content">
        <h2 class="error-boundary__title">{{ title }}</h2>
        <p class="error-boundary__message">{{ message }}</p>
        
        <div v-if="showDetails && errorDetails" class="error-boundary__details">
          <details class="error-details">
            <summary class="error-details__summary">Technical Details</summary>
            <div class="error-details__content">
              <div class="error-info">
                <div class="error-info__item">
                  <strong>Error:</strong> {{ errorDetails.message }}
                </div>
                <div v-if="errorDetails.stack" class="error-info__item">
                  <strong>Stack Trace:</strong>
                  <pre class="error-stack">{{ errorDetails.stack }}</pre>
                </div>
                <div v-if="errorDetails.componentStack" class="error-info__item">
                  <strong>Component Stack:</strong>
                  <pre class="error-stack">{{ errorDetails.componentStack }}</pre>
                </div>
                <div class="error-info__item">
                  <strong>Timestamp:</strong> {{ errorDetails.timestamp }}
                </div>
                <div class="error-info__item">
                  <strong>User Agent:</strong> {{ errorDetails.userAgent }}
                </div>
                <div class="error-info__item">
                  <strong>URL:</strong> {{ errorDetails.url }}
                </div>
              </div>
            </div>
          </details>
        </div>
        
        <div class="error-boundary__actions">
          <button 
            @click="retry" 
            class="error-boundary__button error-boundary__button--primary"
          >
            {{ retryLabel }}
          </button>
          
          <button 
            @click="goHome" 
            class="error-boundary__button error-boundary__button--secondary"
          >
            {{ homeLabel }}
          </button>
          
          <button 
            v-if="showReportButton"
            @click="reportError" 
            class="error-boundary__button error-boundary__button--secondary"
          >
            {{ reportLabel }}
          </button>
          
          <button 
            @click="refresh" 
            class="error-boundary__button error-boundary__button--secondary"
          >
            {{ refreshLabel }}
          </button>
        </div>
        
        <div v-if="showFallbackSlot" class="error-boundary__fallback">
          <slot name="fallback" :error="errorDetails" :retry="retry" />
        </div>
      </div>
    </div>
  </div>
  
  <slot v-else />
</template>

<script setup lang="ts">
import { ref, onErrorCaptured, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { errorHandler } from '@/services/errorHandler';

interface Props {
  title?: string;
  message?: string;
  showDetails?: boolean;
  showReportButton?: boolean;
  showFallbackSlot?: boolean;
  retryLabel?: string;
  homeLabel?: string;
  reportLabel?: string;
  refreshLabel?: string;
  onError?: (error: Error, instance: any, info: string) => void;
  onRetry?: () => void;
}

interface Emits {
  (e: 'error', error: Error, instance: any, info: string): void;
  (e: 'retry'): void;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Something went wrong',
  message: 'An unexpected error occurred. Please try again or contact support if the problem persists.',
  showDetails: false,
  showReportButton: true,
  showFallbackSlot: false,
  retryLabel: 'Try Again',
  homeLabel: 'Go to Dashboard',
  reportLabel: 'Report Issue',
  refreshLabel: 'Refresh Page'
});

const emit = defineEmits<Emits>();

const router = useRouter();

const hasError = ref(false);
const errorDetails = ref<{
  message: string;
  stack?: string;
  componentStack?: string;
  timestamp: string;
  userAgent: string;
  url: string;
} | null>(null);

const retryCount = ref(0);
const maxRetries = 3;

const canRetry = computed(() => retryCount.value < maxRetries);

onErrorCaptured((error: Error, instance: any, info: string) => {
  console.error('Error boundary caught error:', error, info);
  
  hasError.value = true;
  errorDetails.value = {
    message: error.message,
    stack: error.stack,
    componentStack: info,
    timestamp: new Date().toISOString(),
    userAgent: navigator.userAgent,
    url: window.location.href
  };

  // Log error using error handler
  errorHandler.handleRuntimeError(error, {
    showNotification: false, // We're handling the UI ourselves
    context: {
      component: 'ErrorBoundary',
      action: 'error_captured',
      additionalData: {
        componentStack: info,
        retryCount: retryCount.value
      }
    }
  });

  // Call custom error handler if provided
  props.onError?.(error, instance, info);
  
  // Emit error event
  emit('error', error, instance, info);

  // Prevent the error from propagating further
  return false;
});

const retry = () => {
  if (!canRetry.value) {
    refresh();
    return;
  }

  retryCount.value++;
  hasError.value = false;
  errorDetails.value = null;
  
  // Call custom retry handler if provided
  props.onRetry?.();
  
  // Emit retry event
  emit('retry');
  
  // Force component re-render
  setTimeout(() => {
    if (hasError.value) {
      // If error persists after retry, show refresh option
      console.warn('Error persisted after retry, suggesting page refresh');
    }
  }, 100);
};

const goHome = () => {
  router.push('/app/dashboard').catch(() => {
    // If routing fails, fallback to window navigation
    window.location.href = '/app/dashboard';
  });
};

const reportError = () => {
  // In a real application, this would send error details to your error reporting service
  const errorReport = {
    error: errorDetails.value,
    userAgent: navigator.userAgent,
    url: window.location.href,
    timestamp: new Date().toISOString(),
    retryCount: retryCount.value
  };

  // For now, copy to clipboard and show notification
  navigator.clipboard.writeText(JSON.stringify(errorReport, null, 2)).then(() => {
    alert('Error details copied to clipboard. Please paste this information when reporting the issue.');
  }).catch(() => {
    // Fallback: show error details in a new window
    const errorWindow = window.open('', '_blank');
    if (errorWindow) {
      errorWindow.document.write(`
        <html>
          <head><title>Error Report</title></head>
          <body>
            <h1>Error Report</h1>
            <pre>${JSON.stringify(errorReport, null, 2)}</pre>
          </body>
        </html>
      `);
    }
  });
};

const refresh = () => {
  window.location.reload();
};

// Handle global errors that might not be caught by onErrorCaptured
onMounted(() => {
  const handleGlobalError = (event: ErrorEvent) => {
    if (!hasError.value) {
      hasError.value = true;
      errorDetails.value = {
        message: event.message,
        stack: event.error?.stack,
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href
      };
    }
  };

  const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
    if (!hasError.value) {
      hasError.value = true;
      errorDetails.value = {
        message: `Unhandled Promise Rejection: ${event.reason}`,
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href
      };
    }
  };

  window.addEventListener('error', handleGlobalError);
  window.addEventListener('unhandledrejection', handleUnhandledRejection);

  // Cleanup listeners on unmount
  return () => {
    window.removeEventListener('error', handleGlobalError);
    window.removeEventListener('unhandledrejection', handleUnhandledRejection);
  };
});
</script>

<style scoped>
.error-boundary {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--color-bg);
}

.error-boundary__container {
  max-width: 600px;
  text-align: center;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: 1rem;
  padding: 3rem 2rem;
  box-shadow: 0 4px 12px var(--color-shadow);
}

.error-boundary__icon {
  color: var(--color-error);
  margin-bottom: 1.5rem;
}

.error-boundary__title {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 1rem 0;
}

.error-boundary__message {
  font-size: 1rem;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin: 0 0 2rem 0;
}

.error-boundary__details {
  margin: 2rem 0;
  text-align: left;
}

.error-details {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
  overflow: hidden;
}

.error-details__summary {
  padding: 1rem;
  background: var(--color-bg-tertiary);
  cursor: pointer;
  font-weight: 500;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
}

.error-details__summary:hover {
  background: var(--color-bg-hover);
}

.error-details__content {
  padding: 1rem;
}

.error-info__item {
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.error-info__item:last-child {
  margin-bottom: 0;
}

.error-info__item strong {
  color: var(--color-text);
  display: block;
  margin-bottom: 0.25rem;
}

.error-stack {
  background: var(--color-bg-tertiary);
  border: 1px solid var(--color-border);
  border-radius: 0.25rem;
  padding: 0.75rem;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 200px;
  overflow-y: auto;
}

.error-boundary__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: center;
  margin-bottom: 2rem;
}

.error-boundary__button {
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
}

.error-boundary__button--primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.error-boundary__button--primary:hover {
  background: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
}

.error-boundary__button--secondary {
  background: transparent;
  color: var(--color-text);
  border-color: var(--color-border);
}

.error-boundary__button--secondary:hover {
  background: var(--color-bg-hover);
  border-color: var(--color-border-hover);
}

.error-boundary__fallback {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid var(--color-border);
}

/* Responsive design */
@media (max-width: 640px) {
  .error-boundary {
    padding: 1rem;
  }
  
  .error-boundary__container {
    padding: 2rem 1.5rem;
  }
  
  .error-boundary__actions {
    flex-direction: column;
  }
  
  .error-boundary__button {
    width: 100%;
  }
}

/* Dark theme adjustments */
@media (prefers-color-scheme: dark) {
  .error-boundary {
    --color-bg: #1a1a1a;
    --color-bg-secondary: #2d2d2d;
    --color-bg-tertiary: #3d3d3d;
    --color-bg-hover: #404040;
    --color-text: #ffffff;
    --color-text-secondary: #a0a0a0;
    --color-border: #404040;
    --color-border-hover: #505050;
    --color-shadow: rgba(0, 0, 0, 0.3);
    --color-error: #ef4444;
    --color-primary: #3b82f6;
    --color-primary-dark: #2563eb;
  }
}

[data-theme="dark"] .error-boundary {
  --color-bg: #1a1a1a;
  --color-bg-secondary: #2d2d2d;
  --color-bg-tertiary: #3d3d3d;
  --color-bg-hover: #404040;
  --color-text: #ffffff;
  --color-text-secondary: #a0a0a0;
  --color-border: #404040;
  --color-border-hover: #505050;
  --color-shadow: rgba(0, 0, 0, 0.3);
  --color-error: #ef4444;
  --color-primary: #3b82f6;
  --color-primary-dark: #2563eb;
}
</style>