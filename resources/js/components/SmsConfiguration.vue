<template>
  <div class="sms-configuration">
    <div class="config-header">
      <h2 class="config-title">SMS Configuration</h2>
      <p class="config-description">
        Configure SMS settings for sending notifications and voucher delivery
      </p>
    </div>

    <!-- Configuration Status -->
    <div class="status-card" :class="statusCardClass">
      <div class="status-content">
        <div class="status-icon">
          <svg v-if="configStatus.configured && configStatus.enabled" class="icon-success" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <svg v-else-if="configStatus.configured && !configStatus.enabled" class="icon-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <svg v-else class="icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="status-text">
          <h3 class="status-title">{{ statusTitle }}</h3>
          <p class="status-message">{{ statusMessage }}</p>
        </div>
      </div>
      
      <div v-if="configStatus.last_test_result" class="test-result">
        <span class="test-label">Last Test:</span>
        <span :class="testResultClass">{{ configStatus.last_test_result.message }}</span>
      </div>
    </div>

    <!-- Configuration Form -->
    <form @submit.prevent="saveConfiguration" class="config-form">
      <div class="form-section">
        <h3 class="section-title">Basic Settings</h3>
        
        <div class="form-group">
          <label class="form-label">
            <input
              v-model="form.enabled"
              type="checkbox"
              class="form-checkbox"
            />
            <span class="checkbox-text">Enable SMS Service</span>
          </label>
          <p class="form-help">Enable or disable SMS notifications system-wide</p>
        </div>

        <div v-if="form.enabled" class="enabled-settings">
          <div class="form-group">
            <label for="api_key" class="form-label">API Key *</label>
            <div class="input-group">
              <input
                id="api_key"
                v-model="form.api_key"
                :type="showApiKey ? 'text' : 'password'"
                class="form-input"
                :class="{ 'error': errors.api_key }"
                placeholder="Enter your UGSMS API key"
                required
              />
              <button
                type="button"
                class="input-addon"
                @click="showApiKey = !showApiKey"
              >
                <svg v-if="showApiKey" class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                </svg>
                <svg v-else class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
            <span v-if="errors.api_key" class="error-message">{{ errors.api_key }}</span>
            <p class="form-help">Your UGSMS API key for authentication</p>
          </div>

          <div class="form-group">
            <label for="base_url" class="form-label">Base URL *</label>
            <input
              id="base_url"
              v-model="form.base_url"
              type="url"
              class="form-input"
              :class="{ 'error': errors.base_url }"
              placeholder="https://api.ugsms.com"
              required
            />
            <span v-if="errors.base_url" class="error-message">{{ errors.base_url }}</span>
            <p class="form-help">UGSMS API base URL (must use HTTPS)</p>
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
            <p class="form-help">The name that appears as the SMS sender (max 11 characters)</p>
          </div>
        </div>
      </div>

      <div v-if="form.enabled" class="form-section">
        <h3 class="section-title">Advanced Settings</h3>
        
        <div class="form-row">
          <div class="form-group">
            <label for="retry_attempts" class="form-label">Retry Attempts</label>
            <input
              id="retry_attempts"
              v-model.number="form.retry_attempts"
              type="number"
              min="1"
              max="5"
              class="form-input"
              :class="{ 'error': errors.retry_attempts }"
            />
            <span v-if="errors.retry_attempts" class="error-message">{{ errors.retry_attempts }}</span>
            <p class="form-help">Number of retry attempts for failed SMS (1-5)</p>
          </div>

          <div class="form-group">
            <label for="timeout" class="form-label">Timeout (seconds)</label>
            <input
              id="timeout"
              v-model.number="form.timeout"
              type="number"
              min="5"
              max="60"
              class="form-input"
              :class="{ 'error': errors.timeout }"
            />
            <span v-if="errors.timeout" class="error-message">{{ errors.timeout }}</span>
            <p class="form-help">Request timeout in seconds (5-60)</p>
          </div>
        </div>

        <div class="form-group">
          <label for="low_balance_threshold" class="form-label">Low Balance Threshold (UGX)</label>
          <input
            id="low_balance_threshold"
            v-model.number="form.low_balance_threshold"
            type="number"
            min="0"
            step="100"
            class="form-input"
            :class="{ 'error': errors.low_balance_threshold }"
          />
          <span v-if="errors.low_balance_threshold" class="error-message">{{ errors.low_balance_threshold }}</span>
          <p class="form-help">Alert when SMS balance falls below this amount</p>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button
          type="button"
          class="btn btn-secondary"
          @click="testConfiguration"
          :disabled="loading || !form.enabled"
        >
          <svg v-if="testing" class="btn-icon animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <svg v-else class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Test Connection
        </button>

        <button
          type="submit"
          class="btn btn-primary"
          :disabled="loading"
        >
          <svg v-if="loading" class="btn-icon animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <svg v-else class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Save Configuration
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/api'

