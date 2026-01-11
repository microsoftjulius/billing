/**
 * Comprehensive error handling service
 */

import { AxiosError } from 'axios';
import { useAppStore } from '@/store/modules/app';
import { useRealtimeStore } from '@/store/modules/realtime';
import type { ApiError, Notification } from '@/types';

export interface ErrorContext {
  component?: string;
  action?: string;
  userId?: string;
  timestamp?: string;
  userAgent?: string;
  url?: string;
  additionalData?: Record<string, any>;
}

export interface ErrorRecoveryAction {
  label: string;
  action: () => void;
  primary?: boolean;
}

export interface ErrorHandlerOptions {
  showNotification?: boolean;
  logError?: boolean;
  context?: ErrorContext;
  recoveryActions?: ErrorRecoveryAction[];
  fallbackMessage?: string;
}

class ErrorHandlerService {
  private errorLog: Array<{
    error: Error;
    context: ErrorContext;
    timestamp: string;
  }> = [];

  private maxLogSize = 100;

  /**
   * Handle API errors with user-friendly messages
   */
  handleApiError(
    error: AxiosError<ApiError>,
    options: ErrorHandlerOptions = {}
  ): void {
    const {
      showNotification = true,
      logError = true,
      context = {},
      recoveryActions = [],
      fallbackMessage = 'An unexpected error occurred'
    } = options;

    const appStore = useAppStore();
    const { response, request, message } = error;

    // Log error if enabled
    if (logError) {
      this.logError(error, context);
    }

    // Handle different types of API errors
    if (response) {
      // Server responded with error status
      const { status, data } = response;
      
      switch (status) {
        case 401:
          this.handleAuthenticationError(showNotification, recoveryActions);
          break;
        case 403:
          this.handleAuthorizationError(showNotification, recoveryActions);
          break;
        case 422:
          this.handleValidationError(data, showNotification, recoveryActions);
          break;
        case 429:
          this.handleRateLimitError(showNotification, recoveryActions);
          break;
        case 500:
        case 502:
        case 503:
        case 504:
          this.handleServerError(status, showNotification, recoveryActions);
          break;
        default:
          this.handleGenericError(
            data?.message || fallbackMessage,
            showNotification,
            recoveryActions
          );
      }
    } else if (request) {
      // Network error
      this.handleNetworkError(showNotification, recoveryActions);
    } else {
      // Request setup error
      this.handleRequestError(message, showNotification, recoveryActions);
    }
  }

  /**
   * Handle authentication errors (401)
   */
  private handleAuthenticationError(
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    const appStore = useAppStore();
    
    // Clear user session
    appStore.setUser(null);
    localStorage.removeItem('auth_token');

    if (showNotification) {
      const actions = [
        {
          label: 'Login Again',
          action: () => {
            window.location.href = '/login';
          },
          primary: true
        },
        ...recoveryActions
      ];

      appStore.addNotification({
        type: 'error',
        title: 'Authentication Required',
        message: 'Your session has expired. Please log in again to continue.',
        duration: 0, // Persistent
        actions
      });
    }
  }

