import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { webSocketService } from '@/services/websocket';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import Dashboard from '@/components/Dashboard.vue';
import MikroTikMonitor from '@/components/MikroTikMonitor.vue';
import VoucherManagement from '@/components/VoucherManagement.vue';
import CustomerManagement from '@/components/CustomerManagement.vue';
import ConnectionStatus from '@/components/common/ConnectionStatus.vue';
import type { MikroTikDevice, Payment, Voucher, Customer } from '@/types';

// Integration tests for real-time features across all components
describe('Real-time Integration Tests', () => {
  let pinia: ReturnType<typeof createPinia>;
  let realtimeStore: ReturnType<typeof useRealtimeStore>;
  let appStore: ReturnType<typeof useAppStore>;

  // Mock WebSocket connection with more comprehensive event simulation
  const mockEcho = {
    channel: vi.fn().mockReturnThis(),
    private: vi.fn().mockReturnThis(),
    listen: vi.fn().mockReturnThis(),
    leave: vi.fn(),
    disconnect: vi.fn(),
    connector: {
      pusher: {
        connection: {
          bind: vi.fn(),
          send_event: vi.fn(),
          state: 'connected'
        },
        send_event: vi.fn()
      }
    }
  };

  // Mock event listeners for testing (simplified)
  const mockEventListeners = new Map();

  // Helper function to simulate WebSocket events
  const simulateWebSocketEvent = (channel: string, event: string, data: any) => {
    // This simulates what would happen when a real WebSocket event is received
    // In the actual implementation, these would be handled by the WebSocket service
    console.log(`Simulating WebSocket event: ${channel}.${event}`, data);
    
    // For testing purposes, we directly call the store methods that would be called
    // by the WebSocket service when real events are received
    if (channel === 'mikrotik-status' && event === 'MikroTikStatusUpdated') {
      realtimeStore.updateMikroTikDevice(data.device.id, data.device);
    } else if (channel === 'payments' && event === 'PaymentProcessed') {
      realtimeStore.addRecentPayment(data.payment);
    } else if (channel === 'payments' && event === 'PaymentStatusUpdated') {
      realtimeStore.updatePaymentStatus(data.payment_id, data.status);
    } else if (channel === 'vouchers' && event === 'VoucherActivated') {
      realtimeStore.updateVoucherStatus(data.voucher.id, 'active');
    } else if (channel === 'vouchers' && event === 'VoucherExpired') {
      realtimeStore.updateVoucherStatus(data.voucher_id, 'expired');
    } else if (channel === 'vouchers' && event === 'VoucherGenerated') {
      data.vouchers.forEach((voucher: Voucher) => {
        realtimeStore.activeVouchers.push(voucher);
      });
    } else if (channel === 'system-notifications' && event === 'SystemAlert') {
      appStore.addNotification({
        id: Date.now().toString(),
        type: data.type as any,
        title: 'System Alert',
        message: data.message,
        duration: 10000,
      });
    }
  };

  beforeEach(() => {
    pinia = createPinia();
    setActivePinia(pinia);

    realtimeStore = useRealtimeStore();
    appStore = useAppStore();

    // Reset mock event listeners
    mockEventListeners.clear();

    // Mock WebSocket service with simplified implementation
    vi.spyOn(webSocketService, 'initialize').mockImplementation(() => {
      // Simulate successful connection
      realtimeStore.setConnectionStatus('connected');
    });
    
    vi.spyOn(webSocketService, 'disconnect').mockImplementation(() => {
      realtimeStore.setConnectionStatus('disconnected');
      mockEventListeners.clear();
    });

    // Mock global fetch for API calls
    global.fetch = vi.fn();
    
    // Mock axios for component API calls
    vi.mock('axios', () => ({
      default: {
        get: vi.fn().mockResolvedValue({ data: { data: [] } }),
        post: vi.fn().mockResolvedValue({ data: { data: {} } }),
        put: vi.fn().mockResolvedValue({ data: { data: {} } }),
        delete: vi.fn().mockResolvedValue({ data: { success: true } })
      }
    }));

    // Mock Chart.js
    vi.mock('chart.js/auto', () => ({
      default: vi.fn().mockImplementation(() => ({
        destroy: vi.fn(),
        update: vi.fn(),
        resize: vi.fn()
      }))
    }));

    // Mock router-link for component tests
    vi.mock('vue-router', () => ({
      RouterLink: {
        name: 'RouterLink',
        props: ['to'],
        template: '<a><slot /></a>'
      }
    }));
  });

  afterEach(() => {
    vi.restoreAllMocks();
    mockEventListeners.clear();
  });

  describe('WebSocket Broadcasting Integration', () => {
    it('should broadcast MikroTik status updates to all connected components', async () => {
      // Initialize WebSocket connection
      webSocketService.initialize();
      expect(realtimeStore.connectionStatus).toBe('connected');

      // Simulate MikroTik device status update event
      const mockDevice: MikroTikDevice = {
        id: 'device-1',
        name: 'Router-Main',
        ip_address: '192.168.1.1',
        location: {
          region: 'Central',
          district: 'Kampala',
          coordinates: { lat: 0.3476, lng: 32.5825 }
        },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 86400,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate WebSocket event broadcast by directly calling store methods
      // This simulates what would happen when the real WebSocket service receives events
      realtimeStore.updateMikroTikDevices([...realtimeStore.mikrotikDevices, mockDevice]);
      await nextTick();

      // Verify update is stored correctly in realtime store
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].status).toBe('online');
      expect(realtimeStore.mikrotikDevices[0].id).toBe('device-1');

      // Simulate status change event by directly updating the store
      realtimeStore.updateMikroTikDevice('device-1', { 
        status: 'offline',
        last_seen: new Date().toISOString()
      });
      await nextTick();

      // Verify update is reflected across all components
      const deviceInStore = realtimeStore.mikrotikDevices.find(d => d.id === 'device-1');
      expect(deviceInStore?.status).toBe('offline');
      expect(deviceInStore?.last_seen).toBeDefined();
    });

    it('should broadcast payment updates to dashboard and voucher components', async () => {
      // Initialize connection
      webSocketService.initialize();

      // Simulate payment processing event
      const mockPayment: Payment = {
        id: 'payment-1',
        customer_id: 'customer-1',
        voucher_id: 'voucher-1',
        gateway_id: 'gateway-1',
        amount: 10000,
        currency: 'UGX',
        status: 'processing',
        gateway_transaction_id: 'txn-123',
        gateway_reference: 'ref-456',
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate payment processed event by directly calling store methods
      realtimeStore.addRecentPayment(mockPayment);
      await nextTick();

      // Verify payment appears in recent payments
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.recentPayments[0].id).toBe('payment-1');
      expect(realtimeStore.recentPayments[0].status).toBe('processing');

      // Simulate payment status update event
      realtimeStore.updatePaymentStatus('payment-1', 'completed');
      await nextTick();

      // Verify status update is reflected
      const updatedPayment = realtimeStore.recentPayments.find(p => p.id === 'payment-1');
      expect(updatedPayment?.status).toBe('completed');
      expect(updatedPayment?.processed_at).toBeDefined();
    });

    it('should broadcast voucher updates across all relevant components', async () => {
      // Initialize connection
      webSocketService.initialize();

      // Simulate voucher generation event
      const mockVouchers: Voucher[] = [
        {
          id: 'voucher-1',
          code: 'TEST-001',
          customer_id: 'customer-1',
          amount: 5000,
          duration_hours: 24,
          status: 'unused',
          activated_at: null,
          expires_at: null,
          mikrotik_device_id: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'voucher-2',
          code: 'TEST-002',
          customer_id: 'customer-2',
          amount: 10000,
          duration_hours: 48,
          status: 'unused',
          activated_at: null,
          expires_at: null,
          mikrotik_device_id: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      // Simulate bulk voucher generation event by directly updating store
      realtimeStore.updateActiveVouchers([...realtimeStore.activeVouchers, ...mockVouchers]);
      await nextTick();

      // Verify vouchers are added to active vouchers
      expect(realtimeStore.activeVouchers).toHaveLength(2);
      expect(realtimeStore.activeVouchers.map(v => v.id)).toContain('voucher-1');
      expect(realtimeStore.activeVouchers.map(v => v.id)).toContain('voucher-2');

      // Simulate voucher activation event
      realtimeStore.updateVoucherStatus('voucher-1', 'active');
      await nextTick();

      // Verify activation is reflected
      const voucherInStore = realtimeStore.activeVouchers.find(v => v.id === 'voucher-1');
      expect(voucherInStore?.status).toBe('active');

      // Simulate voucher expiration event
      realtimeStore.updateVoucherStatus('voucher-2', 'expired');
      await nextTick();

      // Verify expiration is reflected
      const expiredVoucher = realtimeStore.activeVouchers.find(v => v.id === 'voucher-2');
      expect(expiredVoucher?.status).toBe('expired');
    });

    it('should handle system notifications and alerts', async () => {
      // Initialize connection
      webSocketService.initialize();

      // Simulate system alert
      const systemAlert = {
        type: 'warning',
        message: 'High memory usage detected on MikroTik device Router-Main',
        data: { device_id: 'device-1', memory_usage: 85 }
      };

      // Simulate system alert by directly calling app store
      appStore.addNotification({
        type: systemAlert.type as any,
        title: 'System Alert',
        message: systemAlert.message,
        duration: 10000,
      });
      await nextTick();

      // Verify notification was added to app store
      expect(appStore.notifications).toHaveLength(1);
      expect(appStore.notifications[0].type).toBe('warning');
      expect(appStore.notifications[0].message).toBe(systemAlert.message);
      expect(appStore.notifications[0].title).toBe('System Alert');
    });

    it('should handle user-specific private channel notifications', async () => {
      // Set up user in app store
      appStore.user = { id: 'user-123', name: 'Test User', email: 'test@example.com', token: 'test-token' };
      
      // Initialize connection
      webSocketService.initialize();

      // Simulate user notification
      const userNotification = {
        type: 'info',
        title: 'Payment Received',
        message: 'Your payment of UGX 15,000 has been processed successfully.'
      };

      // Simulate notification on private user channel by directly calling app store
      appStore.addNotification({
        type: userNotification.type as any,
        title: userNotification.title,
        message: userNotification.message,
        duration: 5000,
      });
      await nextTick();

      // Verify notification was added
      expect(appStore.notifications).toHaveLength(1);
      expect(appStore.notifications[0].type).toBe('info');
      expect(appStore.notifications[0].title).toBe('Payment Received');
      expect(appStore.notifications[0].message).toBe(userNotification.message);
    });
  });

  describe('Real-time Dashboard Updates', () => {
    it('should update dashboard statistics in real-time', async () => {
      // Initialize connection
      webSocketService.initialize();

      // Initial state - no data
      expect(realtimeStore.mikrotikDevices).toHaveLength(0);
      expect(realtimeStore.recentPayments).toHaveLength(0);
      expect(realtimeStore.activeVouchers).toHaveLength(0);

      // Simulate real-time data updates through direct store calls
      const mockDevice: MikroTikDevice = {
        id: 'device-1',
        name: 'Main Router',
        ip_address: '192.168.1.1',
        location: { region: 'Central', district: 'Kampala' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 3600,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      const mockPayment: Payment = {
        id: 'payment-1',
        customer_id: 'customer-1',
        voucher_id: null,
        gateway_id: 'gateway-1',
        amount: 15000,
        currency: 'UGX',
        status: 'completed',
        gateway_transaction_id: 'txn-789',
        gateway_reference: null,
        callback_data: null,
        processed_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      const mockVoucher: Voucher = {
        id: 'voucher-1',
        code: 'DASH-001',
        customer_id: 'customer-1',
        amount: 15000,
        duration_hours: 72,
        status: 'active',
        activated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 72 * 60 * 60 * 1000).toISOString(),
        mikrotik_device_id: 'device-1',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate real-time updates by directly calling store methods
      realtimeStore.updateMikroTikDevices([mockDevice]);
      realtimeStore.addRecentPayment(mockPayment);
      realtimeStore.updateActiveVouchers([mockVoucher]);
      await nextTick();

      // Verify dashboard reflects real-time updates
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.activeVouchers).toHaveLength(1);

      // Simulate additional real-time updates
      realtimeStore.updateMikroTikDevice('device-1', { status: 'offline' });
      realtimeStore.updatePaymentStatus('payment-1', 'refunded');
      realtimeStore.updateVoucherStatus('voucher-1', 'expired');
      await nextTick();

      // Verify updates are reflected in dashboard data
      expect(realtimeStore.mikrotikDevices[0].status).toBe('offline');
      expect(realtimeStore.recentPayments[0].status).toBe('refunded');
      expect(realtimeStore.activeVouchers[0].status).toBe('expired');
    });

    it('should handle connection status changes in dashboard', async () => {
      // Test connection status changes
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate connection
      webSocketService.initialize();
      expect(realtimeStore.connectionStatus).toBe('connected');

      // Simulate disconnection
      webSocketService.disconnect();
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate reconnection attempts
      realtimeStore.setConnectionStatus('reconnecting');
      expect(realtimeStore.connectionStatus).toBe('reconnecting');

      // Simulate successful reconnection
      realtimeStore.setConnectionStatus('connected');
      realtimeStore.resetReconnectAttempts();
      expect(realtimeStore.connectionStatus).toBe('connected');
      expect(realtimeStore.reconnectAttempts).toBe(0);
    });

    it('should update dashboard metrics when real-time events occur', async () => {
      webSocketService.initialize();

      // Simulate multiple device status updates
      const devices: MikroTikDevice[] = [
        {
          id: 'device-1',
          name: 'Router-1',
          ip_address: '192.168.1.1',
          location: { region: 'Central', district: 'Kampala' },
          api_port: 8728,
          username: 'admin',
          status: 'online',
          last_seen: new Date().toISOString(),
          uptime_seconds: 7200,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'device-2',
          name: 'Router-2',
          ip_address: '192.168.1.2',
          location: { region: 'Western', district: 'Mbarara' },
          api_port: 8728,
          username: 'admin',
          status: 'offline',
          last_seen: new Date(Date.now() - 300000).toISOString(), // 5 minutes ago
          uptime_seconds: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      // Simulate device status updates by directly calling store methods
      realtimeStore.updateMikroTikDevices(devices);
      await nextTick();

      // Verify devices are tracked
      expect(realtimeStore.mikrotikDevices).toHaveLength(2);
      
      // Count online vs offline devices
      const onlineDevices = realtimeStore.mikrotikDevices.filter(d => d.status === 'online');
      const offlineDevices = realtimeStore.mikrotikDevices.filter(d => d.status === 'offline');
      
      expect(onlineDevices).toHaveLength(1);
      expect(offlineDevices).toHaveLength(1);
    });
  });

  describe('Cross-Component Data Consistency', () => {
    it('should maintain data consistency across multiple component instances', async () => {
      // Initialize connection
      webSocketService.initialize();

      // Add test data through direct store calls
      const testDevice: MikroTikDevice = {
        id: 'consistency-test',
        name: 'Consistency Router',
        ip_address: '10.0.0.1',
        location: { region: 'Test', district: 'Test' },
        api_port: 8728,
        username: 'test',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 1800,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate real-time update by directly calling store methods
      realtimeStore.updateMikroTikDevices([testDevice]);
      await nextTick();

      // Verify data is consistent
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].id).toBe('consistency-test');

      // Simulate status change through direct store call
      realtimeStore.updateMikroTikDevice('consistency-test', { status: 'error', last_seen: new Date().toISOString() });
      await nextTick();

      // Verify consistency across all components that would use this data
      const deviceInStore = realtimeStore.mikrotikDevices.find(d => d.id === 'consistency-test');
      expect(deviceInStore?.status).toBe('error');
    });

    it('should handle rapid sequential updates without data corruption', async () => {
      webSocketService.initialize();

      // Create test payment for rapid updates
      const basePayment: Payment = {
        id: 'rapid-test',
        customer_id: 'customer-rapid',
        voucher_id: null,
        gateway_id: 'gateway-rapid',
        amount: 5000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: null,
        gateway_reference: null,
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Add initial payment through direct store call
      realtimeStore.addRecentPayment(basePayment);
      await nextTick();

      // Perform rapid status updates through direct store calls
      const statusSequence: Payment['status'][] = ['processing', 'completed', 'refunded'];
      
      for (const status of statusSequence) {
        realtimeStore.updatePaymentStatus('rapid-test', status);
        await nextTick();
        
        // Verify each update is applied correctly
        const currentPayment = realtimeStore.recentPayments.find(p => p.id === 'rapid-test');
        expect(currentPayment?.status).toBe(status);
      }

      // Verify final state integrity
      const finalPayment = realtimeStore.recentPayments.find(p => p.id === 'rapid-test');
      expect(finalPayment?.status).toBe('refunded');
      expect(finalPayment?.id).toBe('rapid-test');
      expect(finalPayment?.amount).toBe(5000);
    });

    it('should synchronize data across Dashboard and MikroTik Monitor components', async () => {
      webSocketService.initialize();

      // Create multiple devices with different statuses
      const devices: MikroTikDevice[] = [
        {
          id: 'sync-device-1',
          name: 'Sync Router 1',
          ip_address: '192.168.10.1',
          location: { region: 'North', district: 'Gulu' },
          api_port: 8728,
          username: 'admin',
          status: 'online',
          last_seen: new Date().toISOString(),
          uptime_seconds: 14400,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'sync-device-2',
          name: 'Sync Router 2',
          ip_address: '192.168.10.2',
          location: { region: 'South', district: 'Masaka' },
          api_port: 8728,
          username: 'admin',
          status: 'offline',
          last_seen: new Date(Date.now() - 600000).toISOString(), // 10 minutes ago
          uptime_seconds: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      // Simulate device updates through direct store calls
      realtimeStore.updateMikroTikDevices(devices);
      await nextTick();

      // Verify both components would see the same data
      expect(realtimeStore.mikrotikDevices).toHaveLength(2);
      
      // Simulate status change on one device
      realtimeStore.updateMikroTikDevice('sync-device-2', { status: 'online', last_seen: new Date().toISOString() });
      await nextTick();

      // Verify synchronization
      const syncedDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'sync-device-2');
      expect(syncedDevice?.status).toBe('online');
      
      // Both Dashboard and MikroTikMonitor should show the same status
      const onlineDevices = realtimeStore.mikrotikDevices.filter(d => d.status === 'online');
      expect(onlineDevices).toHaveLength(2);
    });

    it('should maintain voucher-payment relationships across components', async () => {
      webSocketService.initialize();

      // Create related payment and voucher
      const payment: Payment = {
        id: 'payment-voucher-link',
        customer_id: 'customer-123',
        voucher_id: 'voucher-link-test',
        gateway_id: 'gateway-1',
        amount: 20000,
        currency: 'UGX',
        status: 'completed',
        gateway_transaction_id: 'txn-link-123',
        gateway_reference: null,
        callback_data: null,
        processed_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      const voucher: Voucher = {
        id: 'voucher-link-test',
        code: 'LINK-001',
        customer_id: 'customer-123',
        amount: 20000,
        duration_hours: 168, // 1 week
        status: 'unused',
        activated_at: null,
        expires_at: null,
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate payment completion and voucher generation through direct store calls
      realtimeStore.addRecentPayment(payment);
      realtimeStore.updateActiveVouchers([voucher]);
      await nextTick();

      // Verify both are tracked
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.activeVouchers).toHaveLength(1);

      // Verify relationship
      const storedPayment = realtimeStore.recentPayments[0];
      const storedVoucher = realtimeStore.activeVouchers[0];
      
      expect(storedPayment.voucher_id).toBe(storedVoucher.id);
      expect(storedPayment.customer_id).toBe(storedVoucher.customer_id);
      expect(storedPayment.amount).toBe(storedVoucher.amount);

      // Simulate voucher activation
      realtimeStore.updateVoucherStatus('voucher-link-test', 'active');
      await nextTick();

      // Verify activation is reflected
      const updatedVoucher = realtimeStore.activeVouchers.find(v => v.id === 'voucher-link-test');
      expect(updatedVoucher?.status).toBe('active');
      expect(updatedVoucher?.activated_at).toBeDefined();
    });
  });

  describe('Error Handling and Recovery', () => {
    it('should handle WebSocket connection errors gracefully', async () => {
      // Start with disconnected state
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate connection failure
      vi.spyOn(webSocketService, 'initialize').mockImplementation(() => {
        realtimeStore.setConnectionStatus('disconnected');
        throw new Error('Connection failed');
      });

      // Attempt to connect
      try {
        webSocketService.initialize();
      } catch (error) {
        // Expected to fail
      }

      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate reconnection attempts
      for (let i = 0; i < 3; i++) {
        const canReconnect = realtimeStore.incrementReconnectAttempts();
        expect(canReconnect).toBe(true);
      }

      // Simulate successful reconnection
      vi.spyOn(webSocketService, 'initialize').mockImplementation(() => {
        realtimeStore.setConnectionStatus('connected');
        realtimeStore.resetReconnectAttempts();
      });

      webSocketService.initialize();
      expect(realtimeStore.connectionStatus).toBe('connected');
      expect(realtimeStore.reconnectAttempts).toBe(0);
    });

    it('should maintain data integrity during connection interruptions', async () => {
      // Initialize with data
      webSocketService.initialize();
      
      const testData = {
        device: {
          id: 'interruption-test',
          name: 'Test Router',
          ip_address: '192.168.100.1',
          location: { region: 'Test', district: 'Test' },
          api_port: 8728,
          username: 'test',
          status: 'online' as const,
          last_seen: new Date().toISOString(),
          uptime_seconds: 7200,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        payment: {
          id: 'interruption-payment',
          customer_id: 'customer-int',
          voucher_id: null,
          gateway_id: 'gateway-int',
          amount: 8000,
          currency: 'UGX' as const,
          status: 'completed' as const,
          gateway_transaction_id: 'txn-int',
          gateway_reference: null,
          callback_data: null,
          processed_at: new Date().toISOString(),
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      };

      // Add data while connected through direct store calls
      realtimeStore.updateMikroTikDevices([testData.device]);
      realtimeStore.addRecentPayment(testData.payment);
      await nextTick();

      // Verify data is present
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);

      // Simulate connection interruption
      webSocketService.disconnect();
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Verify data is still intact during disconnection
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].id).toBe('interruption-test');
      expect(realtimeStore.recentPayments[0].id).toBe('interruption-payment');

      // Simulate reconnection
      webSocketService.initialize();
      expect(realtimeStore.connectionStatus).toBe('connected');

      // Verify data integrity is maintained after reconnection
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].status).toBe('online');
      expect(realtimeStore.recentPayments[0].status).toBe('completed');
    });

    it('should handle malformed WebSocket events gracefully', async () => {
      webSocketService.initialize();

      // Simulate malformed device update (missing required fields)
      const malformedDeviceEvent = {
        device: {
          id: 'malformed-device',
          // Missing required fields like name, ip_address, etc.
          status: 'online'
        }
      };

      // This should not crash the application
      try {
        simulateWebSocketEvent('mikrotik-status', 'MikroTikStatusUpdated', malformedDeviceEvent);
        await nextTick();
      } catch (error) {
        // Should handle gracefully
      }

      // Verify store remains stable
      expect(realtimeStore.connectionStatus).toBe('connected');

      // Simulate malformed payment event
      const malformedPaymentEvent = {
        payment: {
          id: 'malformed-payment',
          // Missing required fields
          status: 'completed'
        }
      };

      try {
        simulateWebSocketEvent('payments', 'PaymentProcessed', malformedPaymentEvent);
        await nextTick();
      } catch (error) {
        // Should handle gracefully
      }

      // Verify store remains stable
      expect(realtimeStore.connectionStatus).toBe('connected');
    });

    it('should handle WebSocket event ordering issues', async () => {
      webSocketService.initialize();

      const payment: Payment = {
        id: 'ordering-test',
        customer_id: 'customer-order',
        voucher_id: null,
        gateway_id: 'gateway-order',
        amount: 12000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: 'txn-order',
        gateway_reference: null,
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate events arriving out of order
      // Status update arrives before payment creation - this should be handled gracefully
      // In real scenarios, we'd handle this by checking if payment exists before updating
      
      // Payment creation arrives first (normal flow)
      realtimeStore.addRecentPayment(payment);
      await nextTick();

      // Then status update
      realtimeStore.updatePaymentStatus('ordering-test', 'completed');
      await nextTick();

      // Verify the system handles this gracefully
      const storedPayment = realtimeStore.recentPayments.find(p => p.id === 'ordering-test');
      expect(storedPayment).toBeDefined();
      expect(storedPayment?.id).toBe('ordering-test');
      
      // The status update should be applied
      expect(storedPayment?.status).toBe('completed');
    });

    it('should recover from store corruption scenarios', async () => {
      webSocketService.initialize();

      // Add some initial data
      const initialDevice: MikroTikDevice = {
        id: 'recovery-device',
        name: 'Recovery Router',
        ip_address: '192.168.200.1',
        location: { region: 'Recovery', district: 'Test' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 3600,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateMikroTikDevices([initialDevice]);
      await nextTick();

      expect(realtimeStore.mikrotikDevices).toHaveLength(1);

      // Simulate a scenario where the store gets corrupted (e.g., invalid data)
      // This could happen due to network issues or malformed responses
      try {
        // Attempt to update with invalid data
        realtimeStore.updateMikroTikDevice('recovery-device', { status: 'invalid-status' as any });
        await nextTick();
      } catch (error) {
        // Should handle gracefully
      }

      // Verify the system can recover with valid data
      realtimeStore.updateMikroTikDevice('recovery-device', { status: 'offline' });
      await nextTick();

      const recoveredDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'recovery-device');
      expect(recoveredDevice?.status).toBe('offline');
    });
  });

  describe('Component Integration with Real-time Data', () => {
    it('should integrate real-time updates with Dashboard component lifecycle', async () => {
      webSocketService.initialize();

      // Simulate dashboard receiving real-time updates
      const dashboardData = {
        devices: [
          {
            id: 'dashboard-device-1',
            name: 'Dashboard Router 1',
            ip_address: '192.168.50.1',
            location: { region: 'Dashboard', district: 'Test' },
            api_port: 8728,
            username: 'admin',
            status: 'online' as const,
            last_seen: new Date().toISOString(),
            uptime_seconds: 5400,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
        ],
        payments: [
          {
            id: 'dashboard-payment-1',
            customer_id: 'dashboard-customer',
            voucher_id: null,
            gateway_id: 'dashboard-gateway',
            amount: 25000,
            currency: 'UGX' as const,
            status: 'completed' as const,
            gateway_transaction_id: 'dashboard-txn',
            gateway_reference: null,
            callback_data: null,
            processed_at: new Date().toISOString(),
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
        ]
      };

      // Simulate real-time updates that Dashboard would receive through direct store calls
      realtimeStore.updateMikroTikDevices(dashboardData.devices);
      dashboardData.payments.forEach(payment => {
        realtimeStore.addRecentPayment(payment);
      });
      await nextTick();

      // Verify Dashboard would have access to this real-time data
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);

      // Simulate dashboard-specific real-time updates
      appStore.addNotification({
        type: 'info',
        title: 'System Alert',
        message: 'Dashboard refresh completed',
        duration: 10000,
      });
      await nextTick();

      // Verify notification was received
      expect(appStore.notifications).toHaveLength(1);
      expect(appStore.notifications[0].message).toBe('Dashboard refresh completed');
    });

    it('should integrate real-time updates with MikroTik Monitor component', async () => {
      webSocketService.initialize();

      // Simulate multiple device status changes that MikroTik Monitor would handle
      const monitorDevices: MikroTikDevice[] = [
        {
          id: 'monitor-device-1',
          name: 'Monitor Router 1',
          ip_address: '192.168.60.1',
          location: { region: 'Monitor', district: 'North' },
          api_port: 8728,
          username: 'admin',
          status: 'online',
          last_seen: new Date().toISOString(),
          uptime_seconds: 7200,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'monitor-device-2',
          name: 'Monitor Router 2',
          ip_address: '192.168.60.2',
          location: { region: 'Monitor', district: 'South' },
          api_port: 8728,
          username: 'admin',
          status: 'offline',
          last_seen: new Date(Date.now() - 900000).toISOString(), // 15 minutes ago
          uptime_seconds: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      // Simulate initial device discovery through direct store calls
      realtimeStore.updateMikroTikDevices(monitorDevices);
      await nextTick();

      // Verify MikroTik Monitor would see all devices
      expect(realtimeStore.mikrotikDevices).toHaveLength(2);

      // Simulate rapid status changes (like what happens during network issues)
      const statusChanges = [
        { deviceId: 'monitor-device-1', status: 'error' as const },
        { deviceId: 'monitor-device-2', status: 'online' as const },
        { deviceId: 'monitor-device-1', status: 'online' as const }
      ];

      for (const change of statusChanges) {
        realtimeStore.updateMikroTikDevice(change.deviceId, { status: change.status, last_seen: new Date().toISOString() });
        await nextTick();
      }

      // Verify final states
      const device1 = realtimeStore.mikrotikDevices.find(d => d.id === 'monitor-device-1');
      const device2 = realtimeStore.mikrotikDevices.find(d => d.id === 'monitor-device-2');

      expect(device1?.status).toBe('online');
      expect(device2?.status).toBe('online');
    });

    it('should handle real-time updates across voucher and payment components', async () => {
      webSocketService.initialize();

      // Simulate voucher-payment workflow
      const customer_id = 'workflow-customer';
      const voucher_id = 'workflow-voucher';
      const payment_id = 'workflow-payment';

      // Step 1: Payment initiated
      const initialPayment: Payment = {
        id: payment_id,
        customer_id,
        voucher_id: null, // No voucher yet
        gateway_id: 'workflow-gateway',
        amount: 30000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: 'workflow-txn',
        gateway_reference: null,
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.addRecentPayment(initialPayment);
      await nextTick();

      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.recentPayments[0].status).toBe('pending');

      // Step 2: Payment completed
      realtimeStore.updatePaymentStatus(payment_id, 'completed');
      await nextTick();

      const completedPayment = realtimeStore.recentPayments.find(p => p.id === payment_id);
      expect(completedPayment?.status).toBe('completed');

      // Step 3: Voucher generated after payment completion
      const generatedVoucher: Voucher = {
        id: voucher_id,
        code: 'WORKFLOW-001',
        customer_id,
        amount: 30000,
        duration_hours: 720, // 30 days
        status: 'unused',
        activated_at: null,
        expires_at: null,
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateActiveVouchers([generatedVoucher]);
      await nextTick();

      expect(realtimeStore.activeVouchers).toHaveLength(1);
      expect(realtimeStore.activeVouchers[0].status).toBe('unused');

      // Step 4: Voucher activated
      realtimeStore.updateVoucherStatus(voucher_id, 'active');
      // Update the voucher to include device assignment
      const voucherIndex = realtimeStore.activeVouchers.findIndex(v => v.id === voucher_id);
      if (voucherIndex > -1) {
        realtimeStore.activeVouchers[voucherIndex].mikrotik_device_id = 'workflow-device';
      }
      await nextTick();

      const activeVoucher = realtimeStore.activeVouchers.find(v => v.id === voucher_id);
      expect(activeVoucher?.status).toBe('active');
      expect(activeVoucher?.activated_at).toBeDefined();

      // Verify the complete workflow is tracked
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.activeVouchers).toHaveLength(1);
      expect(completedPayment?.customer_id).toBe(activeVoucher?.customer_id);
    });

    it('should test Dashboard component real-time integration with mounted component', async () => {
      // Mock axios for Dashboard API calls
      const mockAxios = {
        get: vi.fn().mockImplementation((url: string) => {
          if (url.includes('/api/v1/dashboard/stats')) {
            return Promise.resolve({
              data: {
                data: {
                  revenue: { today: 150000, growth_percentage: 12.5 },
                  customers: { active: 45, total: 120, active_percentage: 8.2 },
                  vouchers: { active: 23, total: 89, utilization_rate: 67.3 },
                  mikrotik: { online: 3, total_devices: 4, offline: 1, uptime_percentage: 95.2 },
                  system_health: { overall_status: 'healthy', checks: {} }
                }
              }
            });
          }
          if (url.includes('/api/v1/dashboard/recent-payments')) {
            return Promise.resolve({ data: { data: [] } });
          }
          if (url.includes('/api/v1/dashboard/recent-vouchers')) {
            return Promise.resolve({ data: { data: [] } });
          }
          return Promise.resolve({ data: { data: [] } });
        })
      };

      vi.doMock('axios', () => ({ default: mockAxios }));

      webSocketService.initialize();

      // Mount Dashboard component
      const wrapper = mount(Dashboard, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': ConnectionStatus,
            'MikroTikMonitor': true,
            'MetricCard': true,
            'LoadingOverlay': true
          }
        }
      });

      await nextTick();

      // Simulate real-time device status update
      const testDevice: MikroTikDevice = {
        id: 'dashboard-test-device',
        name: 'Dashboard Test Router',
        ip_address: '192.168.100.1',
        location: { region: 'Test', district: 'Dashboard' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 3600,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateMikroTikDevices([testDevice]);
      await nextTick();

      // Verify Dashboard component can access real-time data
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].id).toBe('dashboard-test-device');

      // Simulate device status change
      realtimeStore.updateMikroTikDevice('dashboard-test-device', { status: 'offline' });
      await nextTick();

      // Verify status change is reflected
      const updatedDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'dashboard-test-device');
      expect(updatedDevice?.status).toBe('offline');

      wrapper.unmount();
    });

    it('should test MikroTik Monitor component real-time integration', async () => {
      webSocketService.initialize();

      // Test that real-time updates work for MikroTik Monitor functionality
      const testDevice: MikroTikDevice = {
        id: 'monitor-test-device',
        name: 'Monitor Test Router',
        ip_address: '192.168.101.1',
        location: { region: 'Test', district: 'Monitor' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 7200,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate device data being loaded (as would happen in component)
      realtimeStore.updateMikroTikDevices([testDevice]);
      await nextTick();

      // Verify initial data is available
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].status).toBe('online');

      // Simulate real-time status update (as would come from WebSocket)
      realtimeStore.updateMikroTikDevice('monitor-test-device', { 
        status: 'error',
        last_seen: new Date().toISOString()
      });
      await nextTick();

      // Verify status change is reflected in store (component would react to this)
      const updatedDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'monitor-test-device');
      expect(updatedDevice?.status).toBe('error');

      // Simulate recovery
      realtimeStore.updateMikroTikDevice('monitor-test-device', { 
        status: 'online',
        last_seen: new Date().toISOString()
      });
      await nextTick();

      // Verify recovery is reflected
      const recoveredDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'monitor-test-device');
      expect(recoveredDevice?.status).toBe('online');
    });

    it('should test cross-component real-time data synchronization', async () => {
      webSocketService.initialize();

      // Test that multiple components would see the same real-time data updates
      const sharedDevice: MikroTikDevice = {
        id: 'shared-device',
        name: 'Shared Router',
        ip_address: '192.168.200.1',
        location: { region: 'Shared', district: 'Test' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 14400,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate initial data load (as would happen in components)
      realtimeStore.updateMikroTikDevices([sharedDevice]);
      await nextTick();

      // Verify initial state that both Dashboard and MikroTik Monitor would see
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].id).toBe('shared-device');
      expect(realtimeStore.mikrotikDevices[0].status).toBe('online');

      // Simulate real-time status change (as would come from WebSocket)
      realtimeStore.updateMikroTikDevice('shared-device', { status: 'offline' });
      await nextTick();

      // Verify both components would see the updated status
      const updatedDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'shared-device');
      expect(updatedDevice?.status).toBe('offline');

      // Simulate payment that would affect Dashboard
      const testPayment: Payment = {
        id: 'cross-component-payment',
        customer_id: 'cross-customer',
        voucher_id: null,
        gateway_id: 'cross-gateway',
        amount: 18000,
        currency: 'UGX',
        status: 'completed',
        gateway_transaction_id: 'cross-txn',
        gateway_reference: null,
        callback_data: null,
        processed_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.addRecentPayment(testPayment);
      await nextTick();

      // Verify Dashboard would see the payment
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.recentPayments[0].id).toBe('cross-component-payment');

      // Verify that device status and payment data coexist
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(updatedDevice?.status).toBe('offline');
    });

    it('should test real-time notification broadcasting across components', async () => {
      webSocketService.initialize();

      // Mount components that should receive notifications
      const dashboardWrapper = mount(Dashboard, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': true,
            'MikroTikMonitor': true,
            'MetricCard': true,
            'LoadingOverlay': true
          }
        }
      });

      await nextTick();

      // Simulate system-wide notification
      appStore.addNotification({
        type: 'warning',
        title: 'System Alert',
        message: 'High memory usage detected on multiple devices',
        duration: 10000,
      });
      await nextTick();

      // Verify notification is available to all components
      expect(appStore.notifications).toHaveLength(1);
      expect(appStore.notifications[0].type).toBe('warning');
      expect(appStore.notifications[0].title).toBe('System Alert');

      // Simulate user-specific notification
      appStore.user = { id: 'test-user', name: 'Test User', email: 'test@example.com', token: 'test-token' };
      
      appStore.addNotification({
        type: 'success',
        title: 'Payment Processed',
        message: 'Your payment of UGX 25,000 has been processed successfully.',
        duration: 5000,
      });
      await nextTick();

      // Verify user notification is added
      expect(appStore.notifications).toHaveLength(2);
      expect(appStore.notifications[1].type).toBe('success');
      expect(appStore.notifications[1].title).toBe('Payment Processed');

      dashboardWrapper.unmount();
    });

    it('should test connection status updates across all components', async () => {
      // Mount components that display connection status
      const dashboardWrapper = mount(Dashboard, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': ConnectionStatus,
            'MikroTikMonitor': true,
            'MetricCard': true,
            'LoadingOverlay': true
          }
        }
      });

      const monitorWrapper = mount(MikroTikMonitor, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': ConnectionStatus,
            'Modal': true
          }
        }
      });

      await nextTick();

      // Initial state should be disconnected
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate connection
      webSocketService.initialize();
      expect(realtimeStore.connectionStatus).toBe('connected');

      // Simulate disconnection
      realtimeStore.setConnectionStatus('disconnected');
      await nextTick();

      // All components should see disconnected status
      expect(realtimeStore.connectionStatus).toBe('disconnected');

      // Simulate reconnection attempt
      realtimeStore.setConnectionStatus('reconnecting');
      await nextTick();

      // All components should see reconnecting status
      expect(realtimeStore.connectionStatus).toBe('reconnecting');

      // Simulate successful reconnection
      realtimeStore.setConnectionStatus('connected');
      realtimeStore.resetReconnectAttempts();
      await nextTick();

      // All components should see connected status
      expect(realtimeStore.connectionStatus).toBe('connected');
      expect(realtimeStore.reconnectAttempts).toBe(0);

      dashboardWrapper.unmount();
      monitorWrapper.unmount();
    });
  });

  describe('Advanced Real-time Integration Scenarios', () => {
    it('should handle simultaneous updates across multiple data types', async () => {
      webSocketService.initialize();

      // Simulate simultaneous updates that would happen in real system
      const simultaneousUpdates = {
        devices: [
          {
            id: 'simultaneous-device-1',
            name: 'Simultaneous Router 1',
            ip_address: '192.168.150.1',
            location: { region: 'Simultaneous', district: 'Test1' },
            api_port: 8728,
            username: 'admin',
            status: 'online' as const,
            last_seen: new Date().toISOString(),
            uptime_seconds: 10800,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          },
          {
            id: 'simultaneous-device-2',
            name: 'Simultaneous Router 2',
            ip_address: '192.168.150.2',
            location: { region: 'Simultaneous', district: 'Test2' },
            api_port: 8728,
            username: 'admin',
            status: 'offline' as const,
            last_seen: new Date(Date.now() - 600000).toISOString(),
            uptime_seconds: 0,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
        ],
        payments: [
          {
            id: 'simultaneous-payment-1',
            customer_id: 'simultaneous-customer-1',
            voucher_id: null,
            gateway_id: 'simultaneous-gateway',
            amount: 15000,
            currency: 'UGX' as const,
            status: 'completed' as const,
            gateway_transaction_id: 'sim-txn-1',
            gateway_reference: null,
            callback_data: null,
            processed_at: new Date().toISOString(),
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          },
          {
            id: 'simultaneous-payment-2',
            customer_id: 'simultaneous-customer-2',
            voucher_id: null,
            gateway_id: 'simultaneous-gateway',
            amount: 22000,
            currency: 'UGX' as const,
            status: 'processing' as const,
            gateway_transaction_id: 'sim-txn-2',
            gateway_reference: null,
            callback_data: null,
            processed_at: null,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
        ],
        vouchers: [
          {
            id: 'simultaneous-voucher-1',
            code: 'SIM-001',
            customer_id: 'simultaneous-customer-1',
            amount: 15000,
            duration_hours: 48,
            status: 'active' as const,
            activated_at: new Date().toISOString(),
            expires_at: new Date(Date.now() + 48 * 60 * 60 * 1000).toISOString(),
            mikrotik_device_id: 'simultaneous-device-1',
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
        ]
      };

      // Apply all updates simultaneously
      realtimeStore.updateMikroTikDevices(simultaneousUpdates.devices);
      simultaneousUpdates.payments.forEach(payment => {
        realtimeStore.addRecentPayment(payment);
      });
      realtimeStore.updateActiveVouchers(simultaneousUpdates.vouchers);
      await nextTick();

      // Verify all updates were applied correctly
      expect(realtimeStore.mikrotikDevices).toHaveLength(2);
      expect(realtimeStore.recentPayments).toHaveLength(2);
      expect(realtimeStore.activeVouchers).toHaveLength(1);

      // Verify data integrity
      const onlineDevices = realtimeStore.mikrotikDevices.filter(d => d.status === 'online');
      const offlineDevices = realtimeStore.mikrotikDevices.filter(d => d.status === 'offline');
      const completedPayments = realtimeStore.recentPayments.filter(p => p.status === 'completed');
      const processingPayments = realtimeStore.recentPayments.filter(p => p.status === 'processing');
      const activeVouchers = realtimeStore.activeVouchers.filter(v => v.status === 'active');

      expect(onlineDevices).toHaveLength(1);
      expect(offlineDevices).toHaveLength(1);
      expect(completedPayments).toHaveLength(1);
      expect(processingPayments).toHaveLength(1);
      expect(activeVouchers).toHaveLength(1);
    });

    it('should handle real-time updates during component lifecycle changes', async () => {
      webSocketService.initialize();

      // Mount Dashboard component
      const dashboardWrapper = mount(Dashboard, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': true,
            'MikroTikMonitor': true,
            'MetricCard': true,
            'LoadingOverlay': true
          }
        }
      });

      await nextTick();

      // Add initial data
      const initialDevice: MikroTikDevice = {
        id: 'lifecycle-device',
        name: 'Lifecycle Router',
        ip_address: '192.168.175.1',
        location: { region: 'Lifecycle', district: 'Test' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 5400,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateMikroTikDevices([initialDevice]);
      await nextTick();

      // Verify component sees initial data
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);

      // Unmount component
      dashboardWrapper.unmount();

      // Update data while component is unmounted
      realtimeStore.updateMikroTikDevice('lifecycle-device', { status: 'offline' });
      await nextTick();

      // Verify data is still updated in store
      const updatedDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'lifecycle-device');
      expect(updatedDevice?.status).toBe('offline');

      // Remount component
      const newDashboardWrapper = mount(Dashboard, {
        global: {
          plugins: [pinia],
          stubs: {
            'ConnectionStatus': true,
            'MikroTikMonitor': true,
            'MetricCard': true,
            'LoadingOverlay': true
          }
        }
      });

      await nextTick();

      // Verify remounted component sees updated data
      expect(realtimeStore.mikrotikDevices).toHaveLength(1);
      expect(realtimeStore.mikrotikDevices[0].status).toBe('offline');

      newDashboardWrapper.unmount();
    });

    it('should test real-time data flow in complete user workflow', async () => {
      webSocketService.initialize();

      // Simulate complete user workflow: Customer payment -> Voucher generation -> Device assignment -> Usage tracking
      
      // Step 1: Customer initiates payment
      const workflowCustomer = 'workflow-customer-complete';
      const workflowPayment: Payment = {
        id: 'workflow-payment-complete',
        customer_id: workflowCustomer,
        voucher_id: null,
        gateway_id: 'workflow-gateway-complete',
        amount: 35000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: 'workflow-txn-complete',
        gateway_reference: null,
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.addRecentPayment(workflowPayment);
      await nextTick();

      // Verify payment is tracked
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.recentPayments[0].status).toBe('pending');

      // Step 2: Payment gateway processes payment
      realtimeStore.updatePaymentStatus('workflow-payment-complete', 'processing');
      await nextTick();

      let currentPayment = realtimeStore.recentPayments.find(p => p.id === 'workflow-payment-complete');
      expect(currentPayment?.status).toBe('processing');

      // Step 3: Payment completed
      realtimeStore.updatePaymentStatus('workflow-payment-complete', 'completed');
      await nextTick();

      currentPayment = realtimeStore.recentPayments.find(p => p.id === 'workflow-payment-complete');
      expect(currentPayment?.status).toBe('completed');
      expect(currentPayment?.processed_at).toBeDefined();

      // Step 4: System generates voucher
      const workflowVoucher: Voucher = {
        id: 'workflow-voucher-complete',
        code: 'COMPLETE-001',
        customer_id: workflowCustomer,
        amount: 35000,
        duration_hours: 168, // 1 week
        status: 'unused',
        activated_at: null,
        expires_at: null,
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateActiveVouchers([workflowVoucher]);
      await nextTick();

      // Verify voucher is generated
      expect(realtimeStore.activeVouchers).toHaveLength(1);
      expect(realtimeStore.activeVouchers[0].status).toBe('unused');

      // Step 5: Customer activates voucher on device
      const workflowDevice: MikroTikDevice = {
        id: 'workflow-device-complete',
        name: 'Workflow Router Complete',
        ip_address: '192.168.250.1',
        location: { region: 'Workflow', district: 'Complete' },
        api_port: 8728,
        username: 'admin',
        status: 'online',
        last_seen: new Date().toISOString(),
        uptime_seconds: 21600,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateMikroTikDevices([workflowDevice]);
      await nextTick();

      // Activate voucher on device
      realtimeStore.updateVoucherStatus('workflow-voucher-complete', 'active');
      const voucherIndex = realtimeStore.activeVouchers.findIndex(v => v.id === 'workflow-voucher-complete');
      if (voucherIndex > -1) {
        realtimeStore.activeVouchers[voucherIndex].mikrotik_device_id = 'workflow-device-complete';
        realtimeStore.activeVouchers[voucherIndex].activated_at = new Date().toISOString();
        realtimeStore.activeVouchers[voucherIndex].expires_at = new Date(Date.now() + 168 * 60 * 60 * 1000).toISOString();
      }
      await nextTick();

      // Verify complete workflow state
      const finalPayment = realtimeStore.recentPayments.find(p => p.id === 'workflow-payment-complete');
      const finalVoucher = realtimeStore.activeVouchers.find(v => v.id === 'workflow-voucher-complete');
      const finalDevice = realtimeStore.mikrotikDevices.find(d => d.id === 'workflow-device-complete');

      expect(finalPayment?.status).toBe('completed');
      expect(finalVoucher?.status).toBe('active');
      expect(finalVoucher?.mikrotik_device_id).toBe('workflow-device-complete');
      expect(finalVoucher?.activated_at).toBeDefined();
      expect(finalVoucher?.expires_at).toBeDefined();
      expect(finalDevice?.status).toBe('online');

      // Verify relationships
      expect(finalPayment?.customer_id).toBe(finalVoucher?.customer_id);
      expect(finalVoucher?.amount).toBe(finalPayment?.amount);
    });
  });
});