// Reactive state
const loading = ref(false)
const testing = ref(false)
const showApiKey = ref(false)
const configStatus = ref({
  configured: false,
  enabled: false,
  provider: 'ugsms',
  api_key_set: false,
  api_key_masked: null,
  last_test_result: null
})

const form = ref({
  enabled: false,
  api_key: '',
  base_url: 'https://api.ugsms.com',
  sender_id: 'BILLING',
  retry_attempts: 2,
  timeout: 15,
  low_balance_threshold: 5000
})

const errors = ref<Record<string, string>>({})

const availableSenderIds = ref([
  'BILLING',
  'SUPPORT', 
  'ALERTS',
  'VOUCHER',
  'PAYMENT',
  'SYSTEM'
])

// Computed properties
const statusCardClass = computed(() => {
  if (configStatus.value.configured && configStatus.value.enabled) {
    return 'status-success'
  } else if (configStatus.value.configured && !configStatus.value.enabled) {
    return 'status-warning'
  } else {
    return 'status-error'
  }
})

const statusTitle = computed(() => {
  if (configStatus.value.configured && configStatus.value.enabled) {
    return 'SMS Service Active'
  } else if (configStatus.value.configured && !configStatus.value.enabled) {
    return 'SMS Service Disabled'
  } else {
    return 'SMS Service Not Configured'
  }
})

const statusMessage = computed(() => {
  if (configStatus.value.configured && configStatus.value.enabled) {
    return 'SMS service is properly configured and enabled'
  } else if (configStatus.value.configured && !configStatus.value.enabled) {
    return 'SMS service is configured but currently disabled'
  } else {
    return 'SMS service requires configuration before use'
  }
})

const testResultClass = computed(() => {
  if (!configStatus.value.last_test_result) return ''
  return configStatus.value.last_test_result.success ? 'test-success' : 'test-error'
})

