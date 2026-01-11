<template>
  <form @submit.prevent="handleSubmit" class="customer-form">
    <!-- Form Error Handler -->
    <FormErrorHandler 
      :errors="formErrors" 
      :show-field-errors="false"
      @clear="clearErrors"
      @field-focus="focusField"
    />

    <div class="form-grid">
      <!-- Basic Information -->
      <div class="form-section">
        <h3 class="section-title">Basic Information</h3>
        
        <div class="form-group" :class="{ 'form-field--error': hasFieldError('name') }">
          <label for="name" class="form-label">Full Name *</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            name="name"
            class="form-input"
            :class="{ 'error': hasFieldError('name') }"
            placeholder="Enter customer's full name"
            required
            @blur="validateField('name')"
          />
          <span v-if="hasFieldError('name')" class="error-message">{{ getFirstFieldError('name') }}</span>
        </div>

        <div class="form-group" :class="{ 'form-field--error': hasFieldError('email') }">
          <label for="email" class="form-label">Email Address</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            name="email"
            class="form-input"
            :class="{ 'error': hasFieldError('email') }"
            placeholder="Enter email address (optional)"
            @blur="validateField('email')"
          />
          <span v-if="hasFieldError('email')" class="error-message">{{ getFirstFieldError('email') }}</span>
        </div>

        <div class="form-group" :class="{ 'form-field--error': hasFieldError('phone') }">
          <label for="phone" class="form-label">Phone Number *</label>
          <input
            id="phone"
            v-model="form.phone"
            type="tel"
            name="phone"
            class="form-input"
            :class="{ 'error': hasFieldError('phone') }"
            placeholder="Enter phone number"
            required
            @blur="validateField('phone')"
          />
          <span v-if="hasFieldError('phone')" class="error-message">{{ getFirstFieldError('phone') }}</span>
        </div>

        <div class="form-group" :class="{ 'form-field--error': hasFieldError('status') }">
          <label for="status" class="form-label">Status *</label>
          <select
            id="status"
            v-model="form.status"
            name="status"
            class="form-select"
            :class="{ 'error': hasFieldError('status') }"
            required
          >
            <option value="">Select status</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="inactive">Inactive</option>
          </select>
          <span v-if="hasFieldError('status')" class="error-message">{{ getFirstFieldError('status') }}</span>
        </div>
      </div>

      <!-- Location Information -->
      <div class="form-section">
        <h3 class="section-title">Location Information</h3>
        
        <div class="form-group">
          <label for="region" class="form-label">Region</label>
          <input
            id="region"
            v-model="form.location.region"
            type="text"
            class="form-input"
            placeholder="Enter region"
          />
        </div>

        <div class="form-group">
          <label for="district" class="form-label">District</label>
          <input
            id="district"
            v-model="form.location.district"
            type="text"
            class="form-input"
            placeholder="Enter district"
          />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="latitude" class="form-label">Latitude</label>
            <input
              id="latitude"
              v-model.number="form.location.coordinates.lat"
              type="number"
              step="any"
              class="form-input"
              placeholder="0.000000"
            />
          </div>

          <div class="form-group">
            <label for="longitude" class="form-label">Longitude</label>
            <input
              id="longitude"
              v-model.number="form.location.coordinates.lng"
              type="number"
              step="any"
              class="form-input"
              placeholder="0.000000"
            />
          </div>
        </div>

        <button
          type="button"
          class="location-btn"
          @click="getCurrentLocation"
          :disabled="gettingLocation"
        >
          <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          {{ gettingLocation ? 'Getting Location...' : 'Use Current Location' }}
        </button>
      </div>

      <!-- Service Plan -->
      <div class="form-section full-width">
        <h3 class="section-title">Service Plan</h3>
        
        <div class="form-group">
          <label for="service_plan_id" class="form-label">Service Plan</label>
          <select
            id="service_plan_id"
            v-model="form.service_plan_id"
            class="form-select"
          >
            <option value="">No service plan</option>
            <option
              v-for="plan in servicePlans"
              :key="plan.id"
              :value="plan.id"
            >
              {{ plan.name }} - {{ formatCurrency(plan.price) }} ({{ plan.duration_hours }}h)
            </option>
          </select>
        </div>

        <div v-if="selectedServicePlan" class="service-plan-preview">
          <div class="plan-info">
            <h4 class="plan-name">{{ selectedServicePlan.name }}</h4>
            <p class="plan-description">{{ selectedServicePlan.description }}</p>
            <div class="plan-details">
              <span class="plan-price">{{ formatCurrency(selectedServicePlan.price) }}</span>
              <span class="plan-duration">{{ selectedServicePlan.duration_hours }} hours</span>
              <span v-if="selectedServicePlan.bandwidth_limit" class="plan-bandwidth">
                {{ selectedServicePlan.bandwidth_limit }} Mbps
              </span>
            </div>
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
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {{ customer ? 'Update Customer' : 'Create Customer' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useFormErrorHandler, validationRules } from '@/composables/useFormErrorHandler'
import FormErrorHandler from '@/components/common/FormErrorHandler.vue'
import type { Customer } from '@/types'

// Props
interface Props {
  customer?: Customer | null
  servicePlans: any[]
  loading: boolean
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  save: [customerData: Partial<Customer>]
  cancel: []
}>()

// Form error handling
const {
  errors: formErrors,
  hasFieldError,
  getFirstFieldError,
  clearErrors,
  handleSubmit: handleFormSubmit,
  isSubmitting,
  validateField: validateSingleField,
  validateForm,
  focusField,
  cleanup
} = useFormErrorHandler({
  autoFocus: true,
  showNotifications: false // We're using the FormErrorHandler component
})

// Reactive state
const gettingLocation = ref(false)

const form = ref({
  name: '',
  email: '',
  phone: '',
  status: 'active' as 'active' | 'suspended' | 'inactive',
  service_plan_id: '',
  location: {
    region: '',
    district: '',
    coordinates: {
      lat: null as number | null,
      lng: null as number | null
    }
  }
})

// Validation rules
const validationRulesConfig = {
  name: [
    validationRules.required('Customer name is required'),
    validationRules.minLength(2, 'Name must be at least 2 characters'),
    validationRules.maxLength(100, 'Name must not exceed 100 characters')
  ],
  email: [
    validationRules.email('Please enter a valid email address'),
    validationRules.maxLength(255, 'Email must not exceed 255 characters')
  ],
  phone: [
    validationRules.required('Phone number is required'),
    validationRules.phone('Please enter a valid phone number')
  ],
  status: [
    validationRules.required('Status is required')
  ]
}

// Computed properties
const selectedServicePlan = computed(() => {
  if (!form.value.service_plan_id) return null
  return props.servicePlans.find(plan => plan.id === form.value.service_plan_id)
})

const isFormValid = computed(() => {
  return form.value.name.trim() !== '' && 
         form.value.phone.trim() !== '' && 
         form.value.status !== '' &&
         !formErrors.value || Object.keys(formErrors.value).length === 0
})

// Methods
const validateField = (field: string) => {
  const rules = validationRulesConfig[field as keyof typeof validationRulesConfig]
  if (rules) {
    const value = form.value[field as keyof typeof form.value]
    validateSingleField(field, value, rules)
  }
}

const getCurrentLocation = () => {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by this browser.')
    return
  }

  gettingLocation.value = true

  navigator.geolocation.getCurrentPosition(
    (position) => {
      form.value.location.coordinates.lat = position.coords.latitude
      form.value.location.coordinates.lng = position.coords.longitude
      gettingLocation.value = false
    },
    (error) => {
      console.error('Error getting location:', error)
      alert('Unable to get your location. Please enter coordinates manually.')
      gettingLocation.value = false
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 60000
    }
  )
}

