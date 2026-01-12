<template>
  <form @submit.prevent="handleSubmit" class="sms-form">
    <div class="form-content">
      <!-- Customer Info -->
      <div class="customer-info">
        <h3 class="info-title">Sending SMS to:</h3>
        <div class="customer-details">
          <span class="customer-name">{{ customer.name }}</span>
          <span class="customer-phone">{{ customer.phone }}</span>
        </div>
      </div>

      <!-- SMS Details -->
      <div class="form-section">
        <div class="form-group">
          <label for="template" class="form-label">Message Template</label>
          <select
            id="template"
            v-model="selectedTemplate"
            class="form-select"
            @change="applyTemplate"
          >
            <option value="">Custom message</option>
            <option
              v-for="template in smsTemplates"
              :key="template.id"
              :value="template"
            >
              {{ template.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="message" class="form-label">Message *</label>
          <textarea
            id="message"
            v-model="form.message"
            class="form-textarea"
            :class="{ 'error': errors.message }"
            rows="4"
            maxlength="160"
            placeholder="Enter your SMS message..."
            required
            @input="updateCharacterCount"
          ></textarea>
          <div class="message-info">
            <span v-if="errors.message" class="error-message">{{ errors.message }}</span>
            <span class="character-count" :class="{ 'warning': characterCount > 140, 'error': characterCount > 160 }">
              {{ characterCount }}/160 characters
            </span>
          </div>
        </div>

        <div class="form-group">
          <label for="sender_id" class="form-label">Sender ID</label>
          <select
            id="sender_id"
            v-model="form.sender_id"
            class="form-select"
          >
            <option value="">Use default sender ID</option>
            <option
              v-for="senderId in availableSenderIds"
              :key="senderId"
              :value="senderId"
            >
              {{ senderId }}
            </option>
          </select>
          <small class="form-help">The name that will appear as the sender</small>
        </div>

        <div class="form-group">
          <label for="priority" class="form-label">Priority</label>
          <select
            id="priority"
            v-model="form.priority"
            class="form-select"
          >
            <option value="normal">Normal</option>
            <option value="high">High Priority</option>
          </select>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input
              v-model="form.schedule_later"
              type="checkbox"
              class="form-checkbox"
              @change="toggleSchedule"
            />
            <span class="checkbox-text">Schedule for later</span>
          </label>
        </div>

        <div v-if="form.schedule_later" class="form-group">
          <label for="scheduled_at" class="form-label">Send Date & Time *</label>
          <input
            id="scheduled_at"
            v-model="form.scheduled_at"
            type="datetime-local"
            class="form-input"
            :class="{ 'error': errors.scheduled_at }"
            :min="minDateTime"
          />
          <span v-if="errors.scheduled_at" class="error-message">{{ errors.scheduled_at }}</span>
        </div>
      </div>

      <!-- SMS Preview -->
      <div v-if="form.message" class="sms-preview">
        <h4 class="preview-title">SMS Preview</h4>
        <div class="preview-phone">
          <div class="phone-header">
            <span class="sender-name">{{ form.sender_id || 'Default' }}</span>
            <span class="timestamp">{{ previewTimestamp }}</span>
          </div>
          <div class="phone-message">{{ form.message }}</div>
        </div>
        <div class="preview-info">
          <div class="info-item">
            <span class="info-label">Estimated Cost:</span>
            <span class="info-value">{{ estimatedCost }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Message Parts:</span>
            <span class="info-value">{{ messageParts }}</span>
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
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        {{ form.schedule_later ? 'Schedule SMS' : 'Send SMS' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/api/client'
import type { Customer } from '@/types'

// Props
interface Props {
  customer: Customer
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  send: [smsData: any]
  cancel: []
}>()

// Reactive state
const loading = ref(false)
const smsTemplates = ref([])
const availableSenderIds = ref(['BILLING', 'SUPPORT', 'ALERTS'])
const selectedTemplate = ref(null)
const characterCount = ref(0)
const errors = ref<Record<string, string>>({})

const form = ref({
  message: '',
  sender_id: '',
  priority: 'normal',
  schedule_later: false,
  scheduled_at: ''
})

// Computed properties
const isFormValid = computed(() => {
  return form.value.message.trim() !== '' && 
         form.value.message.length <= 160 &&
         (!form.value.schedule_later || form.value.scheduled_at) &&
         Object.keys(errors.value).length === 0
})

const minDateTime = computed(() => {
  const now = new Date()
  now.setMinutes(now.getMinutes() + 5) // Minimum 5 minutes from now
  return now.toISOString().slice(0, 16)
})

const previewTimestamp = computed(() => {
  if (form.value.schedule_later && form.value.scheduled_at) {
    return new Date(form.value.scheduled_at).toLocaleTimeString([], { 
      hour: '2-digit', 
      minute: '2-digit' 
    })
  }
  return new Date().toLocaleTimeString([], { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
})

const messageParts = computed(() => {
  return Math.ceil(form.value.message.length / 160)
})

const estimatedCost = computed(() => {
  const costPerSms = 50 // UGX per SMS
  const totalCost = messageParts.value * costPerSms
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(totalCost)
})

// Methods
const validateForm = () => {
  errors.value = {}

  // Message validation
  if (!form.value.message.trim()) {
    errors.value.message = 'Message is required'
  } else if (form.value.message.length > 160) {
    errors.value.message = 'Message cannot exceed 160 characters'
  }

  // Scheduled time validation
  if (form.value.schedule_later) {
    if (!form.value.scheduled_at) {
      errors.value.scheduled_at = 'Please select a date and time'
    } else {
      const scheduledTime = new Date(form.value.scheduled_at)
      const minTime = new Date()
      minTime.setMinutes(minTime.getMinutes() + 5)
      
      if (scheduledTime <= minTime) {
        errors.value.scheduled_at = 'Scheduled time must be at least 5 minutes from now'
      }
    }
  }

  return Object.keys(errors.value).length === 0
}

const updateCharacterCount = () => {
  characterCount.value = form.value.message.length
}

const toggleSchedule = () => {
  if (!form.value.schedule_later) {
    form.value.scheduled_at = ''
  }
}

const applyTemplate = () => {
  if (selectedTemplate.value) {
    form.value.message = selectedTemplate.value.content
    updateCharacterCount()
  }
}

const handleSubmit = () => {
  if (!validateForm()) {
    return
  }

  const smsData = {
    customer_id: props.customer.id,
    phone_number: props.customer.phone,
    message: form.value.message.trim(),
    sender_id: form.value.sender_id || undefined,
    priority: form.value.priority,
    scheduled_at: form.value.schedule_later ? form.value.scheduled_at : undefined
  }

  emit('send', smsData)
}

const loadSmsTemplates = async () => {
  try {
    const response = await api.get('/sms/templates')
    smsTemplates.value = response.data.data
  } catch (error) {
    console.error('Failed to load SMS templates:', error)
    // Set default templates if API fails
    smsTemplates.value = [
      {
        id: 1,
        name: 'Payment Reminder',
        content: 'Dear {{customer_name}}, your payment is due. Please make payment to continue service.'
      },
      {
        id: 2,
        name: 'Service Activation',
        content: 'Hello {{customer_name}}, your service has been activated. Thank you for your payment!'
      },
      {
        id: 3,
        name: 'Service Suspension',
        content: 'Dear {{customer_name}}, your service has been suspended due to non-payment. Please contact support.'
      }
    ]
  }
}

const processTemplateVariables = (content: string) => {
  return content
    .replace(/\{\{customer_name\}\}/g, props.customer.name)
    .replace(/\{\{phone\}\}/g, props.customer.phone)
}

// Watch for form changes
watch(form, validateForm, { deep: true })

// Watch for template selection
watch(selectedTemplate, (template) => {
  if (template) {
    form.value.message = processTemplateVariables(template.content)
    updateCharacterCount()
  }
})

// Load data on mount
onMounted(async () => {
  await loadSmsTemplates()
  updateCharacterCount()
})
</script>

<style scoped>
.sms-form {
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
.form-textarea.error {
  border-color: #ef4444;
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
  font-family: inherit;
}

.message-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.character-count {
  font-size: 0.75rem;
  color: #6b7280;
}

.character-count.warning {
  color: #f59e0b;
}

.character-count.error {
  color: #ef4444;
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
}

.sms-preview {
  padding: 16px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  margin-top: 24px;
}

.preview-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px;
}

.preview-phone {
  background: white;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.phone-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.sender-name {
  font-size: 0.75rem;
  font-weight: 600;
  color: #3b82f6;
}

.timestamp {
  font-size: 0.75rem;
  color: #6b7280;
}

.phone-message {
  font-size: 0.875rem;
  color: #111827;
  line-height: 1.4;
  word-wrap: break-word;
}

.preview-info {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 0.75rem;
  color: #6b7280;
}

.info-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: #111827;
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