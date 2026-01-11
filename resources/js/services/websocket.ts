import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import { errorHandler } from '@/services/errorHandler';
import type { MikroTikDevice, Payment, Voucher } from '@/types';

// Configure Pusher for Laravel Echo
window.Pusher = Pusher;

class WebSocketService {
  private echo: Echo | null = null;
  private reconnectTimer: NodeJS.Timeout | null = null;
  private heartbeatTimer: NodeJS.Timeout | null = null;
  private connectionAttempts = 0;
  private maxConnectionAttempts = 10;

  /**
   * Get realtime store instance
   */
  private getRealtimeStore() {
    return useRealtimeStore();
  }

  /**
   * Get app store instance
   */
  private getAppStore() {
    return useAppStore();
  }

  /**
   * Initialize WebSocket connection
   */
  public initialize(): void {
    if (this.echo) {
      this.disconnect();
    }

    try {
      this.echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        wssPort: import.meta.env.VITE_REVERB_PORT,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        auth: {
          headers: {
            Authorization: `Bearer ${this.getAuthToken()}`,
          },
        },
      });

      this.setupEventListeners();
      this.setupChannelSubscriptions();
      this.startHeartbeat();
    } catch (error) {
      errorHandler.handleWebSocketError(error as Error, {
        context: {
          component: 'WebSocket',
          action: 'initialize',
          additionalData: {
            attempt: this.connectionAttempts
          }
        }
      });
    }
  }

  /**
   * Get authentication token from app store
   */
  private getAuthToken(): string {
    return this.getAppStore().user?.token || '';
  }

  /**
   * Setup connection event listeners
   */
  private setupEventListeners(): void {
    if (!this.echo) return;

    const realtimeStore = this.getRealtimeStore();
    const appStore = this.getAppStore();

    // Connection established
    this.echo.connector.pusher.connection.bind('connected', () => {
      console.log('WebSocket connected');
      realtimeStore.setConnectionStatus('connected');
      realtimeStore.resetReconnectAttempts();
      this.connectionAttempts = 0;
      this.clearReconnectTimer();
      
      // Show success notification if this was a reconnection
      if (this.connectionAttempts > 0) {
        appStore.addNotification({
          type: 'success',
          title: 'Connection Restored',
          message: 'Real-time connection has been restored.',
          duration: 3000
        });
      }
    });

    // Connection lost
    this.echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('WebSocket disconnected');
      realtimeStore.setConnectionStatus('disconnected');
      this.scheduleReconnect();
    });

    // Connection error
    this.echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('WebSocket error:', error);
      realtimeStore.setConnectionStatus('disconnected');
      
      errorHandler.handleWebSocketError(new Error(`WebSocket connection error: ${error.type || 'unknown'}`), {
        context: {
          component: 'WebSocket',
          action: 'connection_error',
          additionalData: {
            errorType: error.type,
            errorData: error.data,
            attempt: this.connectionAttempts
          }
        }
      });
      
      this.scheduleReconnect();
    });

    // Connection state change
    this.echo.connector.pusher.connection.bind('state_change', (states: any) => {
      console.log('WebSocket state change:', states);
      
      if (states.current === 'connecting' || states.current === 'reconnecting') {
        realtimeStore.setConnectionStatus('reconnecting');
      } else if (states.current === 'connected') {
        realtimeStore.setConnectionStatus('connected');
      } else if (states.current === 'disconnected' || states.current === 'failed') {
        realtimeStore.setConnectionStatus('disconnected');
        
        if (states.current === 'failed') {
          errorHandler.handleWebSocketError(new Error('WebSocket connection failed'), {
            context: {
              component: 'WebSocket',
              action: 'connection_failed',
              additionalData: {
                previousState: states.previous,
                currentState: states.current
              }
            }
          });
        }
      }
    });

    // Handle subscription errors
    this.echo.connector.pusher.connection.bind('subscription_error', (error: any) => {
      errorHandler.handleWebSocketError(new Error(`WebSocket subscription error: ${error.error}`), {
        context: {
          component: 'WebSocket',
          action: 'subscription_error',
          additionalData: {
            channel: error.channel,
            error: error.error
          }
        }
      });
    });
  }

  /**
   * Setup channel subscriptions for real-time updates
   */
  private setupChannelSubscriptions(): void {
    if (!this.echo) return;

    const realtimeStore = this.getRealtimeStore();
    const appStore = this.getAppStore();

    try {
      // Subscribe to MikroTik device status updates
      this.echo.channel('mikrotik-status')
        .listen('MikroTikStatusUpdated', (event: { device: MikroTikDevice }) => {
          console.log('MikroTik status updated:', event.device);
          realtimeStore.updateMikroTikDevice(event.device.id, event.device);
        })
        .error((error: any) => {
          errorHandler.handleWebSocketError(new Error(`MikroTik channel error: ${error}`), {
            context: {
              component: 'WebSocket',
              action: 'mikrotik_channel_error',
              additionalData: { error }
            }
          });
        });

      // Subscribe to payment updates
      this.echo.channel('payments')
        .listen('PaymentProcessed', (event: { payment: Payment }) => {
          console.log('Payment processed:', event.payment);
          realtimeStore.addRecentPayment(event.payment);
        })
        .listen('PaymentStatusUpdated', (event: { payment_id: string; status: Payment['status'] }) => {
          console.log('Payment status updated:', event);
          realtimeStore.updatePaymentStatus(event.payment_id, event.status);
        })
        .error((error: any) => {
          errorHandler.handleWebSocketError(new Error(`Payment channel error: ${error}`), {
            context: {
              component: 'WebSocket',
              action: 'payment_channel_error',
              additionalData: { error }
            }
          });
        });

      // Subscribe to voucher updates
      this.echo.channel('vouchers')
        .listen('VoucherActivated', (event: { voucher: Voucher }) => {
          console.log('Voucher activated:', event.voucher);
          realtimeStore.updateVoucherStatus(event.voucher.id, 'active');
        })
        .listen('VoucherExpired', (event: { voucher_id: string }) => {
          console.log('Voucher expired:', event.voucher_id);
          realtimeStore.updateVoucherStatus(event.voucher_id, 'expired');
        })
        .listen('VoucherGenerated', (event: { vouchers: Voucher[] }) => {
          console.log('Vouchers generated:', event.vouchers);
          // Add new vouchers to the active vouchers list
          event.vouchers.forEach(voucher => {
            realtimeStore.activeVouchers.push(voucher);
          });
        })
        .error((error: any) => {
          errorHandler.handleWebSocketError(new Error(`Voucher channel error: ${error}`), {
            context: {
              component: 'WebSocket',
              action: 'voucher_channel_error',
              additionalData: { error }
            }
          });
        });

      // Subscribe to system notifications
      this.echo.channel('system-notifications')
        .listen('SystemAlert', (event: { type: string; message: string; data?: any }) => {
          console.log('System alert:', event);
          appStore.addNotification({
            id: Date.now().toString(),
            type: event.type as any,
            title: 'System Alert',
            message: event.message,
            duration: 10000,
          });
        })
        .error((error: any) => {
          errorHandler.handleWebSocketError(new Error(`System notifications channel error: ${error}`), {
            context: {
              component: 'WebSocket',
              action: 'system_notifications_channel_error',
              additionalData: { error }
            }
          });
        });

      // Subscribe to user-specific private channel
      const userId = appStore.user?.id;
      if (userId) {
        this.echo.private(`App.Models.User.${userId}`)
          .notification((notification: any) => {
            console.log('User notification:', notification);
            appStore.addNotification({
              id: Date.now().toString(),
              type: notification.type || 'info',
              title: notification.title || 'Notification',
              message: notification.message,
              duration: 5000,
            });
          })
          .error((error: any) => {
            errorHandler.handleWebSocketError(new Error(`User private channel error: ${error}`), {
              context: {
                component: 'WebSocket',
                action: 'user_private_channel_error',
                additionalData: { error, userId }
              }
            });
          });
      }
    } catch (error) {
      errorHandler.handleWebSocketError(error as Error, {
        context: {
          component: 'WebSocket',
          action: 'channel_subscription_setup_error'
        }
      });
    }
  }

  /**
   * Schedule reconnection attempt with exponential backoff
   */
  private scheduleReconnect(): void {
    if (this.reconnectTimer) return;

    const realtimeStore = this.getRealtimeStore();
    const appStore = this.getAppStore();

    this.connectionAttempts++;

    if (this.connectionAttempts > this.maxConnectionAttempts) {
      console.error('Max reconnection attempts reached');
      
      errorHandler.handleWebSocketError(new Error('Max WebSocket reconnection attempts reached'), {
        showNotification: false, // We'll show a custom notification
        context: {
          component: 'WebSocket',
          action: 'max_reconnect_attempts',
          additionalData: {
            attempts: this.connectionAttempts
          }
        }
      });

      appStore.addNotification({
        type: 'error',
        title: 'Connection Failed',
        message: 'Unable to establish real-time connection after multiple attempts. Please refresh the page.',
        duration: 0, // Persistent notification
        actions: [
          {
            label: 'Refresh Page',
            action: () => {
              window.location.reload();
            },
            primary: true
          },
          {
            label: 'Try Again',
            action: () => {
              this.connectionAttempts = 0;
              this.initialize();
            }
          },
          {
            label: 'Continue Offline',
            action: () => {
              appStore.removeNotification('connection-failed');
              realtimeStore.setConnectionStatus('offline');
            }
          }
        ]
      });
      return;
    }

    const canReconnect = realtimeStore.incrementReconnectAttempts();
    if (!canReconnect) {
      return;
    }

    const delay = Math.min(Math.pow(2, this.connectionAttempts - 1) * 1000, 30000); // Max 30 seconds
    console.log(`Scheduling reconnect in ${delay}ms (attempt ${this.connectionAttempts})`);

    // Show reconnection notification for longer delays
    if (delay > 5000 && this.connectionAttempts > 2) {
      appStore.addNotification({
        id: 'reconnecting',
        type: 'info',
        title: 'Reconnecting...',
        message: `Attempting to reconnect in ${Math.ceil(delay / 1000)} seconds...`,
        duration: delay - 1000
      });
    }

    this.reconnectTimer = setTimeout(() => {
      this.clearReconnectTimer();
      
      // Check if we're still online before attempting reconnect
      if (!navigator.onLine) {
        appStore.addNotification({
          type: 'warning',
          title: 'Offline',
          message: 'Cannot reconnect while offline. Will retry when connection is restored.',
          duration: 5000
        });
        
        // Wait for online event
        const handleOnline = () => {
          window.removeEventListener('online', handleOnline);
          this.initialize();
        };
        window.addEventListener('online', handleOnline);
        return;
      }
      
      this.initialize();
    }, delay);
  }

  /**
   * Clear reconnection timer
   */
  private clearReconnectTimer(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  /**
   * Start heartbeat to keep connection alive
   */
  private startHeartbeat(): void {
    this.stopHeartbeat();
    
    this.heartbeatTimer = setInterval(() => {
      if (this.echo && this.getRealtimeStore().connectionStatus === 'connected') {
        // Send a ping to keep connection alive
        this.echo.connector.pusher.send_event('pusher:ping', {});
      }
    }, 30000); // Ping every 30 seconds
  }

  /**
   * Stop heartbeat timer
   */
  private stopHeartbeat(): void {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  /**
   * Disconnect WebSocket
   */
  public disconnect(): void {
    if (this.echo) {
      this.echo.disconnect();
      this.echo = null;
    }
    
    this.clearReconnectTimer();
    this.stopHeartbeat();
    this.getRealtimeStore().setConnectionStatus('disconnected');
  }

  /**
   * Get current connection status
   */
  public getConnectionStatus(): string {
    return this.getRealtimeStore().connectionStatus;
  }

  /**
   * Manually trigger reconnection
   */
  public reconnect(): void {
    this.disconnect();
    this.getRealtimeStore().resetReconnectAttempts();
    this.initialize();
  }

  /**
   * Subscribe to a custom channel
   */
  public subscribeToChannel(channelName: string, eventName: string, callback: (data: any) => void): void {
    if (!this.echo) {
      console.warn('WebSocket not initialized');
      return;
    }

    this.echo.channel(channelName).listen(eventName, callback);
  }

  /**
   * Subscribe to a private channel
   */
  public subscribeToPrivateChannel(channelName: string, eventName: string, callback: (data: any) => void): void {
    if (!this.echo) {
      console.warn('WebSocket not initialized');
      return;
    }

    this.echo.private(channelName).listen(eventName, callback);
  }

  /**
   * Leave a channel
   */
  public leaveChannel(channelName: string): void {
    if (!this.echo) return;
    
    this.echo.leave(channelName);
  }
}

// Export singleton instance
export const webSocketService = new WebSocketService();

// Global type declaration for Pusher
declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}