// Methods
const loadConfiguration = async () => {
  try {
    loading.value = true
    const response = await api.get('/sms/configuration')
    
    if (response.data.success) {
      configStatus.value = response.data.data
      
      // Populate form with current configuration
      form.value = {
        enabled: configStatus.value.enabled,
        api_key: configStatus.value.api_key_masked || '',
        base_url: configStatus.value.base_url || 'https://api.ugsms.com',
        sender_id: configStatus.value.sender_id || 'BILLING',
        retry_attempts: 2,
        timeout: 15,
        low_balance_threshold: 5000
      }
    }
  } catch (error) {
    console.error('Failed to load SMS configuration:', error)
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  errors.value = {}

  if (form.value.enabled) {
    if (!form.value.api_key) {
      errors.value.api_key = 'API key is required when SMS is enabled'
    } else if (form.value.api_key.length < 10) {
      errors.value.api_key = 'API key must be at least 10 characters long'
    }

    if (!form.value.base_url) {
      errors.value.base_url = 'Base URL is required when SMS is enabled'
    } else if (!form.value.base_url.startsWith('https://')) {
      errors.value.base_url = 'Base URL must use HTTPS'
    }

    if (form.value.retry_attempts < 1 || form.value.retry_attempts > 5) {
      errors.value.retry_attempts = 'Retry attempts must be between 1 and 5'
    }

    if (form.value.timeout < 5 || form.value.timeout > 60) {
      errors.value.timeout = 'Timeout must be between 5 and 60 seconds'
    }

    if (form.value.low_balance_threshold < 0) {
      errors.value.low_balance_threshold = 'Low balance threshold cannot be negative'
    }
  }

  return Object.keys(errors.value).length === 0
}

const saveConfiguration = async () => {
  if (!validateForm()) {
    return
  }

  try {
    loading.value = true
    
    const response = await api.put('/sms/configuration', form.value)
    
    if (response.data.success) {
      // Reload configuration to get updated status
      await loadConfiguration()
      
      // Show success notification
      // In a real app, you'd use a notification system
      alert('SMS configuration saved successfully!')
    } else {
      throw new Error(response.data.message || 'Failed to save configuration')
    }
  } catch (error) {
    console.error('Failed to save SMS configuration:', error)
    alert('Failed to save SMS configuration: ' + (error.response?.data?.message || error.message))
  } finally {
    loading.value = false
  }
}

const testConfiguration = async () => {
  try {
    testing.value = true
    
    const response = await api.post('/sms/test-configuration')
    
    if (response.data.success) {
      alert('SMS configuration test successful!')
    } else {
      alert('SMS configuration test failed: ' + response.data.message)
    }
    
    // Reload configuration to get updated test results
    await loadConfiguration()
    
  } catch (error) {
    console.error('Failed to test SMS configuration:', error)
    alert('SMS configuration test failed: ' + (error.response?.data?.message || error.message))
  } finally {
    testing.value = false
  }
}

// Load configuration on mount
onMounted(() => {
  loadConfiguration()
})
</script>

<style scoped>
.sms-configuration {
  max-width: 800px;
  margin: 0 auto;
  padding: 24px;
}

.config-header {
  margin-bottom: 32px;
}

.config-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 8px;
}

.config-description {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

.status-card {
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 32px;
  border: 1px solid;
}

.status-card.status-success {
  background: #f0f9ff;
  border-color: #0ea5e9;
  color: #0c4a6e;
}

.status-card.status-warning {
  background: #fffbeb;
  border-color: #f59e0b;
  color: #92400e;
}

.status-card.status-error {
  background: #fef2f2;
  border-color: #ef4444;
  color: #991b1b;
}

.status-content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.status-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
}

.icon-success {
  color: #059669;
}

.icon-warning {
  color: #d97706;
}

.icon-error {
  color: #dc2626;
}

.status-text {
  flex: 1;
}

.status-title {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0 0 4px;
}

.status-message {
  font-size: 0.875rem;
  margin: 0;
  opacity: 0.8;
}

.test-result {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid currentColor;
  opacity: 0.6;
  font-size: 0.875rem;
}

.test-label {
  font-weight: 500;
}

.test-success {
  color: #059669;
}

.test-error {
  color: #dc2626;
}

.config-form {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.form-section {
  padding: 24px;
  border-bottom: 1px solid #e5e7eb;
}

.form-section:last-child {
  border-bottom: none;
}

.section-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  transition: all 0.2s;
  background: white;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error {
  border-color: #ef4444;
}

.input-group {
  position: relative;
  display: flex;
}

.input-addon {
  position: absolute;
  right: 0;
  top: 0;
  bottom: 0;
  padding: 0 12px;
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.input-addon:hover {
  color: #374151;
}

.form-checkbox {
  width: auto;
  margin-right: 8px;
}

.checkbox-text {
  font-weight: 500;
}

.enabled-settings {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-help {
  font-size: 0.75rem;
  color: #6b7280;
  margin: 4px 0 0;
}

.error-message {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 4px;
  display: block;
}

.form-actions {
  padding: 24px;
  background: #f9fafb;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
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

.icon {
  width: 20px;
  height: 20px;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>