const handleSubmit = async () => {
  // Validate entire form
  const isValid = validateForm(form.value, validationRulesConfig)
  
  if (!isValid) {
    return
  }

  // Prepare form data
  const customerData: Partial<Customer> = {
    name: form.value.name.trim(),
    email: form.value.email.trim() || undefined,
    phone: form.value.phone.trim(),
    status: form.value.status,
    service_plan_id: form.value.service_plan_id || undefined
  }

  // Add location if provided
  if (form.value.location.region || form.value.location.district || 
      (form.value.location.coordinates.lat && form.value.location.coordinates.lng)) {
    customerData.location = {
      region: form.value.location.region || '',
      district: form.value.location.district || '',
      coordinates: (form.value.location.coordinates.lat && form.value.location.coordinates.lng) 
        ? {
            lat: form.value.location.coordinates.lat,
            lng: form.value.location.coordinates.lng
          }
        : undefined
    }
  }

  // Use form error handler to submit
  await handleFormSubmit(async () => {
    emit('save', customerData)
    return customerData
  }, {
    clearOnSuccess: true
  })
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(amount)
}

// Initialize form with customer data if editing
const initializeForm = () => {
  if (props.customer) {
    form.value = {
      name: props.customer.name || '',
      email: props.customer.email || '',
      phone: props.customer.phone || '',
      status: props.customer.status || 'active',
      service_plan_id: props.customer.service_plan_id || '',
      location: {
        region: props.customer.location?.region || '',
        district: props.customer.location?.district || '',
        coordinates: {
          lat: props.customer.location?.coordinates?.lat || null,
          lng: props.customer.location?.coordinates?.lng || null
        }
      }
    }
  }
}

// Watch for customer changes
watch(() => props.customer, initializeForm, { immediate: true })

// Initialize form on mount
onMounted(() => {
  initializeForm()
})

// Cleanup on unmount
onUnmounted(() => {
  cleanup()
})
</script>

<style scoped>
.customer-form {
  max-width: 800px;
  margin: 0 auto;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  margin-bottom: 32px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section.full-width {
  grid-column: 1 / -1;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.form-input,
.form-select {
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

.form-input.error,
.form-select.error {
  border-color: #ef4444;
}

.form-input.error:focus,
.form-select.error:focus {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.error-message {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 4px;
}

.location-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  color: #6b7280;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  align-self: flex-start;
}

.location-btn:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
  color: #374151;
}

.location-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.service-plan-preview {
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.plan-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.plan-name {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.plan-description {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
  line-height: 1.4;
}

.plan-details {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.plan-price,
.plan-duration,
.plan-bandwidth {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  background: white;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  color: #374151;
}

.plan-price {
  color: #059669;
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

/* Responsive design */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column-reverse;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>