<template>
  <Modal 
    :show="show"
    @close="$emit('close')"
    title="Add New Router"
    size="lg"
  >
    <form @submit.prevent="submitForm" class="router-form">
      <div class="form-sections">
        <!-- Basic Information -->
        <div class="form-section">
          <h3 class="section-title">Basic Information</h3>
          <div class="form-grid">
            <div class="form-group">
              <label for="name" class="form-label">Router Name *</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="form-input"
                placeholder="Enter router name"
                required
              />
            </div>
            <div class="form-group">
              <label for="ip_address" class="form-label">IP Address *</label>
              <input
                id="ip_address"
                v-model="form.ip_address"
                type="text"
                class="form-input"
                placeholder="192.168.1.1"
                required
              />
            </div>
            <div class="form-group">
              <label for="port" class="form-label">Port</label>
              <input
                id="port"
                v-model.number="form.port"
                type="number"
                class="form-input"
                placeholder="8728"
                min="1"
                max="65535"
              />
            </div>
            <div class="form-group">
              <label for="location" class="form-label">Location</label>
              <input
                id="location"
                v-model="form.location"
                type="text"
                class="form-input"
                placeholder="Enter location"
              />
            </div>
          </div>
        </div>

        <!-- Authentication -->
        <div class="form-section">
          <h3 class="section-title">Authentication</h3>
          <div class="form-grid">
            <div class="form-group">
              <label for="username" class="form-label">Username *</label>
              <input
                id="username"
                v-model="form.username"
                type="text"
                class="form-input"
                placeholder="admin"
                required
              />
            </div>
            <div class="form-group">
              <label for="password" class="form-label">Password *</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                class="form-input"
                placeholder="Enter password"
                required
              />
            </div>
          </div>
        </div>

        <!-- Connection Test -->
        <div class="form-section">
          <h3 class="section-title">Connection Test</h3>
          <div class="test-connection">
            <button
              type="button"
              @click="testConnection"
              :disabled="isTestingConnection || !canTestConnection"
              class="btn btn-outline test-btn"
            >
              <i v-if="isTestingConnection" class="icon-loader spinning"></i>
              <i v-else class="icon-wifi"></i>
              {{ isTestingConnection ? 'Testing...' : 'Test Connection' }}
            </button>
            
            <div v-if="connectionTestResult" class="test-result" :class="connectionTestResult.success ? 'success' : 'error'">
              <i :class="connectionTestResult.success ? 'icon-check-circle' : 'icon-x-circle'"></i>
              <span>{{ connectionTestResult.message }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button
          type="button"
          @click="$emit('close')"
          class="btn btn-secondary"
          :disabled="isSubmitting"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="isSubmitting || !isFormValid"
        >
          <i v-if="isSubmitting" class="icon-loader spinning"></i>
          {{ isSubmitting ? 'Adding...' : 'Add Router' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useAppStore } from '@/store/modules/app'
import Modal from '@/components/common/Modal.vue'
import api from '@/api/client'

interface Props {
  show: boolean
}

interface RouterForm {
  name: string
  ip_address: string
  port: number
  location: string
  username: string
  password: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  success: [router: any]
}>()

const appStore = useAppStore()

// Form state
const form = ref<RouterForm>({
  name: '',
  ip_address: '',
  port: 8728,
  location: '',
  username: 'admin',
  password: ''
})

const isSubmitting = ref(false)
const isTestingConnection = ref(false)
const connectionTestResult = ref<{ success: boolean; message: string } | null>(null)

// Computed properties
const isFormValid = computed(() => {
  return form.value.name.trim() !== '' &&
         form.value.ip_address.trim() !== '' &&
         form.value.username.trim() !== '' &&
         form.value.password.trim() !== ''
})

const canTestConnection = computed(() => {
  return form.value.ip_address.trim() !== '' &&
         form.value.username.trim() !== '' &&
         form.value.password.trim() !== ''
})

// Methods
const resetForm = () => {
  form.value = {
    name: '',
    ip_address: '',
    port: 8728,
    location: '',
    username: 'admin',
    password: ''
  }
  connectionTestResult.value = null
}

const testConnection = async () => {
  if (!canTestConnection.value) return

  isTestingConnection.value = true
  connectionTestResult.value = null

  try {
    const response = await api.post('/api/v1/router-management/test-connection', {
      ip_address: form.value.ip_address,
      port: form.value.port,
      username: form.value.username,
      password: form.value.password
    })

    if (response.data.success) {
      connectionTestResult.value = {
        success: true,
        message: 'Connection successful!'
      }
    } else {
      connectionTestResult.value = {
        success: false,
        message: response.data.message || 'Connection failed'
      }
    }
  } catch (error: any) {
    connectionTestResult.value = {
      success: false,
      message: error.response?.data?.message || 'Connection test failed'
    }
  } finally {
    isTestingConnection.value = false
  }
}

const submitForm = async () => {
  if (!isFormValid.value) return

  isSubmitting.value = true

  try {
    const response = await api.post('/api/v1/router-management', form.value)

    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'Router added successfully!'
      })

      emit('success', response.data.data)
      emit('close')
      resetForm()
    } else {
      throw new Error(response.data.message || 'Failed to add router')
    }
  } catch (error: any) {
    console.error('Failed to add router:', error)
    appStore.addNotification({
      type: 'error',
      message: error.response?.data?.message || 'Failed to add router'
    })
  } finally {
    isSubmitting.value = false
  }
}

// Watch for modal close to reset form
watch(() => props.show, (newValue) => {
  if (!newValue) {
    resetForm()
  }
})
</script>

<style scoped>
.router-form {
  max-height: 70vh;
  overflow-y: auto;
}

.form-sections {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-section {
  background: var(--bg-secondary, #1f2937);
  border: 1px solid var(--border-color, #374151);
  border-radius: 8px;
  padding: 20px;
}

.section-title {
  margin: 0 0 16px 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary, #f9fafb);
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-weight: 500;
  color: var(--text-primary, #f9fafb);
  font-size: 0.875rem;
}

.form-input {
  padding: 10px 12px;
  border: 1px solid var(--border-color, #374151);
  border-radius: 6px;
  font-size: 0.875rem;
  background: var(--bg-primary, #111827);
  color: var(--text-primary, #f9fafb);
  transition: all 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color, #3b82f6);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input:invalid {
  border-color: var(--error-color, #ef4444);
}

.test-connection {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.test-btn {
  align-self: flex-start;
  display: flex;
  align-items: center;
  gap: 8px;
}

.test-result {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.875rem;
}

.test-result.success {
  background: var(--success-bg, rgba(16, 185, 129, 0.1));
  color: var(--success-color, #10b981);
  border: 1px solid var(--success-color, #10b981);
}

.test-result.error {
  background: var(--error-bg, rgba(239, 68, 68, 0.1));
  color: var(--error-color, #ef4444);
  border: 1px solid var(--error-color, #ef4444);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border-color, #374151);
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color, #3b82f6);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-hover, #2563eb);
}

.btn-secondary {
  background: var(--bg-secondary, #1f2937);
  color: var(--text-primary, #f9fafb);
  border: 1px solid var(--border-color, #374151);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-tertiary, #374151);
}

.btn-outline {
  background: transparent;
  color: var(--text-secondary, #9ca3af);
  border: 1px solid var(--border-color, #374151);
}

.btn-outline:hover:not(:disabled) {
  background: var(--bg-secondary, #1f2937);
  color: var(--text-primary, #f9fafb);
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .form-actions .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>