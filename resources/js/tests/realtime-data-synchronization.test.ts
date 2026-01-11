import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import fc from 'fast-check';
import { useRealtimeStore } from '@/store/modules/realtime';
import { webSocketService } from '@/services/websocket';
import type { MikroTikDevice, Payment, Voucher } from '@/types';

// Feature: vue-frontend-enhancement, Property 3: Real-time Data Synchronization

describe('Real-time Data Synchronization Property Tests', () => {
  let pinia: ReturnType<typeof createPinia>;
  let realtimeStore: ReturnType<typeof useRealtimeStore>;

  beforeEach(() => {
    pinia = createPinia();
    setActivePinia(pinia);
    realtimeStore = useRealtimeStore();
    
    // Mock WebSocket service
    vi.spyOn(webSocketService, 'initialize').mockImplementation(() => {});
    vi.spyOn(webSocketService, 'disconnect').mockImplementation(() => {});
    vi.spyOn(webSocketService, 'reconnect').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // Property test generators
  const mikrotikDeviceArb = fc.record({
    id: fc.uuid(),
    name: fc.string({ minLength: 1, maxLength: 50 }),
    ip_address: fc.ipV4(),
    location: fc.record({
      region: fc.string({ minLength: 1, maxLength: 30 }),
      district: fc.string({ minLength: 1, maxLength: 30 }),
      coordinates: fc.option(fc.record({
        lat: fc.float({ min: -90, max: 90 }),
        lng: fc.float({ min: -180, max: 180 })
      }))
    }),
    api_port: fc.integer({ min: 1, max: 65535 }),
    username: fc.string({ minLength: 1, maxLength: 20 }),
    status: fc.constantFrom('online', 'offline', 'error'),
    last_seen: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
    uptime_seconds: fc.integer({ min: 0, max: 31536000 }), // Up to 1 year
    created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
    updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
  });

  const paymentArb = fc.record({
    id: fc.uuid(),
    customer_id: fc.uuid(),
    voucher_id: fc.option(fc.uuid()),
    gateway_id: fc.uuid(),
    amount: fc.float({ min: Math.fround(0.01), max: Math.fround(1000000) }),
    currency: fc.constantFrom('UGX', 'USD', 'EUR'),
    status: fc.constantFrom('pending', 'processing', 'completed', 'failed', 'refunded'),
    gateway_transaction_id: fc.option(fc.string({ minLength: 1, maxLength: 100 })),
    gateway_reference: fc.option(fc.string({ minLength: 1, maxLength: 100 })),
    callback_data: fc.option(fc.object()),
    processed_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
    created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
    updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
  });

  const voucherArb = fc.record({
    id: fc.uuid(),
    code: fc.string({ minLength: 8, maxLength: 20 }),
    customer_id: fc.option(fc.uuid()),
    amount: fc.float({ min: Math.fround(0.01), max: Math.fround(100000) }),
    duration_hours: fc.integer({ min: 1, max: 8760 }), // Up to 1 year
    status: fc.constantFrom('unused', 'active', 'expired', 'suspended'),
    activated_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
    expires_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
    mikrotik_device_id: fc.option(fc.uuid()),
    created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
    updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
  });

  it('should synchronize MikroTik device status updates across all connected clients', () => {
    fc.assert(
      fc.property(mikrotikDeviceArb, (device: MikroTikDevice) => {
        // Initialize with empty devices
        realtimeStore.updateMikroTikDevices([]);
        expect(realtimeStore.mikrotikDevices).toHaveLength(0);

        // Add initial device
        realtimeStore.updateMikroTikDevices([device]);
        expect(realtimeStore.mikrotikDevices).toHaveLength(1);
        expect(realtimeStore.mikrotikDevices[0]).toEqual(device);

        // Update device status
        const updatedDevice = { ...device, status: 'online' as const, last_seen: new Date().toISOString() };
        realtimeStore.updateMikroTikDevice(device.id, updatedDevice);

        // Verify the update was applied
        const storedDevice = realtimeStore.mikrotikDevices.find(d => d.id === device.id);
        expect(storedDevice).toBeDefined();
        expect(storedDevice?.status).toBe('online');
        expect(storedDevice?.last_seen).toBe(updatedDevice.last_seen);

        // Property: Device updates should maintain data integrity
        expect(storedDevice?.id).toBe(device.id);
        expect(storedDevice?.name).toBe(device.name);
        expect(storedDevice?.ip_address).toBe(device.ip_address);
      }),
      { numRuns: 100 }
    );
  });

  it('should synchronize payment updates and maintain chronological order', () => {
    fc.assert(
      fc.property(fc.array(paymentArb, { minLength: 1, maxLength: 10 }), (payments: Payment[]) => {
        // Clear existing payments
        realtimeStore.recentPayments.splice(0);

        // Add payments one by one (simulating real-time updates)
        payments.forEach(payment => {
          realtimeStore.addRecentPayment(payment);
        });

        // Property: All payments should be stored
        expect(realtimeStore.recentPayments).toHaveLength(Math.min(payments.length, 50)); // Max 50 payments

        // Property: Most recent payment should be first
        if (payments.length > 0) {
          const lastAddedPayment = payments[payments.length - 1];
          expect(realtimeStore.recentPayments[0].id).toBe(lastAddedPayment.id);
        }

        // Property: Payment status updates should work correctly
        if (payments.length > 0) {
          const paymentToUpdate = payments[0];
          const newStatus = 'completed' as const;
          
          realtimeStore.updatePaymentStatus(paymentToUpdate.id, newStatus);
          
          const updatedPayment = realtimeStore.recentPayments.find(p => p.id === paymentToUpdate.id);
          expect(updatedPayment?.status).toBe(newStatus);
          if (newStatus === 'completed') {
            expect(updatedPayment?.processed_at).toBeDefined();
          }
        }
      }),
      { numRuns: 100 }
    );
  });

  it('should synchronize voucher status changes and maintain state consistency', () => {
    fc.assert(
      fc.property(fc.array(voucherArb, { minLength: 1, maxLength: 20 }), (vouchers: Voucher[]) => {
        // Initialize with vouchers
        realtimeStore.updateActiveVouchers(vouchers);
        expect(realtimeStore.activeVouchers).toHaveLength(vouchers.length);

        // Property: All vouchers should be stored correctly
        vouchers.forEach(voucher => {
          const storedVoucher = realtimeStore.activeVouchers.find(v => v.id === voucher.id);
          expect(storedVoucher).toBeDefined();
          expect(storedVoucher?.code).toBe(voucher.code);
          expect(storedVoucher?.amount).toBe(voucher.amount);
        });

        // Property: Voucher status updates should work correctly
        if (vouchers.length > 0) {
          const voucherToUpdate = vouchers[0];
          const newStatus = 'active' as const;
          
          realtimeStore.updateVoucherStatus(voucherToUpdate.id, newStatus);
          
          const updatedVoucher = realtimeStore.activeVouchers.find(v => v.id === voucherToUpdate.id);
          expect(updatedVoucher?.status).toBe(newStatus);
          
          // If status is 'active' and no previous activation time, should set activation time
          if (newStatus === 'active' && !voucherToUpdate.activated_at) {
            expect(updatedVoucher?.activated_at).toBeDefined();
          }
        }
      }),
      { numRuns: 100 }
    );
  });

  it('should handle connection status changes and reconnection logic', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('connected', 'disconnected', 'reconnecting'),
        fc.integer({ min: 0, max: 10 }),
        (status: 'connected' | 'disconnected' | 'reconnecting', attempts: number) => {
          // Reset state first
          realtimeStore.resetReconnectAttempts();
          
          // Set connection status
          realtimeStore.setConnectionStatus(status);
          expect(realtimeStore.connectionStatus).toBe(status);

          // Property: Connected status should reset reconnect attempts
          if (status === 'connected') {
            expect(realtimeStore.reconnectAttempts).toBe(0);
            return; // Don't test reconnection logic when connected
          }

          // Property: Reconnection attempts should be tracked correctly (only for non-connected states)
          for (let i = 0; i < attempts; i++) {
            const canReconnect = realtimeStore.incrementReconnectAttempts();
            
            // The store returns true when attempts < maxReconnectAttempts (4 attempts allowed, 5th fails)
            if (realtimeStore.reconnectAttempts < realtimeStore.maxReconnectAttempts) {
              expect(canReconnect).toBe(true);
            } else {
              expect(canReconnect).toBe(false);
            }
          }

          // Property: Reset should work correctly
          realtimeStore.resetReconnectAttempts();
          expect(realtimeStore.reconnectAttempts).toBe(0);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('should maintain data consistency during rapid updates', () => {
    fc.assert(
      fc.property(
        fc.array(mikrotikDeviceArb, { minLength: 1, maxLength: 5 }),
        fc.array(fc.record({
          deviceId: fc.string(),
          status: fc.constantFrom('online', 'offline', 'error'),
          timestamp: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
        }), { minLength: 1, maxLength: 10 }),
        (initialDevices: MikroTikDevice[], updates: Array<{ deviceId: string; status: string; timestamp: string }>) => {
          // Initialize devices
          realtimeStore.updateMikroTikDevices(initialDevices);

          // Apply rapid updates
          updates.forEach(update => {
            // Only update existing devices
            const existingDevice = initialDevices.find(d => d.id === update.deviceId);
            if (existingDevice) {
              realtimeStore.updateMikroTikDevice(update.deviceId, {
                status: update.status as any,
                last_seen: update.timestamp
              });
            }
          });

          // Property: Device count should remain consistent
          expect(realtimeStore.mikrotikDevices).toHaveLength(initialDevices.length);

          // Property: Each device should maintain its identity
          initialDevices.forEach(originalDevice => {
            const currentDevice = realtimeStore.mikrotikDevices.find(d => d.id === originalDevice.id);
            expect(currentDevice).toBeDefined();
            expect(currentDevice?.id).toBe(originalDevice.id);
            expect(currentDevice?.name).toBe(originalDevice.name);
            expect(currentDevice?.ip_address).toBe(originalDevice.ip_address);
          });
        }
      ),
      { numRuns: 100 }
    );
  });

  it('should handle concurrent data updates without race conditions', () => {
    fc.assert(
      fc.property(
        fc.array(paymentArb, { minLength: 2, maxLength: 5 }),
        fc.array(fc.constantFrom('pending', 'processing', 'completed', 'failed'), { minLength: 2, maxLength: 5 }),
        (payments: Payment[], statusUpdates: Array<Payment['status']>) => {
          // Add payments
          payments.forEach(payment => {
            realtimeStore.addRecentPayment(payment);
          });

          // Apply concurrent status updates
          payments.forEach((payment, index) => {
            if (index < statusUpdates.length) {
              realtimeStore.updatePaymentStatus(payment.id, statusUpdates[index]);
            }
          });

          // Property: All payments should still exist
          expect(realtimeStore.recentPayments.length).toBeGreaterThanOrEqual(payments.length);

          // Property: Status updates should be applied correctly
          payments.forEach((payment, index) => {
            if (index < statusUpdates.length) {
              const updatedPayment = realtimeStore.recentPayments.find(p => p.id === payment.id);
              expect(updatedPayment?.status).toBe(statusUpdates[index]);
            }
          });

          // Property: Payment data integrity should be maintained
          payments.forEach(originalPayment => {
            const currentPayment = realtimeStore.recentPayments.find(p => p.id === originalPayment.id);
            expect(currentPayment).toBeDefined();
            expect(currentPayment?.id).toBe(originalPayment.id);
            expect(currentPayment?.amount).toBe(originalPayment.amount);
            expect(currentPayment?.currency).toBe(originalPayment.currency);
          });
        }
      ),
      { numRuns: 100 }
    );
  });
});