  /**
   * Handle authorization errors (403)
   */
  private handleAuthorizationError(
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      const actions = [
        {
          label: 'Go to Dashboard',
          action: () => {
            window.location.href = '/app/dashboard';
          }
        },
        ...recoveryActions
      ];

      appStore.addNotification({
        type: 'error',
        title: 'Access Denied',
        message: 'You do not have permission to perform this action.',
        duration: 8000,
        actions
      });
    }
  }

  /**
   * Handle validation errors (422)
   */
  private handleValidationError(
    data: ApiError,
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification && data.errors) {
      const appStore = useAppStore();
      const errorMessages = Object.values(data.errors).flat();
      
      appStore.addNotification({
        type: 'error',
        title: 'Validation Error',
        message: errorMessages.join(', '),
        duration: 10000,
        actions: recoveryActions
      });
    }
  }

  /**
   * Handle rate limit errors (429)
   */
  private handleRateLimitError(
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      const actions = [
        {
          label: 'Try Again Later',
          action: () => {
            // Refresh page after delay
            setTimeout(() => {
              window.location.reload();
            }, 60000);
          }
        },
        ...recoveryActions
      ];

      appStore.addNotification({
        type: 'warning',
        title: 'Rate Limit Exceeded',
        message: 'Too many requests. Please wait a moment before trying again.',
        duration: 15000,
        actions
      });
    }
  }

  /**
   * Handle server errors (5xx)
   */
  private handleServerError(
    status: number,
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      const actions = [
        {
          label: 'Retry',
          action: () => {
            window.location.reload();
          },
          primary: true
        },
        {
          label: 'Report Issue',
          action: () => {
            this.reportError();
          }
        },
        ...recoveryActions
      ];

      const messages = {
        500: 'Internal server error occurred. Our team has been notified.',
        502: 'Service temporarily unavailable. Please try again in a few minutes.',
        503: 'Service is under maintenance. Please try again later.',
        504: 'Request timeout. The server took too long to respond.'
      };

      appStore.addNotification({
        type: 'error',
        title: 'Server Error',
        message: messages[status as keyof typeof messages] || 'Server error occurred.',
        duration: 0, // Persistent for server errors
        actions
      });
    }
  }

  /**
   * Handle network errors
   */
  private handleNetworkError(
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      const actions = [
        {
          label: 'Check Connection',
          action: () => {
            this.checkNetworkStatus();
          },
          primary: true
        },
        {
          label: 'Retry',
          action: () => {
            window.location.reload();
          }
        },
        ...recoveryActions
      ];

      appStore.addNotification({
        type: 'error',
        title: 'Network Error',
        message: 'Unable to connect to the server. Please check your internet connection.',
        duration: 0, // Persistent for network errors
        actions
      });
    }
  }

  /**
   * Handle generic errors
   */
  private handleGenericError(
    message: string,
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      
      appStore.addNotification({
        type: 'error',
        title: 'Error',
        message,
        duration: 8000,
        actions: recoveryActions
      });
    }
  }

  /**
   * Handle request setup errors
   */
  private handleRequestError(
    message: string,
    showNotification: boolean,
    recoveryActions: ErrorRecoveryAction[]
  ): void {
    if (showNotification) {
      const appStore = useAppStore();
      
      appStore.addNotification({
        type: 'error',
        title: 'Request Error',
        message: `Failed to send request: ${message}`,
        duration: 8000,
        actions: recoveryActions
      });
    }
  }

  /**
   * Handle WebSocket connection errors
   */
  handleWebSocketError(
    error: Event | Error,
    options: ErrorHandlerOptions = {}
  ): void {
    const {
      showNotification = true,
      logError = true,
      context = {},
      recoveryActions = []
    } = options;

    if (logError) {
      this.logError(error instanceof Error ? error : new Error('WebSocket error'), {
        ...context,
        component: 'WebSocket'
      });
    }

    if (showNotification) {
      const appStore = useAppStore();
      const realtimeStore = useRealtimeStore();
      
      const actions = [
        {
          label: 'Reconnect',
          action: () => {
            // Trigger manual reconnection
            import('@/services/websocket').then(({ webSocketService }) => {
              webSocketService.reconnect();
            });
          },
          primary: true
        },
        {
          label: 'Refresh Page',
          action: () => {
            window.location.reload();
          }
        },
        ...recoveryActions
      ];

      // Only show notification if not already reconnecting
      if (realtimeStore.connectionStatus !== 'reconnecting') {
        appStore.addNotification({
          type: 'warning',
          title: 'Connection Lost',
          message: 'Real-time connection lost. Some features may not work properly.',
          duration: 10000,
          actions
        });
      }
    }
  }

  /**
   * Handle form validation errors
   */
  handleFormError(
    errors: Record<string, string[]>,
    options: ErrorHandlerOptions = {}
  ): void {
    const { showNotification = true } = options;

    if (showNotification) {
      const appStore = useAppStore();
      const errorMessages = Object.entries(errors)
        .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
        .join('\n');

      appStore.addNotification({
        type: 'error',
        title: 'Form Validation Error',
        message: errorMessages,
        duration: 10000
      });
    }
  }

  /**
   * Handle JavaScript runtime errors
   */
  handleRuntimeError(
    error: Error,
    options: ErrorHandlerOptions = {}
  ): void {
    const {
      showNotification = true,
      logError = true,
      context = {}
    } = options;

    if (logError) {
      this.logError(error, {
        ...context,
        component: 'Runtime'
      });
    }

    if (showNotification) {
      const appStore = useAppStore();
      
      appStore.addNotification({
        type: 'error',
        title: 'Application Error',
        message: 'An unexpected error occurred. Please refresh the page if the problem persists.',
        duration: 10000,
        actions: [
          {
            label: 'Refresh Page',
            action: () => {
              window.location.reload();
            },
            primary: true
          },
          {
            label: 'Report Issue',
            action: () => {
              this.reportError(error);
            }
          }
        ]
      });
    }
  }

  /**
   * Log error to internal storage and external service
   */
  private logError(error: Error | Event, context: ErrorContext = {}): void {
    const errorEntry = {
      error: error instanceof Error ? error : new Error('Unknown error'),
      context: {
        ...context,
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href,
        userId: useAppStore().user?.id
      },
      timestamp: new Date().toISOString()
    };

    // Add to internal log
    this.errorLog.unshift(errorEntry);
    
    // Keep log size manageable
    if (this.errorLog.length > this.maxLogSize) {
      this.errorLog = this.errorLog.slice(0, this.maxLogSize);
    }

    // Log to console in development
    if (import.meta.env.DEV) {
      console.error('Error logged:', errorEntry);
    }

    // Send to external logging service in production
    if (import.meta.env.PROD) {
      this.sendToLoggingService(errorEntry);
    }
  }

  /**
   * Send error to external logging service
   */
  private async sendToLoggingService(errorEntry: any): Promise<void> {
    try {
      // Send to Laravel backend error logging endpoint
      await fetch('/api/v1/errors', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(errorEntry)
      });
    } catch (loggingError) {
      console.error('Failed to send error to logging service:', loggingError);
      
      // Fallback: store in localStorage for later retry
      try {
        const failedLogs = JSON.parse(localStorage.getItem('failed_error_logs') || '[]');
        failedLogs.push({
          ...errorEntry,
          failedAt: new Date().toISOString()
        });
        
        // Keep only last 10 failed logs to prevent storage overflow
        if (failedLogs.length > 10) {
          failedLogs.splice(0, failedLogs.length - 10);
        }
        
        localStorage.setItem('failed_error_logs', JSON.stringify(failedLogs));
      } catch (storageError) {
        console.error('Failed to store error log in localStorage:', storageError);
      }
    }
  }

  /**
   * Retry sending failed error logs
   */
  async retryFailedLogs(): Promise<void> {
    try {
      const failedLogs = JSON.parse(localStorage.getItem('failed_error_logs') || '[]');
      
      if (failedLogs.length === 0) return;
      
      const successfulLogs: number[] = [];
      
      for (let i = 0; i < failedLogs.length; i++) {
        try {
          await this.sendToLoggingService(failedLogs[i]);
          successfulLogs.push(i);
        } catch (error) {
          // Skip failed logs, they'll be retried next time
          console.warn('Failed to retry error log:', error);
        }
      }
      
      // Remove successfully sent logs
      if (successfulLogs.length > 0) {
        const remainingLogs = failedLogs.filter((_, index) => !successfulLogs.includes(index));
        localStorage.setItem('failed_error_logs', JSON.stringify(remainingLogs));
      }
    } catch (error) {
      console.error('Failed to retry error logs:', error);
    }
  }

  /**
   * Check network connectivity status
   */
  private checkNetworkStatus(): void {
    const appStore = useAppStore();
    
    if (navigator.onLine) {
      appStore.addNotification({
        type: 'info',
        title: 'Network Status',
        message: 'Your device appears to be online. The issue may be with our servers.',
        duration: 5000
      });
    } else {
      appStore.addNotification({
        type: 'warning',
        title: 'Network Status',
        message: 'Your device appears to be offline. Please check your internet connection.',
        duration: 8000
      });
    }
  }

  /**
   * Report error to support team
   */
  private reportError(error?: Error): void {
    const appStore = useAppStore();
    
    // In a real application, this would open a support ticket or feedback form
    appStore.addNotification({
      type: 'info',
      title: 'Error Reported',
      message: 'Thank you for reporting this issue. Our team has been notified.',
      duration: 5000
    });
  }

  /**
   * Get error log for debugging
   */
  getErrorLog(): typeof this.errorLog {
    return [...this.errorLog];
  }

  /**
   * Clear error log
   */
  clearErrorLog(): void {
    this.errorLog = [];
  }

  /**
   * Get error statistics
   */
  getErrorStats(): {
    totalErrors: number;
    errorsByType: Record<string, number>;
    recentErrors: number;
  } {
    const now = Date.now();
    const oneHourAgo = now - (60 * 60 * 1000);
    
    const recentErrors = this.errorLog.filter(
      entry => new Date(entry.timestamp).getTime() > oneHourAgo
    ).length;

    const errorsByType = this.errorLog.reduce((acc, entry) => {
      const type = entry.error.constructor.name;
      acc[type] = (acc[type] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    return {
      totalErrors: this.errorLog.length,
      errorsByType,
      recentErrors
    };
  }
}

// Export singleton instance
export const errorHandler = new ErrorHandlerService();

// Global error handler setup
export const setupGlobalErrorHandling = (): void => {
  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    errorHandler.handleRuntimeError(
      new Error(`Unhandled promise rejection: ${event.reason}`),
      {
        context: {
          component: 'Global',
          action: 'unhandledrejection'
        }
      }
    );
  });

  // Handle uncaught JavaScript errors
  window.addEventListener('error', (event) => {
    console.error('Uncaught error:', event.error);
    errorHandler.handleRuntimeError(event.error || new Error(event.message), {
      context: {
        component: 'Global',
        action: 'error',
        additionalData: {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno
        }
      }
    });
  });

  // Handle network status changes
  window.addEventListener('online', () => {
    const appStore = useAppStore();
    appStore.addNotification({
      type: 'success',
      title: 'Connection Restored',
      message: 'Your internet connection has been restored.',
      duration: 3000
    });
    
    // Retry failed error logs when connection is restored
    errorHandler.retryFailedLogs();
  });

  window.addEventListener('offline', () => {
    const appStore = useAppStore();
    appStore.addNotification({
      type: 'warning',
      title: 'Connection Lost',
      message: 'You are currently offline. Some features may not work properly.',
      duration: 0 // Persistent while offline
    });
  });

  // Periodically retry failed error logs
  setInterval(() => {
    if (navigator.onLine) {
      errorHandler.retryFailedLogs();
    }
  }, 5 * 60 * 1000); // Every 5 minutes
};