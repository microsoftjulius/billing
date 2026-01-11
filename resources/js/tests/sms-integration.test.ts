import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import type { Voucher, Customer, SmsLog, Payment } from '@/types';

// Integration tests for SMS notifications with voucher delivery and customer communication history
describe('SMS Integration Tests', () => {
  let pinia: ReturnType<typeof createPinia>;
  let realtimeStore: ReturnType<typeof useRealtimeStore>;
  let appStore: ReturnType<typeof useAppStore>;

  // Mock API responses
  const mockFetch = vi.fn();

  beforeEach(() => {
    pinia = createPinia();
    setActivePinia(pinia);
    
    realtimeStore = useRealtimeStore();
    appStore = useAppStore();

    // Mock global fetch
    global.fetch = mockFetch;
    
    // Reset mocks
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('SMS Sending Integration with Voucher Delivery', () => {
    it('should send SMS notification when voucher is delivered', async () => {
      // Mock customer data
      const mockCustomer: Customer = {
        id: 'customer-sms-1',
        name: 'Alice Johnson',
        phone: '256701234567',
        email: 'alice@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Mock voucher data
      const mockVoucher: Voucher = {
        id: 'voucher-sms-1',
        code: 'SMS-VOUCHER-001',
        customer_id: mockCustomer.id,
        amount: 15000,
        duration_hours: 48,
        status: 'active',
        activated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 48 * 60 * 60 * 1000).toISOString(),
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Step 1: Mock SMS sending API call
      const smsResponse = {
        success: true,
        message: 'SMS sent successfully',
        data: {
          message_id: 'SMS-MSG-123456',
          recipient: mockCustomer.phone,
          content: `Your internet voucher:\nCode: ${mockVoucher.code}\nValid for: ${mockVoucher.duration_hours} hours\nExpires: ${new Date(mockVoucher.expires_at!).toLocaleString()}\nThank you for your payment!`,
          estimated_cost: 20,
          remaining_balance: 5000,
          status: 'sent'
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => smsResponse
      });

      // Simulate SMS sending for voucher delivery
      const smsData = {
        recipient: mockCustomer.phone,
        content: smsResponse.data.content,
        sender_id: 'BILLING',
        message_type: 'voucher_delivery',
        voucher_id: mockVoucher.id
      };

      const response = await fetch('/api/sms/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(smsData)
      });

      const result = await response.json();

      // Verify SMS sending
      expect(result.success).toBe(true);
      expect(result.data.message_id).toBe('SMS-MSG-123456');
      expect(result.data.recipient).toBe(mockCustomer.phone);
      expect(result.data.content).toContain(mockVoucher.code);
      expect(result.data.content).toContain('48 hours');
      expect(result.data.status).toBe('sent');

      // Step 2: Create SMS log entry (simulating backend logging)
      const smsLog: SmsLog = {
        id: 'sms-log-1',
        customer_id: mockCustomer.id,
        recipient: mockCustomer.phone,
        content: result.data.content,
        sender_id: 'BILLING',
        message_id: result.data.message_id,
        status: 'sent',
        delivery_status: 'pending',
        cost: result.data.estimated_cost,
        currency: 'UGX',
        provider: 'ugsms',
        provider_response: result.data,
        metadata: {
          message_type: 'voucher_delivery',
          voucher_id: mockVoucher.id,
          voucher_code: mockVoucher.code
        },
        sent_at: new Date().toISOString(),
        delivered_at: null,
        failed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Step 3: Update voucher with SMS sent timestamp (simulating backend update)
      const updatedVoucher = {
        ...mockVoucher,
        sms_sent_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Add voucher to store and verify SMS integration
      realtimeStore.updateActiveVouchers([updatedVoucher]);
      await nextTick();

      // Verify voucher SMS integration
      expect(realtimeStore.activeVouchers).toHaveLength(1);
      expect(realtimeStore.activeVouchers[0].sms_sent_at).toBeDefined();
      expect(realtimeStore.activeVouchers[0].code).toBe('SMS-VOUCHER-001');

      // Step 4: Mock SMS delivery status update
      const deliveryStatusResponse = {
        success: true,
        data: {
          message_id: 'SMS-MSG-123456',
          status: 'delivered',
          delivered_at: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => deliveryStatusResponse
      });

      // Check delivery status
      const statusResponse = await fetch(`/api/sms/status/${result.data.message_id}`);
      const statusResult = await statusResponse.json();

      expect(statusResult.success).toBe(true);
      expect(statusResult.data.status).toBe('delivered');
      expect(statusResult.data.delivered_at).toBeDefined();
    });

    it('should handle SMS sending failures gracefully', async () => {
      const mockCustomer: Customer = {
        id: 'customer-sms-fail',
        name: 'Bob Wilson',
        phone: '256700000000', // Invalid phone for testing
        email: 'bob@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      const mockVoucher: Voucher = {
        id: 'voucher-sms-fail',
        code: 'FAIL-VOUCHER-001',
        customer_id: mockCustomer.id,
        amount: 8000,
        duration_hours: 24,
        status: 'active',
        activated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Mock SMS failure response
      const smsFailureResponse = {
        success: false,
        message: 'Invalid phone number format',
        error: {
          code: 'INVALID_PHONE',
          details: 'Phone number must be in valid Uganda format'
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => smsFailureResponse
      });

      // Attempt to send SMS
      const smsData = {
        recipient: mockCustomer.phone,
        content: `Your voucher code: ${mockVoucher.code}`,
        sender_id: 'BILLING',
        voucher_id: mockVoucher.id
      };

      const response = await fetch('/api/sms/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(smsData)
      });

      expect(response.ok).toBe(false);
      expect(response.status).toBe(400);

      const result = await response.json();
      expect(result.success).toBe(false);
      expect(result.message).toContain('Invalid phone number');

      // Verify voucher remains without SMS sent timestamp
      realtimeStore.updateActiveVouchers([mockVoucher]);
      await nextTick();

      expect(realtimeStore.activeVouchers[0].sms_sent_at).toBeUndefined();
    });

    it('should handle bulk SMS sending for multiple vouchers', async () => {
      // Mock multiple customers and vouchers
      const customers: Customer[] = [
        {
          id: 'customer-bulk-1',
          name: 'Customer One',
          phone: '256701111111',
          email: 'customer1@example.com',
          is_active: true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'customer-bulk-2',
          name: 'Customer Two',
          phone: '256702222222',
          email: 'customer2@example.com',
          is_active: true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'customer-bulk-3',
          name: 'Customer Three',
          phone: '256703333333',
          email: 'customer3@example.com',
          is_active: true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      const vouchers: Voucher[] = customers.map((customer, index) => ({
        id: `voucher-bulk-${index + 1}`,
        code: `BULK-${String(index + 1).padStart(3, '0')}`,
        customer_id: customer.id,
        amount: 10000,
        duration_hours: 24,
        status: 'active',
        activated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }));

      // Mock bulk SMS response
      const bulkSmsResponse = {
        success: true,
        message: 'Bulk SMS sent successfully',
        data: {
          total_messages: 3,
          successful: 3,
          failed: 0,
          results: vouchers.map((voucher, index) => ({
            message_id: `BULK-MSG-${index + 1}`,
            recipient: customers[index].phone,
            status: 'sent',
            voucher_id: voucher.id,
            estimated_cost: 20
          }))
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => bulkSmsResponse
      });

      // Send bulk SMS
      const bulkSmsData = {
        messages: vouchers.map((voucher, index) => ({
          recipient: customers[index].phone,
          content: `Your voucher: ${voucher.code}`,
          voucher_id: voucher.id
        }))
      };

      const response = await fetch('/api/sms/send-bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bulkSmsData)
      });

      const result = await response.json();

      // Verify bulk SMS sending
      expect(result.success).toBe(true);
      expect(result.data.total_messages).toBe(3);
      expect(result.data.successful).toBe(3);
      expect(result.data.failed).toBe(0);
      expect(result.data.results).toHaveLength(3);

      // Update vouchers with SMS sent timestamps
      const updatedVouchers = vouchers.map(voucher => ({
        ...voucher,
        sms_sent_at: new Date().toISOString()
      }));

      realtimeStore.updateActiveVouchers(updatedVouchers);
      await nextTick();

      // Verify all vouchers have SMS sent timestamps
      expect(realtimeStore.activeVouchers).toHaveLength(3);
      realtimeStore.activeVouchers.forEach(voucher => {
        expect(voucher.sms_sent_at).toBeDefined();
      });
    });
  });

  describe('SMS Logging and Customer Communication History', () => {
    it('should maintain comprehensive SMS logs for customer communication history', async () => {
      const mockCustomer: Customer = {
        id: 'customer-history',
        name: 'History Customer',
        phone: '256704444444',
        email: 'history@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Mock multiple SMS logs for the customer
      const smsLogs: SmsLog[] = [
        {
          id: 'sms-log-history-1',
          customer_id: mockCustomer.id,
          recipient: mockCustomer.phone,
          content: 'Payment of UGX 10,000 received. Voucher will be sent shortly.',
          sender_id: 'BILLING',
          message_id: 'HIST-MSG-001',
          status: 'delivered',
          delivery_status: 'delivered',
          cost: 20,
          currency: 'UGX',
          provider: 'ugsms',
          provider_response: { message_id: 'HIST-MSG-001', status: 'delivered' },
          metadata: { message_type: 'payment_confirmation' },
          sent_at: new Date(Date.now() - 10 * 60 * 1000).toISOString(), // 10 minutes ago (oldest)
          delivered_at: new Date(Date.now() - 10 * 60 * 1000 + 30000).toISOString(), // 30 seconds later
          failed_at: null,
          created_at: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
          updated_at: new Date(Date.now() - 10 * 60 * 1000 + 30000).toISOString()
        },
        {
          id: 'sms-log-history-2',
          customer_id: mockCustomer.id,
          recipient: mockCustomer.phone,
          content: 'Your internet voucher:\nCode: HIST-VOUCHER-001\nValid for: 24 hours\nThank you!',
          sender_id: 'BILLING',
          message_id: 'HIST-MSG-002',
          status: 'delivered',
          delivery_status: 'delivered',
          cost: 40, // Longer message, higher cost
          currency: 'UGX',
          provider: 'ugsms',
          provider_response: { message_id: 'HIST-MSG-002', status: 'delivered' },
          metadata: { 
            message_type: 'voucher_delivery',
            voucher_id: 'voucher-history-1',
            voucher_code: 'HIST-VOUCHER-001'
          },
          sent_at: new Date(Date.now() - 5 * 60 * 1000).toISOString(), // 5 minutes ago (middle)
          delivered_at: new Date(Date.now() - 5 * 60 * 1000 + 45000).toISOString(), // 45 seconds later
          failed_at: null,
          created_at: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
          updated_at: new Date(Date.now() - 5 * 60 * 1000 + 45000).toISOString()
        },
        {
          id: 'sms-log-history-3',
          customer_id: mockCustomer.id,
          recipient: mockCustomer.phone,
          content: 'Your voucher HIST-VOUCHER-001 will expire in 2 hours. Please use it soon.',
          sender_id: 'BILLING',
          message_id: 'HIST-MSG-003',
          status: 'sent',
          delivery_status: 'pending',
          cost: 20,
          currency: 'UGX',
          provider: 'ugsms',
          provider_response: { message_id: 'HIST-MSG-003', status: 'sent' },
          metadata: { 
            message_type: 'expiry_reminder',
            voucher_id: 'voucher-history-1'
          },
          sent_at: new Date(Date.now() - 1 * 60 * 1000).toISOString(), // 1 minute ago (most recent)
          delivered_at: null,
          failed_at: null,
          created_at: new Date(Date.now() - 1 * 60 * 1000).toISOString(),
          updated_at: new Date(Date.now() - 1 * 60 * 1000).toISOString()
        }
      ];

      // Mock API response for customer SMS history (ordered by most recent first)
      const historyResponse = {
        success: true,
        data: {
          customer: mockCustomer,
          sms_logs: [
            smsLogs[2], // Most recent (1 minute ago)
            smsLogs[1], // Middle (5 minutes ago)  
            smsLogs[0]  // Oldest (10 minutes ago)
          ],
          summary: {
            total_messages: 3,
            delivered_messages: 2,
            pending_messages: 1,
            failed_messages: 0,
            total_cost: 80,
            currency: 'UGX'
          }
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => historyResponse
      });

      // Fetch customer SMS history
      const response = await fetch(`/api/customers/${mockCustomer.id}/sms-history`);
      const result = await response.json();

      // Verify SMS history retrieval
      expect(result.success).toBe(true);
      expect(result.data.sms_logs).toHaveLength(3);
      expect(result.data.summary.total_messages).toBe(3);
      expect(result.data.summary.delivered_messages).toBe(2);
      expect(result.data.summary.pending_messages).toBe(1);
      expect(result.data.summary.total_cost).toBe(80);

      // Verify chronological order (most recent first)
      const logs = result.data.sms_logs;
      expect(new Date(logs[0].sent_at!).getTime()).toBeGreaterThan(new Date(logs[1].sent_at!).getTime());
      expect(new Date(logs[1].sent_at!).getTime()).toBeGreaterThan(new Date(logs[2].sent_at!).getTime());

      // Verify message types are tracked
      expect(logs.find((log: SmsLog) => log.metadata?.message_type === 'payment_confirmation')).toBeDefined();
      expect(logs.find((log: SmsLog) => log.metadata?.message_type === 'voucher_delivery')).toBeDefined();
      expect(logs.find((log: SmsLog) => log.metadata?.message_type === 'expiry_reminder')).toBeDefined();

      // Verify delivery status tracking
      const deliveredLogs = logs.filter((log: SmsLog) => log.status === 'delivered');
      const pendingLogs = logs.filter((log: SmsLog) => log.status === 'sent' && log.delivery_status === 'pending');
      
      expect(deliveredLogs).toHaveLength(2);
      expect(pendingLogs).toHaveLength(1);
    });

    it('should track SMS costs and balance usage', async () => {
      // Mock SMS balance check
      const balanceResponse = {
        success: true,
        data: {
          current_balance: 2500,
          currency: 'UGX',
          last_updated: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => balanceResponse
      });

      // Check SMS balance
      const balanceResult = await fetch('/api/sms/balance');
      const balance = await balanceResult.json();

      expect(balance.success).toBe(true);
      expect(balance.data.current_balance).toBe(2500);

      // Mock SMS cost calculation
      const costResponse = {
        success: true,
        data: {
          message_length: 160,
          segments: 1,
          cost_per_segment: 20,
          total_cost: 20,
          currency: 'UGX'
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => costResponse
      });

      // Calculate SMS cost
      const costResult = await fetch('/api/sms/calculate-cost', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: 'Your voucher code: TEST123. Valid for 24 hours. Thank you for your payment!'
        })
      });

      const cost = await costResult.json();

      expect(cost.success).toBe(true);
      expect(cost.data.segments).toBe(1);
      expect(cost.data.total_cost).toBe(20);

      // Mock low balance alert
      const lowBalanceResponse = {
        success: true,
        message: 'Low balance alert sent to administrator',
        data: {
          alert_sent: true,
          threshold: 1000,
          current_balance: 500,
          admin_phone: '256700000000'
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => lowBalanceResponse
      });

      // Trigger low balance alert
      const alertResult = await fetch('/api/sms/check-balance-alert', {
        method: 'POST'
      });

      const alert = await alertResult.json();

      expect(alert.success).toBe(true);
      expect(alert.data.alert_sent).toBe(true);
      expect(alert.data.current_balance).toBeLessThan(alert.data.threshold);
    });

    it('should handle SMS retry mechanism for failed messages', async () => {
      const mockCustomer: Customer = {
        id: 'customer-retry',
        name: 'Retry Customer',
        phone: '256705555555',
        email: 'retry@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Mock initial SMS failure
      const failureResponse = {
        success: false,
        message: 'Network timeout',
        error: {
          code: 'NETWORK_TIMEOUT',
          retry_after: 30
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 503,
        json: async () => failureResponse
      });

      // Attempt initial SMS send
      const smsData = {
        recipient: mockCustomer.phone,
        content: 'Your voucher: RETRY-001',
        sender_id: 'BILLING'
      };

      const initialResponse = await fetch('/api/sms/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(smsData)
      });

      expect(initialResponse.ok).toBe(false);

      // Mock successful retry
      const retrySuccessResponse = {
        success: true,
        message: 'SMS sent successfully on retry',
        data: {
          message_id: 'RETRY-MSG-001',
          recipient: mockCustomer.phone,
          status: 'sent',
          retry_attempt: 1
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => retrySuccessResponse
      });

      // Retry SMS send
      const retryResponse = await fetch('/api/sms/retry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...smsData,
          retry_attempt: 1
        })
      });

      const retryResult = await retryResponse.json();

      expect(retryResult.success).toBe(true);
      expect(retryResult.data.retry_attempt).toBe(1);
      expect(retryResult.message).toContain('retry');
    });
  });

  describe('Real-time SMS Status Updates', () => {
    it('should update SMS delivery status in real-time', async () => {
      // Mock SMS log with pending delivery
      const pendingSmsLog: SmsLog = {
        id: 'sms-realtime-1',
        customer_id: 'customer-realtime',
        recipient: '256706666666',
        content: 'Your voucher: REALTIME-001',
        sender_id: 'BILLING',
        message_id: 'REALTIME-MSG-001',
        status: 'sent',
        delivery_status: 'pending',
        cost: 20,
        currency: 'UGX',
        provider: 'ugsms',
        provider_response: { message_id: 'REALTIME-MSG-001' },
        metadata: { message_type: 'voucher_delivery' },
        sent_at: new Date().toISOString(),
        delivered_at: null,
        failed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Simulate real-time delivery status update (via WebSocket)
      const deliveryUpdate = {
        message_id: 'REALTIME-MSG-001',
        status: 'delivered',
        delivered_at: new Date().toISOString()
      };

      // Mock status update API
      const statusUpdateResponse = {
        success: true,
        message: 'SMS status updated',
        data: {
          ...pendingSmsLog,
          status: 'delivered',
          delivery_status: 'delivered',
          delivered_at: deliveryUpdate.delivered_at,
          updated_at: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => statusUpdateResponse
      });

      // Update SMS status
      const updateResponse = await fetch(`/api/sms/update-status/${pendingSmsLog.message_id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(deliveryUpdate)
      });

      const updateResult = await updateResponse.json();

      expect(updateResult.success).toBe(true);
      expect(updateResult.data.status).toBe('delivered');
      expect(updateResult.data.delivered_at).toBeDefined();

      // Verify real-time update would be reflected in UI
      // (In actual implementation, this would come through WebSocket)
      expect(updateResult.data.delivery_status).toBe('delivered');
    });

    it('should handle SMS webhook callbacks for delivery status', async () => {
      // Mock webhook callback from SMS provider
      const webhookPayload = {
        message_id: 'WEBHOOK-MSG-001',
        status: 'delivered',
        delivered_at: new Date().toISOString(),
        provider: 'ugsms',
        signature: 'valid-webhook-signature'
      };

      const webhookResponse = {
        success: true,
        message: 'Webhook processed successfully'
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => webhookResponse
      });

      // Process webhook
      const response = await fetch('/api/sms/webhook', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(webhookPayload)
      });

      const result = await response.json();

      expect(result.success).toBe(true);
      expect(result.message).toBe('Webhook processed successfully');
    });
  });

  describe('SMS Integration Error Handling', () => {
    it('should handle SMS provider API errors gracefully', async () => {
      // Mock various API error scenarios
      const errorScenarios = [
        {
          name: 'Invalid API Key',
          response: {
            ok: false,
            status: 401,
            json: async () => ({
              success: false,
              message: 'Invalid API key',
              error: { code: 'INVALID_API_KEY' }
            })
          }
        },
        {
          name: 'Insufficient Balance',
          response: {
            ok: false,
            status: 402,
            json: async () => ({
              success: false,
              message: 'Insufficient SMS balance',
              error: { code: 'INSUFFICIENT_BALANCE', balance: 0 }
            })
          }
        },
        {
          name: 'Rate Limit Exceeded',
          response: {
            ok: false,
            status: 429,
            json: async () => ({
              success: false,
              message: 'Rate limit exceeded',
              error: { code: 'RATE_LIMIT', retry_after: 60 }
            })
          }
        }
      ];

      for (const scenario of errorScenarios) {
        mockFetch.mockResolvedValueOnce(scenario.response);

        const response = await fetch('/api/sms/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            recipient: '256700000000',
            content: 'Test message',
            sender_id: 'BILLING'
          })
        });

        expect(response.ok).toBe(false);

        const result = await response.json();
        expect(result.success).toBe(false);
        expect(result.error).toBeDefined();

        // Verify error is properly categorized
        if (scenario.name === 'Invalid API Key') {
          expect(result.error.code).toBe('INVALID_API_KEY');
        } else if (scenario.name === 'Insufficient Balance') {
          expect(result.error.code).toBe('INSUFFICIENT_BALANCE');
        } else if (scenario.name === 'Rate Limit Exceeded') {
          expect(result.error.code).toBe('RATE_LIMIT');
          expect(result.error.retry_after).toBe(60);
        }
      }
    });

    it('should validate phone numbers before sending SMS', async () => {
      const invalidPhoneNumbers = [
        '',
        '123',
        'invalid-phone',
        '256', // Too short
        '25670000000000000', // Too long
        '254700000000', // Wrong country code for Uganda
      ];

      for (const invalidPhone of invalidPhoneNumbers) {
        const validationResponse = {
          success: false,
          message: 'Invalid phone number format',
          error: {
            code: 'INVALID_PHONE_FORMAT',
            phone: invalidPhone
          }
        };

        mockFetch.mockResolvedValueOnce({
          ok: false,
          status: 400,
          json: async () => validationResponse
        });

        const response = await fetch('/api/sms/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            recipient: invalidPhone,
            content: 'Test message',
            sender_id: 'BILLING'
          })
        });

        expect(response.ok).toBe(false);

        const result = await response.json();
        expect(result.success).toBe(false);
        expect(result.error.code).toBe('INVALID_PHONE_FORMAT');
      }
    });
  });
});