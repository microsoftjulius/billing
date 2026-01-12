<template>
  <form @submit.prevent="handleSubmit" class="payment-form">
    <div class="form-content">
      <!-- Customer Info -->
      <div class="customer-info">
        <h3 class="info-title">Recording payment for:</h3>
        <div class="customer-details">
          <span class="customer-name">{{ customer.name }}</span>
          <span class="customer-phone">{{ customer.phone }}</span>
        </div>
      </div>

      <!-- Payment Details -->
      <div class="form-section">
        <div class="form-group">
          <label for="amount" class="form-label">Amount *</label>
          <div class="amount-input-wrapper">
            <span class="currency-symbol">UGX</span>
            <input
              id="amount"
              v-model.number="form.amount"
              type="number"
              step="100"
              min="100"
              class="form-input amount-input"
              :class="{ 'error': errors.amount }"
              placeholder="0"
              required
            />
          </div>
          <span v-if="errors.amount" class="error-message">{{ errors.amount }}</span>
        </div>

        <div class="form-group">
          <label for="gateway_id" class="form-label">Payment Gateway *</label>
          <select
            id="gateway_id"
            v-model="form.gateway_id"
            class="form-select"
            :class="{ 'error': errors.gateway_id }"
            required
          >
            <option value="">Select payment gateway</option>
            <option
              v-for="gateway in paymentGateways"
              :key="gateway.id"
              :value="gateway.id"
            >
              {{ gateway.name }} ({{ gateway.provider }})
            </option>
          </select>
          <span v-if="errors.gateway_id" class="error-message">{{ errors.gateway_id }}</span>
        </div>

        <div class="form-group">
          <label for="voucher_id" class="form-label">Link to Voucher</label>
          <select
            id="voucher_id"
            v-model="form.voucher_id"
            class="form-select"
          >
            <option value="">No voucher (manual payment)</option>
            <option
              v-for="voucher in availableVouchers"
              :key="voucher.id"
              :value="voucher.id"
            >
              {{ voucher.code }} - {{ formatCurrency(voucher.amount) }} ({{ voucher.duration_hours }}h)
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="gateway_reference" class="form-label">Transaction Reference</label>
          <input
            id="gateway_reference"
            v-model="form.gateway_reference"
            type="text"
            class="form-input"
            placeholder="Enter transaction reference (optional)"
          />
          <small class="form-help">Reference number from the payment gateway</small>
        </div>

        <div class="form-group">
          <label for="notes" class="form-label">Notes</label>
          <textarea
            id="notes"
            v-model="form.notes"
            class="form-textarea"
            rows="3"
            placeholder="Add any additional notes about this payment..."
          ></textarea>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input
              v-model="form.mark_as_completed"
              type="checkbox"
              class="form-checkbox"
            />
            <span class="checkbox-text">Mark as completed immediately</span>
          </label>
          <small class="form-help">
            If unchecked, payment will be marked as pending and require manual confirmation
          </small>
        </div>
      </div>

      <!-- Payment Summary -->
      <div v-if="form.amount > 0" class="payment-summary">
        <h4 class="summary-title">Payment Summary</h4>
        <div class="summary-details">
          <div class="summary-row">
            <span class="summary-label">Amount:</span>
            <span class="summary-value">{{ formatCurrency(form.amount) }}</span>
          </div>
          <div v-if="selectedVoucher" class="summary-row">
            <span class="summary-label">Voucher:</span>
            <span class="summary-value">{{ selectedVoucher.code }}</span>
          </div>
          <div v-if="selectedGateway" class="summary-row">
            <span class="summary-label">Gateway:</span>
            <span class="summary-value">{{ selectedGateway.name }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Status:</span>
            <span class="summary-value">
              <span class="status-badge" :class="form.mark_as_completed ? 'status-completed' : 'status-pending'">
                {{ form.mark_as_completed ? 'Completed' : 'Pending' }}
              </span>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions">
      <button
        type="button"
        class="btn btn-secondary"
        @click="$emit('cancel')"
        :disabled="loading"
      >
        Cancel
      </button>
      
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="loading || !isFormValid"
      >
        <svg v-if="loading" class="btn-icon animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <svg v-else class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
        </svg>
        Record Payment
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/api/index'
import type { Customer } from '@/types'

// Props
interface Props {
  customer: Customer
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  save: [paymentData: any]
  cancel: []
}>()

// Reactive state
const loading = ref(false)
const paymentGateways = ref([])
const availableVouchers = ref([])
const errors = ref<Record<string, string>>({})

const form = ref({
  amount: null as number | null,
  gateway_id: '',
  voucher_id: '',
  gateway_reference: '',
  notes: '',
  mark_as_completed: true
})

// Computed properties
const selectedGateway = computed(() => {
  return paymentGateways.value.find(gateway => gateway.id === form.value.gateway_id)
})

const selectedVoucher = computed(() => {
  return availableVouchers.value.find(voucher => voucher.id === form.value.voucher_id)
})

const isFormValid = computed(() => {
  return form.value.amount && 
         form.value.amount > 0 && 
         form.value.gateway_id &&
         Object.keys(errors.value).length === 0
})

// Methods
const validateForm = () => {
  errors.value = {}

  // Amount validation
  if (!form.value.amount || form.value.amount <= 0) {
    errors.value.amount = 'Amount must be greater than 0'
  } else if (form.value.amount < 100) {
    errors.value.amount = 'Minimum amount is UGX 100'
  }

  // Gateway validation
  if (!form.value.gateway_id) {
    errors.value.gateway_id = 'Please select a payment gateway'
  }

  return Object.keys(errors.value).length === 0
}

const handleSubmit = () => {
  if (!validateForm()) {
    return
  }

  const paymentData = {
    customer_id: props.customer.id,
    amount: form.value.amount,
    currency: 'UGX',
    gateway_id: form.value.gateway_id,
    voucher_id: form.value.voucher_id || undefined,
    gateway_reference: form.value.gateway_reference || undefined,
    notes: form.value.notes || undefined,
    status: form.value.mark_as_completed ? 'completed' : 'pending'
  }

  emit('save', paymentData)
}

const loadPaymentGateways = async () => {
  try {
    const response = await api.get('/payment-gateways?active=true')
    paymentGateways.value = response.data.data
  } catch (error) {
    console.error('Failed to load payment gateways:', error)
  }
}

const loadAvailableVouchers = async () => {
  try {
    const response = await api.get(`/customers/${props.customer.id}/vouchers?status=unused`)
    availableVouchers.value = response.data.data
  } catch (error) {
    console.error('Failed to load vouchers:', error)
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(amount)
}

// Watch for form changes
watch(form, validateForm, { deep: true })

// Load data on mount
onMounted(async () => {
  await Promise.all([
    loadPaymentGateways(),
    loadAvailableVouchers()
  ])
})
</script>

<style scoped>
.payment-form {
  max-width: 500px;
  margin: 0 auto;
}

.form-content {
  margin-bottom: 24px;
}

.customer-info {
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 24px;
}

.info-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
  margin: 0 0 8px;
}

.customer-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.customer-name {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.customer-phone {
  font-size: 0.875rem;
  color: #6b7280;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.amount-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.currency-symbol {
  position: absolute;
  left: 12px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
  z-index: 1;
}

.amount-input {
  padding-left: 48px !important;
}

.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  transition: all 0.2s;
  background: white;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error,
.form-select.error {
  border-color: #ef4444;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
}

.form-checkbox {
  margin-top: 2px;
}

.checkbox-text {
  font-size: 0.875rem;
  color: #374151;
}

.form-help {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 4px;
}

.error-message {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 4px;
}

.payment-summary {
  padding: 16px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  margin-top: 24px;
}

.summary-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px;
}

.summary-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.summary-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: #111827;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #4b5563;
}

.btn-icon {
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
</style>