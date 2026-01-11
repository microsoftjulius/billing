import { defineStore } from 'pinia';
import { ref } from 'vue';
import { MikroTikDevice, Payment, Voucher } from '@/types';

export const useRealtimeStore = defineStore('realtime', () => {
  // State
  const mikrotikDevices = ref<MikroTikDevice[]>([]);
  const recentPayments = ref<Payment[]>([]);
  const activeVouchers = ref<Voucher[]>([]);
  const connectionStatus = ref<'connected' | 'disconnected' | 'reconnecting'>('disconnected');
  const reconnectAttempts = ref(0);
  const maxReconnectAttempts = 5;

  // Actions
  const setConnectionStatus = (status: 'connected' | 'disconnected' | 'reconnecting') => {
    connectionStatus.value = status;
    if (status === 'connected') {
      reconnectAttempts.value = 0;
    }
  };

  const updateMikroTikDevices = (devices: MikroTikDevice[]) => {
    mikrotikDevices.value = devices;
  };

  const updateMikroTikDevice = (deviceId: string, updates: Partial<MikroTikDevice>) => {
    const index = mikrotikDevices.value.findIndex(device => device.id === deviceId);
    if (index > -1) {
      mikrotikDevices.value[index] = { ...mikrotikDevices.value[index], ...updates };
    }
  };

  const addRecentPayment = (payment: Payment) => {
    recentPayments.value.unshift(payment);
    // Keep only the last 50 payments
    if (recentPayments.value.length > 50) {
      recentPayments.value = recentPayments.value.slice(0, 50);
    }
  };

  const updatePaymentStatus = (paymentId: string, status: Payment['status']) => {
    const payment = recentPayments.value.find(p => p.id === paymentId);
    if (payment) {
      payment.status = status;
      if (status === 'completed') {
        payment.processed_at = new Date().toISOString();
      }
    }
  };

  const updateActiveVouchers = (vouchers: Voucher[]) => {
    activeVouchers.value = vouchers;
  };

  const updateVoucherStatus = (voucherId: string, status: Voucher['status']) => {
    const voucher = activeVouchers.value.find(v => v.id === voucherId);
    if (voucher) {
      voucher.status = status;
      if (status === 'active' && !voucher.activated_at) {
        voucher.activated_at = new Date().toISOString();
      }
    }
  };

  const incrementReconnectAttempts = () => {
    reconnectAttempts.value++;
    return reconnectAttempts.value < maxReconnectAttempts;
  };

  const resetReconnectAttempts = () => {
    reconnectAttempts.value = 0;
  };

  return {
    // State
    mikrotikDevices,
    recentPayments,
    activeVouchers,
    connectionStatus,
    reconnectAttempts,
    maxReconnectAttempts,
    
    // Actions
    setConnectionStatus,
    updateMikroTikDevices,
    updateMikroTikDevice,
    addRecentPayment,
    updatePaymentStatus,
    updateActiveVouchers,
    updateVoucherStatus,
    incrementReconnectAttempts,
    resetReconnectAttempts,
  };
});