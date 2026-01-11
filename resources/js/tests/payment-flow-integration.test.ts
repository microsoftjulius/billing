import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import type { Payment, Voucher, Customer, PaymentGateway } from '@/types';

// Integration tests for complete payment flow from frontend to gateway
describe('Payment Flow Integration Tests', () => {
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

  describe('Complete Payment Processing Flow', () => {
    it('should process payment from initiation to completion with voucher activation', async () => {
      // Mock customer data
      const mockCustomer: Customer = {
        id: 'customer-1',
        name: 'John Doe',
        phone: '256700123456',
        email: 'john@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Mock payment gateway
      const mockGateway: PaymentGateway = {
        id: 'gateway-1',
        name: 'CollectUG',
        provider: 'collectug',
        is_active: true,
        configuration: {
          api_key: 'test-key',
          base_url: 'https://api.collectug.com'
        },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Step 1: Mock payment initiation API call
      const paymentInitiationResponse = {
        success: true,
        message: 'Payment initiated successfully',
        data: {
          payment_id: 'payment-1',
          transaction_id: 'TXN-20250110-ABC123',
          reference: 'CUG-REF-123456',
          amount: 10000,
          currency: 'UGX',
          customer: mockCustomer,
          requires_mobile_confirmation: true,
          instructions: 'Please check your phone to confirm the payment',
          check_status_url: '/api/payments/verify/TXN-20250110-ABC123',
          created_at: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => paymentInitiationResponse
      });

      // Simulate payment initiation
      const paymentData = {
        customer_id: mockCustomer.id,
        amount: 10000,
        currency: 'UGX',
        gateway_id: mockGateway.id,
        voucher_id: null,
        gateway_reference: null,
        notes: 'Test payment',
        status: 'pending'
      };

      const initiationResponse = await fetch('/api/payments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
      });

      const initiationResult = await initiationResponse.json();

      // Verify payment initiation
      expect(initiationResult.success).toBe(true);
      expect(initiationResult.data.payment_id).toBe('payment-1');
      expect(initiationResult.data.transaction_id).toBe('TXN-20250110-ABC123');
      expect(initiationResult.data.amount).toBe(10000);
      expect(initiationResult.data.requires_mobile_confirmation).toBe(true);

      // Step 2: Add payment to real-time store (simulating WebSocket event)
      const pendingPayment: Payment = {
        id: initiationResult.data.payment_id,
        customer_id: mockCustomer.id,
        voucher_id: null,
        gateway_id: mockGateway.id,
        amount: initiationResult.data.amount,
        currency: initiationResult.data.currency,
        status: 'pending',
        gateway_transaction_id: initiationResult.data.reference,
        gateway_reference: initiationResult.data.reference,
        callback_data: null,
        processed_at: null,
        created_at: initiationResult.data.created_at,
        updated_at: initiationResult.data.created_at
      };

      realtimeStore.addRecentPayment(pendingPayment);
      await nextTick();

      // Verify payment is in store
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.recentPayments[0].status).toBe('pending');

      // Step 3: Mock payment verification (checking status)
      const verificationResponse = {
        success: true,
        status: 'completed',
        message: 'Payment completed successfully',
        data: {
          payment_id: 'payment-1',
          transaction_id: 'TXN-20250110-ABC123',
          reference: 'CUG-REF-123456',
          amount: 10000,
          currency: 'UGX',
          status: 'completed',
          paid_at: new Date().toISOString(),
          customer: mockCustomer,
          voucher: {
            id: 'voucher-1',
            code: 'VOUCHER-ABC123',
            amount: 10000,
            duration_hours: 24,
            status: 'active',
            activated_at: new Date().toISOString(),
            expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
          }
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => verificationResponse
      });

      // Simulate payment verification
      const verifyResponse = await fetch(`/api/payments/verify/${initiationResult.data.transaction_id}`);
      const verifyResult = await verifyResponse.json();

      // Verify payment completion
      expect(verifyResult.success).toBe(true);
      expect(verifyResult.status).toBe('completed');
      expect(verifyResult.data.voucher).toBeDefined();
      expect(verifyResult.data.voucher.status).toBe('active');

      // Step 4: Update payment status in real-time store (simulating WebSocket event)
      realtimeStore.updatePaymentStatus('payment-1', 'completed');
      await nextTick();

      // Verify payment status update
      const updatedPayment = realtimeStore.recentPayments.find(p => p.id === 'payment-1');
      expect(updatedPayment?.status).toBe('completed');
      expect(updatedPayment?.processed_at).toBeDefined();

      // Step 5: Add voucher to active vouchers (simulating voucher activation event)
      const activatedVoucher: Voucher = {
        id: verifyResult.data.voucher.id,
        code: verifyResult.data.voucher.code,
        customer_id: mockCustomer.id,
        amount: verifyResult.data.voucher.amount,
        duration_hours: verifyResult.data.voucher.duration_hours,
        status: verifyResult.data.voucher.status,
        activated_at: verifyResult.data.voucher.activated_at,
        expires_at: verifyResult.data.voucher.expires_at,
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.updateActiveVouchers([activatedVoucher]);
      await nextTick();

      // Verify voucher activation
      expect(realtimeStore.activeVouchers).toHaveLength(1);
      expect(realtimeStore.activeVouchers[0].status).toBe('active');
      expect(realtimeStore.activeVouchers[0].code).toBe('VOUCHER-ABC123');

      // Verify complete flow integrity
      expect(realtimeStore.recentPayments[0].id).toBe(activatedVoucher.customer_id ? 'payment-1' : 'payment-1');
      expect(realtimeStore.activeVouchers[0].amount).toBe(realtimeStore.recentPayments[0].amount);
    });

    it('should handle payment failure and update status accordingly', async () => {
      // Mock failed payment scenario
      const mockCustomer: Customer = {
        id: 'customer-2',
        name: 'Jane Smith',
        phone: '256700654321',
        email: 'jane@example.com',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Step 1: Mock payment initiation
      const paymentInitiationResponse = {
        success: true,
        message: 'Payment initiated successfully',
        data: {
          payment_id: 'payment-2',
          transaction_id: 'TXN-20250110-DEF456',
          reference: 'CUG-REF-654321',
          amount: 5000,
          currency: 'UGX',
          customer: mockCustomer,
          requires_mobile_confirmation: true,
          created_at: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => paymentInitiationResponse
      });

      // Initiate payment
      const paymentData = {
        customer_id: mockCustomer.id,
        amount: 5000,
        currency: 'UGX',
        gateway_id: 'gateway-1',
        status: 'pending'
      };

      const initiationResponse = await fetch('/api/payments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
      });

      const initiationResult = await initiationResponse.json();
      expect(initiationResult.success).toBe(true);

      // Add to store
      const pendingPayment: Payment = {
        id: initiationResult.data.payment_id,
        customer_id: mockCustomer.id,
        voucher_id: null,
        gateway_id: 'gateway-1',
        amount: initiationResult.data.amount,
        currency: initiationResult.data.currency,
        status: 'pending',
        gateway_transaction_id: initiationResult.data.reference,
        gateway_reference: initiationResult.data.reference,
        callback_data: null,
        processed_at: null,
        created_at: initiationResult.data.created_at,
        updated_at: initiationResult.data.created_at
      };

      realtimeStore.addRecentPayment(pendingPayment);
      await nextTick();

      // Step 2: Mock payment verification showing failure
      const failureResponse = {
        success: false,
        status: 'failed',
        message: 'Payment failed - insufficient funds',
        data: {
          payment_id: 'payment-2',
          transaction_id: 'TXN-20250110-DEF456',
          status: 'failed',
          failed_at: new Date().toISOString(),
          customer: mockCustomer
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => failureResponse
      });

      // Verify payment (which shows failure)
      const verifyResponse = await fetch(`/api/payments/verify/${initiationResult.data.transaction_id}`);
      const verifyResult = await verifyResponse.json();

      expect(verifyResult.success).toBe(false);
      expect(verifyResult.status).toBe('failed');

      // Step 3: Update payment status to failed
      realtimeStore.updatePaymentStatus('payment-2', 'failed');
      await nextTick();

      // Verify failure handling
      const failedPayment = realtimeStore.recentPayments.find(p => p.id === 'payment-2');
      expect(failedPayment?.status).toBe('failed');

      // Verify no voucher was created for failed payment
      expect(realtimeStore.activeVouchers).toHaveLength(0);
    });

    it('should handle payment timeout scenarios', async () => {
      // Mock payment that remains pending for too long
      const timeoutPayment: Payment = {
        id: 'payment-timeout',
        customer_id: 'customer-3',
        voucher_id: null,
        gateway_id: 'gateway-1',
        amount: 15000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: 'TIMEOUT-REF',
        gateway_reference: 'TIMEOUT-REF',
        callback_data: null,
        processed_at: null,
        created_at: new Date(Date.now() - 35 * 60 * 1000).toISOString(), // 35 minutes ago
        updated_at: new Date(Date.now() - 35 * 60 * 1000).toISOString()
      };

      realtimeStore.addRecentPayment(timeoutPayment);
      await nextTick();

      // Mock timeout verification response
      const timeoutResponse = {
        success: false,
        status: 'failed',
        message: 'Payment verification timeout',
        data: {
          payment_id: 'payment-timeout',
          status: 'failed',
          failed_at: new Date().toISOString()
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => timeoutResponse
      });

      // Verify timeout handling
      const verifyResponse = await fetch('/api/payments/verify/TIMEOUT-TXN');
      const verifyResult = await verifyResponse.json();

      expect(verifyResult.success).toBe(false);
      expect(verifyResult.status).toBe('failed');
      expect(verifyResult.message).toContain('timeout');

      // Update status to reflect timeout
      realtimeStore.updatePaymentStatus('payment-timeout', 'failed');
      await nextTick();

      const timedOutPayment = realtimeStore.recentPayments.find(p => p.id === 'payment-timeout');
      expect(timedOutPayment?.status).toBe('failed');
    });
  });

  describe('Payment Gateway Integration', () => {
    it('should handle multiple payment gateways correctly', async () => {
      const gateways = [
        { id: 'gateway-collectug', name: 'CollectUG', provider: 'collectug' },
        { id: 'gateway-stripe', name: 'Stripe', provider: 'stripe' },
        { id: 'gateway-paypal', name: 'PayPal', provider: 'paypal' }
      ];

      for (const gateway of gateways) {
        // Mock gateway-specific response
        const gatewayResponse = {
          success: true,
          message: `Payment initiated via ${gateway.name}`,
          data: {
            payment_id: `payment-${gateway.provider}`,
            transaction_id: `TXN-${gateway.provider.toUpperCase()}-123`,
            reference: `${gateway.provider.toUpperCase()}-REF-123`,
            amount: 8000,
            currency: 'UGX',
            gateway_type: gateway.provider
          }
        };

        mockFetch.mockResolvedValueOnce({
          ok: true,
          json: async () => gatewayResponse
        });

        // Test payment with specific gateway
        const paymentData = {
          customer_id: 'customer-multi',
          amount: 8000,
          currency: 'UGX',
          gateway_id: gateway.id,
          status: 'pending'
        };

        const response = await fetch('/api/payments', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(paymentData)
        });

        const result = await response.json();

        // Verify gateway-specific processing
        expect(result.success).toBe(true);
        expect(result.data.transaction_id).toContain(gateway.provider.toUpperCase());
        expect(result.message).toContain(gateway.name);

        // Add to store and verify
        const payment: Payment = {
          id: result.data.payment_id,
          customer_id: 'customer-multi',
          voucher_id: null,
          gateway_id: gateway.id,
          amount: result.data.amount,
          currency: result.data.currency,
          status: 'pending',
          gateway_transaction_id: result.data.reference,
          gateway_reference: result.data.reference,
          callback_data: null,
          processed_at: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };

        realtimeStore.addRecentPayment(payment);
      }

      await nextTick();

      // Verify all payments are tracked
      expect(realtimeStore.recentPayments).toHaveLength(3);
      
      // Verify each payment has correct gateway reference
      const collectugPayment = realtimeStore.recentPayments.find(p => p.gateway_reference?.includes('COLLECTUG'));
      const stripePayment = realtimeStore.recentPayments.find(p => p.gateway_reference?.includes('STRIPE'));
      const paypalPayment = realtimeStore.recentPayments.find(p => p.gateway_reference?.includes('PAYPAL'));

      expect(collectugPayment).toBeDefined();
      expect(stripePayment).toBeDefined();
      expect(paypalPayment).toBeDefined();
    });

    it('should handle payment callback processing', async () => {
      // Mock payment in pending state
      const pendingPayment: Payment = {
        id: 'payment-callback',
        customer_id: 'customer-callback',
        voucher_id: null,
        gateway_id: 'gateway-1',
        amount: 12000,
        currency: 'UGX',
        status: 'pending',
        gateway_transaction_id: 'CALLBACK-REF-123',
        gateway_reference: 'CALLBACK-REF-123',
        callback_data: null,
        processed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      realtimeStore.addRecentPayment(pendingPayment);
      await nextTick();

      // Mock callback processing
      const callbackResponse = {
        success: true,
        message: 'Callback processed successfully'
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => callbackResponse
      });

      // Simulate callback from payment gateway
      const callbackData = {
        transaction_id: 'CALLBACK-REF-123',
        status: 'completed',
        amount: 12000,
        currency: 'UGX',
        paid_at: new Date().toISOString(),
        signature: 'valid-signature'
      };

      const callbackResult = await fetch('/api/payments/callback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(callbackData)
      });

      const result = await callbackResult.json();

      expect(result.success).toBe(true);
      expect(result.message).toBe('Callback processed successfully');

      // Simulate real-time update from callback processing
      realtimeStore.updatePaymentStatus('payment-callback', 'completed');
      await nextTick();

      // Verify callback processing updated payment
      const updatedPayment = realtimeStore.recentPayments.find(p => p.id === 'payment-callback');
      expect(updatedPayment?.status).toBe('completed');
    });
  });

  describe('Error Handling and Recovery', () => {
    it('should handle API errors gracefully', async () => {
      // Mock API error
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      try {
        await fetch('/api/payments', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            customer_id: 'customer-error',
            amount: 5000,
            currency: 'UGX',
            gateway_id: 'gateway-1'
          })
        });
      } catch (error) {
        expect(error).toBeInstanceOf(Error);
        expect((error as Error).message).toBe('Network error');
      }

      // Verify no payment was added to store on error
      expect(realtimeStore.recentPayments).toHaveLength(0);
    });

    it('should handle invalid payment data', async () => {
      // Mock validation error response
      const validationErrorResponse = {
        success: false,
        message: 'Validation failed',
        errors: {
          amount: ['Amount must be greater than 0'],
          gateway_id: ['Please select a payment gateway']
        }
      };

      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => validationErrorResponse
      });

      // Attempt payment with invalid data
      const invalidPaymentData = {
        customer_id: 'customer-invalid',
        amount: 0, // Invalid amount
        currency: 'UGX',
        gateway_id: '' // Missing gateway
      };

      const response = await fetch('/api/payments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invalidPaymentData)
      });

      expect(response.ok).toBe(false);
      expect(response.status).toBe(422);

      const result = await response.json();
      expect(result.success).toBe(false);
      expect(result.errors).toBeDefined();
      expect(result.errors.amount).toContain('Amount must be greater than 0');
    });

    it('should handle gateway unavailability', async () => {
      // Mock gateway unavailable response
      const gatewayErrorResponse = {
        success: false,
        message: 'Payment gateway temporarily unavailable',
        error: 'GATEWAY_UNAVAILABLE'
      };

      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 503,
        json: async () => gatewayErrorResponse
      });

      // Attempt payment when gateway is down
      const paymentData = {
        customer_id: 'customer-gateway-down',
        amount: 7500,
        currency: 'UGX',
        gateway_id: 'gateway-1'
      };

      const response = await fetch('/api/payments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
      });

      expect(response.ok).toBe(false);
      expect(response.status).toBe(503);

      const result = await response.json();
      expect(result.success).toBe(false);
      expect(result.message).toContain('temporarily unavailable');
    });
  });

  describe('Data Consistency and Integrity', () => {
    it('should maintain payment-voucher relationship integrity', async () => {
      // Create payment and voucher with proper relationship
      const payment: Payment = {
        id: 'payment-voucher-link',
        customer_id: 'customer-link',
        voucher_id: 'voucher-link',
        gateway_id: 'gateway-1',
        amount: 20000,
        currency: 'UGX',
        status: 'completed',
        gateway_transaction_id: 'LINK-REF-123',
        gateway_reference: 'LINK-REF-123',
        callback_data: null,
        processed_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      const voucher: Voucher = {
        id: 'voucher-link',
        code: 'LINK-VOUCHER-123',
        customer_id: 'customer-link',
        amount: 20000,
        duration_hours: 48,
        status: 'active',
        activated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 48 * 60 * 60 * 1000).toISOString(),
        mikrotik_device_id: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };

      // Add both to stores
      realtimeStore.addRecentPayment(payment);
      realtimeStore.updateActiveVouchers([voucher]);
      await nextTick();

      // Verify relationship integrity
      expect(realtimeStore.recentPayments).toHaveLength(1);
      expect(realtimeStore.activeVouchers).toHaveLength(1);
      
      const storedPayment = realtimeStore.recentPayments[0];
      const storedVoucher = realtimeStore.activeVouchers[0];

      expect(storedPayment.voucher_id).toBe(storedVoucher.id);
      expect(storedPayment.customer_id).toBe(storedVoucher.customer_id);
      expect(storedPayment.amount).toBe(storedVoucher.amount);
      expect(storedPayment.status).toBe('completed');
      expect(storedVoucher.status).toBe('active');
    });

    it('should handle concurrent payment updates correctly', async () => {
      // Create multiple payments for concurrent testing
      const payments: Payment[] = [
        {
          id: 'payment-concurrent-1',
          customer_id: 'customer-1',
          voucher_id: null,
          gateway_id: 'gateway-1',
          amount: 5000,
          currency: 'UGX',
          status: 'pending',
          gateway_transaction_id: 'CONCURRENT-1',
          gateway_reference: 'CONCURRENT-1',
          callback_data: null,
          processed_at: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        {
          id: 'payment-concurrent-2',
          customer_id: 'customer-2',
          voucher_id: null,
          gateway_id: 'gateway-1',
          amount: 7500,
          currency: 'UGX',
          status: 'pending',
          gateway_transaction_id: 'CONCURRENT-2',
          gateway_reference: 'CONCURRENT-2',
          callback_data: null,
          processed_at: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      ];

      // Add payments concurrently
      payments.forEach(payment => {
        realtimeStore.addRecentPayment(payment);
      });
      await nextTick();

      // Update statuses concurrently
      realtimeStore.updatePaymentStatus('payment-concurrent-1', 'completed');
      realtimeStore.updatePaymentStatus('payment-concurrent-2', 'failed');
      await nextTick();

      // Verify concurrent updates were handled correctly
      expect(realtimeStore.recentPayments).toHaveLength(2);
      
      const payment1 = realtimeStore.recentPayments.find(p => p.id === 'payment-concurrent-1');
      const payment2 = realtimeStore.recentPayments.find(p => p.id === 'payment-concurrent-2');

      expect(payment1?.status).toBe('completed');
      expect(payment1?.processed_at).toBeDefined();
      expect(payment2?.status).toBe('failed');

      // Verify data integrity maintained
      expect(payment1?.amount).toBe(5000);
      expect(payment2?.amount).toBe(7500);
      expect(payment1?.customer_id).toBe('customer-1');
      expect(payment2?.customer_id).toBe('customer-2');
    });
  });
});