<template>
  <div class="api-key-management">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          API Key Management
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Configure API keys for external services. Keys are encrypted and securely stored.
        </p>
      </div>

      <div class="p-6">
        <form @submit.prevent="saveSettings" class="space-y-6">
          <!-- Payment Gateway Settings -->
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">
              Payment Gateway (CollectUG)
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  API Key
                </label>
                <div class="relative">
                  <input
                    v-model="settings.payment.api_key"
                    :type="showPaymentApiKey ? 'text' : 'password'"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="pk_test_..."
                    :class="{ 'border-red-500': errors.payment?.api_key }"
                  />
                  <button
                    type="button"
                    @click="showPaymentApiKey = !showPaymentApiKey"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    <EyeIcon v-if="!showPaymentApiKey" class="h-5 w-5 text-gray-400" />
                    <EyeSlashIcon v-else class="h-5 w-5 text-gray-400" />
                  </button>
                </div>
                <p v-if="errors.payment?.api_key" class="mt-1 text-sm text-red-600">
                  {{ errors.payment.api_key[0] }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  Format: pk_test_... or pk_live_...
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  API Secret
                </label>
                <div class="relative">
                  <input
                    v-model="settings.payment.api_secret"
                    :type="showPaymentSecret ? 'text' : 'password'"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="sk_test_..."
                    :class="{ 'border-red-500': errors.payment?.api_secret }"
                  />
                  <button
                    type="button"
                    @click="showPaymentSecret = !showPaymentSecret"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    <EyeIcon v-if="!showPaymentSecret" class="h-5 w-5 text-gray-400" />
                    <EyeSlashIcon v-else class="h-5 w-5 text-gray-400" />
                  </button>
                </div>
                <p v-if="errors.payment?.api_secret" class="mt-1 text-sm text-red-600">
                  {{ errors.payment.api_secret[0] }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  Format: sk_test_... or sk_live_...
                </p>
              </div>
            </div>

            <div class="mt-4 flex items-center space-x-4">
              <label class="flex items-center">
                <input
                  v-model="settings.payment.enabled"
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable Payment Gateway</span>
              </label>

              <button
                type="button"
                @click="testPaymentConnection"
                :disabled="!settings.payment.api_key || !settings.payment.api_secret || testing.payment"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="testing.payment">Testing...</span>
                <span v-else>Test Connection</span>
              </button>
            </div>

            <div v-if="testResults.payment" class="mt-3 p-3 rounded-md" :class="testResults.payment.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="flex">
                <CheckCircleIcon v-if="testResults.payment.success" class="h-5 w-5 text-green-400" />
                <XCircleIcon v-else class="h-5 w-5 text-red-400" />
                <div class="ml-3">
                  <p class="text-sm" :class="testResults.payment.success ? 'text-green-800' : 'text-red-800'">
                    {{ testResults.payment.message }}
                  </p>
                  <div v-if="testResults.payment.data" class="mt-2 text-xs" :class="testResults.payment.success ? 'text-green-700' : 'text-red-700'">
                    <p v-if="testResults.payment.data.balance">Balance: {{ testResults.payment.data.balance }}</p>
                    <p v-if="testResults.payment.data.error">Error: {{ testResults.payment.data.error }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SMS Gateway Settings -->
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">
              SMS Gateway (UGSMS)
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  API Key
                </label>
                <div class="relative">
                  <input
                    v-model="settings.sms.api_key"
                    :type="showSmsApiKey ? 'text' : 'password'"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="sms_..."
                    :class="{ 'border-red-500': errors.sms?.api_key }"
                  />
                  <button
                    type="button"
                    @click="showSmsApiKey = !showSmsApiKey"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    <EyeIcon v-if="!showSmsApiKey" class="h-5 w-5 text-gray-400" />
                    <EyeSlashIcon v-else class="h-5 w-5 text-gray-400" />
                  </button>
                </div>
                <p v-if="errors.sms?.api_key" class="mt-1 text-sm text-red-600">
                  {{ errors.sms.api_key[0] }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  Format: sms_... or ugsms_...
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Provider
                </label>
                <select
                  v-model="settings.sms.provider"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                >
                  <option value="ugsms">UGSMS</option>
                  <option value="africas_talking">Africa's Talking</option>
                  <option value="custom">Custom</option>
                </select>
              </div>
            </div>

            <div class="mt-4 flex items-center space-x-4">
              <label class="flex items-center">
                <input
                  v-model="settings.sms.enabled"
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable SMS Gateway</span>
              </label>

              <button
                type="button"
                @click="testSmsConnection"
                :disabled="!settings.sms.api_key || testing.sms"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="testing.sms">Testing...</span>
                <span v-else>Test Connection</span>
              </button>
            </div>

            <div v-if="testResults.sms" class="mt-3 p-3 rounded-md" :class="testResults.sms.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="flex">
                <CheckCircleIcon v-if="testResults.sms.success" class="h-5 w-5 text-green-400" />
                <XCircleIcon v-else class="h-5 w-5 text-red-400" />
                <div class="ml-3">
                  <p class="text-sm" :class="testResults.sms.success ? 'text-green-800' : 'text-red-800'">
                    {{ testResults.sms.message }}
                  </p>
                  <div v-if="testResults.sms.data" class="mt-2 text-xs" :class="testResults.sms.success ? 'text-green-700' : 'text-red-700'">
                    <p v-if="testResults.sms.data.balance">Balance: {{ testResults.sms.data.balance }}</p>
                    <p v-if="testResults.sms.data.error">Error: {{ testResults.sms.data.error }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end space-x-3">
            <button
              type="button"
              @click="resetForm"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
              Reset
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save Settings</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="message" class="mt-4 p-4 rounded-md" :class="message.type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
      <div class="flex">
        <CheckCircleIcon v-if="message.type === 'success'" class="h-5 w-5 text-green-400" />
        <XCircleIcon v-else class="h-5 w-5 text-red-400" />
        <div class="ml-3">
          <p class="text-sm" :class="message.type === 'success' ? 'text-green-800' : 'text-red-800'">
            {{ message.text }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { EyeIcon, EyeSlashIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

// Reactive data
const settings = reactive({
  payment: {
    api_key: '',
    api_secret: '',
    enabled: false,
    test_mode: true
  },
  sms: {
    api_key: '',
    provider: 'ugsms',
    enabled: false
  }
})

const errors = ref({})
const message = ref(null)
const saving = ref(false)

// Visibility toggles
const showPaymentApiKey = ref(false)
const showPaymentSecret = ref(false)
const showSmsApiKey = ref(false)

// Testing states
const testing = reactive({
  payment: false,
  sms: false
})

const testResults = reactive({
  payment: null,
  sms: null
})

// Methods
const loadSettings = async () => {
  try {
    const response = await axios.get('/api/v1/settings')
    if (response.data.success) {
      const data = response.data.data.settings
      
      // Load payment settings
      if (data.payment) {
        Object.assign(settings.payment, data.payment)
      }
      
      // Load SMS settings
      if (data.sms) {
        Object.assign(settings.sms, data.sms)
      }
    }
  } catch (error) {
    console.error('Failed to load settings:', error)
    showMessage('Failed to load settings', 'error')
  }
}

const saveSettings = async () => {
  saving.value = true
  errors.value = {}
  
  try {
    const response = await axios.put('/api/v1/settings', {
      settings: settings
    })
    
    if (response.data.success) {
      showMessage('Settings saved successfully', 'success')
      
      // Clear test results after successful save
      testResults.payment = null
      testResults.sms = null
    } else {
      showMessage(response.data.message || 'Failed to save settings', 'error')
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
      showMessage('Please fix validation errors', 'error')
    } else {
      showMessage('Failed to save settings', 'error')
    }
  } finally {
    saving.value = false
  }
}

const testPaymentConnection = async () => {
  testing.payment = true
  testResults.payment = null
  
  try {
    const response = await axios.post('/api/v1/settings/test-payment', {
      use_current_settings: false,
      test_settings: {
        api_key: settings.payment.api_key,
        api_secret: settings.payment.api_secret,
        base_url: 'https://api.collect.ug'
      },
      amount: 1000,
      phone_number: '+256700000000'
    })
    
    testResults.payment = {
      success: response.data.success,
      message: response.data.message,
      data: response.data.data
    }
  } catch (error) {
    testResults.payment = {
      success: false,
      message: error.response?.data?.message || 'Connection test failed',
      data: error.response?.data?.data
    }
  } finally {
    testing.payment = false
  }
}

const testSmsConnection = async () => {
  testing.sms = true
  testResults.sms = null
  
  try {
    const response = await axios.post('/api/v1/settings/test-sms', {
      use_current_settings: false,
      test_settings: {
        api_key: settings.sms.api_key,
        provider: settings.sms.provider
      },
      phone_number: '+256700000000',
      message: 'Test message from API key management'
    })
    
    testResults.sms = {
      success: response.data.success,
      message: response.data.message,
      data: response.data.data
    }
  } catch (error) {
    testResults.sms = {
      success: false,
      message: error.response?.data?.message || 'Connection test failed',
      data: error.response?.data?.data
    }
  } finally {
    testing.sms = false
  }
}

const resetForm = () => {
  loadSettings()
  errors.value = {}
  message.value = null
  testResults.payment = null
  testResults.sms = null
}

const showMessage = (text: string, type: 'success' | 'error') => {
  message.value = { text, type }
  setTimeout(() => {
    message.value = null
  }, 5000)
}

// Lifecycle
onMounted(() => {
  loadSettings()
})
</script>

<style scoped>
.api-key-management {
  max-width: 4xl;
}
</style>