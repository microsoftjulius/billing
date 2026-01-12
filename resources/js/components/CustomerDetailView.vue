<template>
  <div class="customer-detail-view">
    <!-- Customer Header -->
    <div class="customer-header">
      <div class="customer-info">
        <div class="customer-avatar">
          <span class="avatar-text">{{ customer.name?.charAt(0)?.toUpperCase() || '?' }}</span>
        </div>
        <div class="customer-details">
          <h2 class="customer-name">{{ customer.name }}</h2>
          <div class="customer-meta">
            <span class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              {{ customer.email || 'No email' }}
            </span>
            <span class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              {{ customer.phone }}
            </span>
            <span class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0V6a2 2 0 012-2h4a2 2 0 012 2v1m-6 0h8m-9 0h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z" />
              </svg>
              Member since {{ formatDate(customer.created_at) }}
            </span>
          </div>
        </div>
      </div>
      <div class="customer-status">
        <span class="status-badge" :class="`status-${customer.status}`">
          {{ formatStatus(customer.status) }}
        </span>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <button 
        class="action-btn primary"
        @click="showRecordPaymentModal = true"
      >
        <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
        </svg>
        Record Payment
      </button>
      
      <button 
        v-if="customer.status === 'active'"
        class="action-btn warning"
        @click="$emit('suspend-service', customer.id)"
      >
        <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Suspend Service
      </button>
      
      <button 
        v-else-if="customer.status === 'suspended'"
        class="action-btn success"
        @click="$emit('activate-service', customer.id)"
      >
        <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-9 5a9 9 0 1118 0 9 9 0 01-18 0z" />
        </svg>
        Activate Service
      </button>
      
      <button 
        class="action-btn secondary"
        @click="showSendSmsModal = true"
      >
        <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        Send SMS
      </button>
    </div>

    <!-- Customer Information Tabs -->
    <div class="detail-tabs">
      <div class="tab-nav">
        <button 
          v-for="tab in tabs" 
          :key="tab.key"
          class="tab-button"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
          </svg>
          {{ tab.label }}
        </button>
      </div>

      <div class="tab-content">
        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="tab-panel">
          <div class="info-grid">
            <div class="info-card">
              <h3 class="card-title">Contact Information</h3>
              <div class="info-list">
                <div class="info-item">
                  <span class="info-label">Email:</span>
                  <span class="info-value">{{ customer.email || 'Not provided' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Phone:</span>
                  <span class="info-value">{{ customer.phone }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Status:</span>
                  <span class="status-badge" :class="`status-${customer.status}`">
                    {{ formatStatus(customer.status) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="info-card">
              <h3 class="card-title">Location Information</h3>
              <div class="info-list">
                <div v-if="customer.location" class="info-item">
                  <span class="info-label">Region:</span>
                  <span class="info-value">{{ customer.location.region }}</span>
                </div>
                <div v-if="customer.location" class="info-item">
                  <span class="info-label">District:</span>
                  <span class="info-value">{{ customer.location.district }}</span>
                </div>
                <div v-if="customer.location?.coordinates" class="info-item">
                  <span class="info-label">Coordinates:</span>
                  <span class="info-value">
                    {{ customer.location.coordinates.lat }}, {{ customer.location.coordinates.lng }}
                  </span>
                </div>
                <div v-if="!customer.location" class="info-item">
                  <span class="info-value text-muted">No location information available</span>
                </div>
              </div>
            </div>

            <div class="info-card">
              <h3 class="card-title">Service Plan</h3>
              <div class="info-list">
                <div v-if="customer.service_plan" class="info-item">
                  <span class="info-label">Plan Name:</span>
                  <span class="info-value">{{ customer.service_plan.name }}</span>
                </div>
                <div v-if="customer.service_plan" class="info-item">
                  <span class="info-label">Price:</span>
                  <span class="info-value">{{ formatCurrency(customer.service_plan.price) }}</span>
                </div>
                <div v-if="customer.service_plan" class="info-item">
                  <span class="info-label">Duration:</span>
                  <span class="info-value">{{ customer.service_plan.duration_hours }} hours</span>
                </div>
                <div v-if="!customer.service_plan" class="info-item">
                  <span class="info-value text-muted">No service plan assigned</span>
                </div>
              </div>
            </div>

            <div class="info-card">
              <h3 class="card-title">Account Statistics</h3>
              <div class="info-list">
                <div class="info-item">
                  <span class="info-label">Total Payments:</span>
                  <span class="info-value">{{ paymentHistory.length }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Total Spent:</span>
                  <span class="info-value">{{ formatCurrency(totalSpent) }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Last Payment:</span>
                  <span class="info-value">
                    {{ lastPayment ? formatDate(lastPayment.created_at) : 'No payments' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment History Tab -->
        <div v-if="activeTab === 'payments'" class="tab-panel">
          <div class="section-header">
            <h3 class="section-title">Payment History</h3>
            <button class="refresh-btn" @click="$emit('refresh-payments')" :disabled="loadingPayments">
              <svg class="refresh-icon" :class="{ 'animate-spin': loadingPayments }" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          </div>

          <div v-if="loadingPayments" class="loading-state">
            <div class="loading-spinner"></div>
            <span>Loading payment history...</span>
          </div>

          <div v-else-if="paymentHistory.length === 0" class="empty-state">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
            </svg>
            <p>No payment history available</p>
          </div>

          <div v-else class="payment-list">
            <div v-for="payment in paymentHistory" :key="payment.id" class="payment-item">
              <div class="payment-info">
                <div class="payment-amount">{{ formatCurrency(payment.amount) }}</div>
                <div class="payment-details">
                  <span class="payment-date">{{ formatDateTime(payment.created_at) }}</span>
                  <span class="payment-gateway">via {{ payment.gateway?.name || 'Unknown Gateway' }}</span>
                </div>
              </div>
              <div class="payment-status">
                <span class="status-badge" :class="`status-${payment.status}`">
                  {{ formatStatus(payment.status) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- SMS History Tab -->
        <div v-if="activeTab === 'sms'" class="tab-panel">
          <div class="section-header">
            <h3 class="section-title">SMS Communication History</h3>
            <button class="refresh-btn" @click="$emit('refresh-sms')" :disabled="loadingSms">
              <svg class="refresh-icon" :class="{ 'animate-spin': loadingSms }" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          </div>

          <div v-if="loadingSms" class="loading-state">
            <div class="loading-spinner"></div>
            <span>Loading SMS history...</span>
          </div>

          <div v-else-if="smsHistory.length === 0" class="empty-state">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <p>No SMS communication history</p>
          </div>

          <div v-else class="sms-list">
            <div v-for="sms in smsHistory" :key="sms.id" class="sms-item">
              <div class="sms-content">
                <div class="sms-message">{{ sms.message }}</div>
                <div class="sms-details">
                  <span class="sms-date">{{ formatDateTime(sms.created_at) }}</span>
                  <span v-if="sms.cost" class="sms-cost">Cost: {{ formatCurrency(sms.cost) }}</span>
                </div>
              </div>
              <div class="sms-status">
                <span class="status-badge" :class="`status-${sms.status}`">
                  {{ formatStatus(sms.status) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Record Payment Modal -->
    <Modal
      v-if="showRecordPaymentModal"
      :show="showRecordPaymentModal"
      @close="showRecordPaymentModal = false"
      title="Record Payment"
    >
      <PaymentForm
        :customer="customer"
        @save="handleRecordPayment"
        @cancel="showRecordPaymentModal = false"
      />
    </Modal>

    <!-- Send SMS Modal -->
    <Modal
      v-if="showSendSmsModal"
      :show="showSendSmsModal"
      @close="showSendSmsModal = false"
      title="Send SMS"
    >
      <SmsForm
        :customer="customer"
        @send="handleSendSms"
        @cancel="showSendSmsModal = false"
      />
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Modal from '@/components/common/Modal.vue'
import PaymentForm from './PaymentForm.vue'
import SmsForm from './SmsForm.vue'
import type { Customer, Payment, SmsLog } from '@/types'

// Props
interface Props {
  customer: Customer
  paymentHistory: Payment[]
  smsHistory: SmsLog[]
  loadingPayments: boolean
  loadingSms: boolean
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  'record-payment': [paymentData: any]
  'suspend-service': [customerId: string]
  'activate-service': [customerId: string]
  'send-sms': [smsData: any]
  'refresh-payments': []
  'refresh-sms': []
}>()

// Reactive state
const activeTab = ref('overview')
const showRecordPaymentModal = ref(false)
const showSendSmsModal = ref(false)

// Tab configuration
const tabs = [
  {
    key: 'overview',
    label: 'Overview',
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
  },
  {
    key: 'payments',
    label: 'Payment History',
    icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'
  },
  {
    key: 'sms',
    label: 'SMS History',
    icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'
  }
]

// Computed properties
const totalSpent = computed(() => {
  return props.paymentHistory
    .filter(payment => payment.status === 'completed')
    .reduce((total, payment) => total + payment.amount, 0)
})

const lastPayment = computed(() => {
  return props.paymentHistory.length > 0 ? props.paymentHistory[0] : null
})

// Methods
const handleRecordPayment = (paymentData: any) => {
  emit('record-payment', paymentData)
  showRecordPaymentModal.value = false
}

const handleSendSms = (smsData: any) => {
  emit('send-sms', smsData)
  showSendSmsModal.value = false
}

const formatStatus = (status: string | null | undefined) => {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}
</script>

<style scoped>
.customer-detail-view {
  max-width: 1200px;
  margin: 0 auto;
}

.customer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 24px;
}

.customer-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.customer-avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #3b82f6;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  font-weight: 600;
}

.customer-name {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px;
}

.customer-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  color: #6b7280;
}

.meta-icon {
  width: 16px;
  height: 16px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-suspended {
  background: #fef3c7;
  color: #92400e;
}

.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.status-sent {
  background: #dbeafe;
  color: #1e40af;
}

.status-delivered {
  background: #dcfce7;
  color: #166534;
}

.quick-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn.primary {
  background: #3b82f6;
  color: white;
}

.action-btn.primary:hover {
  background: #2563eb;
}

.action-btn.secondary {
  background: #6b7280;
  color: white;
}

.action-btn.secondary:hover {
  background: #4b5563;
}

.action-btn.success {
  background: #10b981;
  color: white;
}

.action-btn.success:hover {
  background: #059669;
}

.action-btn.warning {
  background: #f59e0b;
  color: white;
}

.action-btn.warning:hover {
  background: #d97706;
}

.action-icon {
  width: 16px;
  height: 16px;
}

.detail-tabs {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.tab-nav {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
}

.tab-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 24px;
  border: none;
  background: none;
  color: #6b7280;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border-bottom: 2px solid transparent;
}

.tab-button:hover {
  color: #374151;
  background: #f9fafb;
}

.tab-button.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-icon {
  width: 16px;
  height: 16px;
}

.tab-content {
  padding: 24px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.info-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 20px;
}

.card-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.info-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.info-value {
  font-size: 0.875rem;
  color: #111827;
}

.text-muted {
  color: #9ca3af;
  font-style: italic;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.refresh-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.refresh-btn:hover {
  background: #f9fafb;
  border-color: #9ca3af;
}

.refresh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.refresh-icon {
  width: 16px;
  height: 16px;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #6b7280;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 12px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin-bottom: 12px;
}

.payment-list,
.sms-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payment-item,
.sms-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.payment-info,
.sms-content {
  flex: 1;
}

.payment-amount {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.payment-details,
.sms-details {
  display: flex;
  gap: 12px;
  font-size: 0.75rem;
  color: #6b7280;
}

.sms-message {
  font-size: 0.875rem;
  color: #111827;
  margin-bottom: 4px;
  line-height: 1.4;
}

.payment-status,
.sms-status {
  flex-shrink: 0;
}
</style>