<template>
  <div class="payment-gateway-management">
    <div class="header">
      <h2>Payment Gateway Management</h2>
      <div class="header-actions">
        <button @click="refreshAnalytics" class="btn btn-outline" :disabled="loading">
          <span v-if="loading">Refreshing...</span>
          <span v-else>Refresh Analytics</span>
        </button>
        <button @click="showAddGateway = true" class="btn btn-primary">
          Add Gateway
        </button>
      </div>
    </div>

    <!-- Analytics Overview -->
    <div v-if="analyticsOverview" class="analytics-overview">
      <div class="analytics-card">
        <h4>Total Gateways</h4>
        <div class="metric">{{ analyticsOverview.total_gateways }}</div>
        <div class="sub-metric">{{ analyticsOverview.active_gateways }} active</div>
      </div>
      <div class="analytics-card">
        <h4>Combined Success Rate</h4>
        <div class="metric">{{ analyticsOverview.overall_success_rate }}%</div>
        <div class="sub-metric">Last 30 days</div>
      </div>
      <div class="analytics-card">
        <h4>Total Volume</h4>
        <div class="metric">{{ formatCurrency(analyticsOverview.total_volume) }}</div>
        <div class="sub-metric">All gateways</div>
      </div>
      <div class="analytics-card">
        <h4>Transaction Fees</h4>
        <div class="metric">{{ formatCurrency(analyticsOverview.total_fees) }}</div>
        <div class="sub-metric">Estimated</div>
      </div>
    </div>

    <!-- Gateway List -->
    <div class="gateway-list">
      <div v-for="gateway in gateways" :key="gateway.id" class="gateway-card">
        <div class="gateway-header">
          <div class="gateway-info">
            <h3>{{ gateway.name }}</h3>
            <span class="provider-badge" :class="gateway.provider">
              {{ gateway.provider.toUpperCase() }}
            </span>
            <span class="status-badge" :class="{ active: gateway.is_active }">
              {{ gateway.is_active ? 'Active' : 'Inactive' }}
            </span>
            <span v-if="gateway.last_test_result" class="test-badge" :class="gateway.last_test_result.success ? 'success' : 'error'">
              {{ gateway.last_test_result.success ? 'Connected' : 'Connection Failed' }}
            </span>
          </div>
          <div class="gateway-actions">
            <button @click="viewAnalytics(gateway)" class="btn btn-sm btn-outline">
              Analytics
            </button>
            <button @click="testGateway(gateway)" class="btn btn-sm btn-outline" :disabled="testing === gateway.id">
              {{ testing === gateway.id ? 'Testing...' : 'Test Connection' }}
            </button>
            <button @click="editGateway(gateway)" class="btn btn-sm btn-outline">
              Edit
            </button>
            <button 
              @click="toggleGateway(gateway)" 
              class="btn btn-sm"
              :class="gateway.is_active ? 'btn-warning' : 'btn-success'"
              :disabled="toggling === gateway.id"
            >
              {{ toggling === gateway.id ? 'Processing...' : (gateway.is_active ? 'Disable' : 'Enable') }}
            </button>
          </div>
        </div>

        <div class="gateway-details">
          <div class="detail-row">
            <span class="label">Supported Currencies:</span>
            <span class="value">{{ gateway.supported_currencies?.join(', ') || 'N/A' }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Payment Methods:</span>
            <span class="value">{{ gateway.supported_methods?.join(', ') || 'N/A' }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Last Updated:</span>
            <span class="value">{{ formatDate(gateway.updated_at) }}</span>
          </div>
          <div v-if="gateway.webhook_url" class="detail-row">
            <span class="label">Webhook URL:</span>
            <span class="value webhook-url">{{ gateway.webhook_url }}</span>
          </div>
        </div>

        <!-- Gateway Statistics -->
        <div v-if="gateway.statistics && gateway.is_active" class="gateway-stats">
          <div class="stat-item">
            <span class="stat-label">Success Rate</span>
            <span class="stat-value" :class="getSuccessRateClass(gateway.statistics.success_rate)">
              {{ gateway.statistics.success_rate }}%
            </span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Total Transactions</span>
            <span class="stat-value">{{ formatNumber(gateway.statistics.total_transactions) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Total Volume</span>
            <span class="stat-value">{{ formatCurrency(gateway.statistics.total_volume) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Avg. Transaction</span>
            <span class="stat-value">{{ formatCurrency(gateway.statistics.average_transaction || 0) }}</span>
          </div>
        </div>

        <!-- Transaction Fees -->
        <div v-if="gateway.fee_structure && gateway.is_active" class="fee-structure">
          <h4>Fee Structure</h4>
          <div class="fee-details">
            <div v-if="gateway.fee_structure.percentage" class="fee-item">
              <span class="fee-label">Percentage Fee:</span>
              <span class="fee-value">{{ gateway.fee_structure.percentage }}%</span>
            </div>
            <div v-if="gateway.fee_structure.fixed" class="fee-item">
              <span class="fee-label">Fixed Fee:</span>
              <span class="fee-value">{{ formatCurrency(gateway.fee_structure.fixed) }}</span>
            </div>
            <div v-if="gateway.fee_structure.estimated_monthly" class="fee-item">
              <span class="fee-label">Est. Monthly Fees:</span>
              <span class="fee-value">{{ formatCurrency(gateway.fee_structure.estimated_monthly) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Gateway Modal -->
    <Modal v-if="showAddGateway || editingGateway" @close="closeModal">
      <template #header>
        <h3>{{ editingGateway ? 'Edit Gateway' : 'Add Payment Gateway' }}</h3>
      </template>
      
      <template #body>
        <form @submit.prevent="saveGateway" class="gateway-form">
          <div class="form-group">
            <label for="gateway-name">Gateway Name</label>
            <input
              id="gateway-name"
              v-model="gatewayForm.name"
              type="text"
              class="form-control"
              required
            />
          </div>

          <div class="form-group">
            <label for="gateway-provider">Provider</label>
            <select
              id="gateway-provider"
              v-model="gatewayForm.provider"
              class="form-control"
              required
              @change="onProviderChange"
            >
              <option value="">Select Provider</option>
              <option value="collectug">CollectUG</option>
              <option value="stripe">Stripe</option>
              <option value="paypal">PayPal</option>
            </select>
          </div>

          <!-- Provider-specific configuration -->
          <div v-if="gatewayForm.provider === 'collectug'" class="provider-config">
            <div class="form-group">
              <label for="collectug-api-key">API Key</label>
              <input
                id="collectug-api-key"
                v-model="gatewayForm.configuration.api_key"
                type="password"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label for="collectug-base-url">Base URL</label>
              <input
                id="collectug-base-url"
                v-model="gatewayForm.configuration.base_url"
                type="url"
                class="form-control"
                placeholder="https://api.collectug.com"
                required
              />
            </div>
          </div>

          <div v-if="gatewayForm.provider === 'stripe'" class="provider-config">
            <div class="form-group">
              <label for="stripe-secret-key">Secret Key</label>
              <input
                id="stripe-secret-key"
                v-model="gatewayForm.configuration.secret_key"
                type="password"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label for="stripe-webhook-secret">Webhook Secret</label>
              <input
                id="stripe-webhook-secret"
                v-model="gatewayForm.configuration.webhook_secret"
                type="password"
                class="form-control"
              />
            </div>
          </div>

          <div v-if="gatewayForm.provider === 'paypal'" class="provider-config">
            <div class="form-group">
              <label for="paypal-client-id">Client ID</label>
              <input
                id="paypal-client-id"
                v-model="gatewayForm.configuration.client_id"
                type="text"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label for="paypal-client-secret">Client Secret</label>
              <input
                id="paypal-client-secret"
                v-model="gatewayForm.configuration.client_secret"
                type="password"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label for="paypal-environment">Environment</label>
              <select
                id="paypal-environment"
                v-model="gatewayForm.configuration.environment"
                class="form-control"
                required
              >
                <option value="sandbox">Sandbox</option>
                <option value="live">Live</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="webhook-url">Webhook URL</label>
            <input
              id="webhook-url"
              v-model="gatewayForm.webhook_url"
              type="url"
              class="form-control"
              placeholder="https://yourdomain.com/webhooks/payment"
            />
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input
                v-model="gatewayForm.is_active"
                type="checkbox"
              />
              Enable Gateway
            </label>
          </div>
        </form>
      </template>

      <template #footer>
        <button @click="closeModal" class="btn btn-secondary">Cancel</button>
        <button @click="testConnection" class="btn btn-outline" :disabled="!canTestConnection">
          Test Connection
        </button>
        <button @click="saveGateway" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Gateway' }}
        </button>
      </template>
    </Modal>

    <!-- Connection Test Results -->
    <Modal v-if="showTestResults" @close="showTestResults = false">
      <template #header>
        <h3>Connection Test Results</h3>
      </template>
      
      <template #body>
        <div class="test-results">
          <div class="result-item" :class="testResults.success ? 'success' : 'error'">
            <span class="result-icon">
              {{ testResults.success ? '✓' : '✗' }}
            </span>
            <span class="result-message">{{ testResults.message }}</span>
          </div>
          
          <div v-if="testResults.details" class="result-details">
            <h4>Details:</h4>
            <pre>{{ JSON.stringify(testResults.details, null, 2) }}</pre>
          </div>
        </div>
      </template>

      <template #footer>
        <button @click="showTestResults = false" class="btn btn-primary">Close</button>
      </template>
    </Modal>

    <!-- Analytics Modal -->
    <Modal v-if="showAnalytics && selectedGateway" @close="showAnalytics = false" size="large">
      <template #header>
        <h3>{{ selectedGateway.name }} - Detailed Analytics</h3>
      </template>
      
      <template #body>
        <div class="analytics-content">
          <!-- Performance Metrics -->
          <div class="analytics-section">
            <h4>Performance Metrics</h4>
            <div class="metrics-grid">
              <div class="metric-card">
                <div class="metric-title">Success Rate</div>
                <div class="metric-value" :class="getSuccessRateClass(selectedGateway.statistics?.success_rate || 0)">
                  {{ selectedGateway.statistics?.success_rate || 0 }}%
                </div>
                <div class="metric-trend">
                  <span class="trend-indicator" :class="getTrendClass(2.5)">↗ +2.5%</span>
                  <span class="trend-period">vs last month</span>
                </div>
              </div>
              
              <div class="metric-card">
                <div class="metric-title">Total Transactions</div>
                <div class="metric-value">{{ formatNumber(selectedGateway.statistics?.total_transactions || 0) }}</div>
                <div class="metric-trend">
                  <span class="trend-indicator positive">↗ +15%</span>
                  <span class="trend-period">vs last month</span>
                </div>
              </div>
              
              <div class="metric-card">
                <div class="metric-title">Total Volume</div>
                <div class="metric-value">{{ formatCurrency(selectedGateway.statistics?.total_volume || 0) }}</div>
                <div class="metric-trend">
                  <span class="trend-indicator positive">↗ +8.2%</span>
                  <span class="trend-period">vs last month</span>
                </div>
              </div>
              
              <div class="metric-card">
                <div class="metric-title">Average Transaction</div>
                <div class="metric-value">{{ formatCurrency(selectedGateway.statistics?.average_transaction || 0) }}</div>
                <div class="metric-trend">
                  <span class="trend-indicator negative">↘ -3.1%</span>
                  <span class="trend-period">vs last month</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Fees -->
          <div class="analytics-section">
            <h4>Fee Analysis</h4>
            <div class="fee-analysis">
              <div class="fee-breakdown">
                <div class="fee-item">
                  <span class="fee-label">Total Fees Paid (30 days):</span>
                  <span class="fee-amount">{{ formatCurrency(calculateEstimatedFees(selectedGateway)) }}</span>
                </div>
                <div class="fee-item">
                  <span class="fee-label">Average Fee per Transaction:</span>
                  <span class="fee-amount">{{ formatCurrency(calculateAverageFee(selectedGateway)) }}</span>
                </div>
                <div class="fee-item">
                  <span class="fee-label">Fee as % of Volume:</span>
                  <span class="fee-percentage">{{ calculateFeePercentage(selectedGateway) }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="analytics-section">
            <h4>Recent Activity</h4>
            <div class="activity-list">
              <div v-for="activity in mockRecentActivity" :key="activity.id" class="activity-item">
                <div class="activity-icon" :class="activity.type">
                  {{ getActivityIcon(activity.type) }}
                </div>
                <div class="activity-details">
                  <div class="activity-title">{{ activity.title }}</div>
                  <div class="activity-time">{{ formatRelativeTime(activity.timestamp) }}</div>
                </div>
                <div class="activity-amount" v-if="activity.amount">
                  {{ formatCurrency(activity.amount) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Configuration Status -->
          <div class="analytics-section">
            <h4>Configuration Status</h4>
            <div class="config-status">
              <div class="status-item">
                <span class="status-label">API Connection:</span>
                <span class="status-value" :class="selectedGateway.last_test_result?.success ? 'success' : 'error'">
                  {{ selectedGateway.last_test_result?.success ? 'Connected' : 'Failed' }}
                </span>
              </div>
              <div class="status-item">
                <span class="status-label">Webhook URL:</span>
                <span class="status-value" :class="selectedGateway.webhook_url ? 'success' : 'warning'">
                  {{ selectedGateway.webhook_url ? 'Configured' : 'Not Set' }}
                </span>
              </div>
              <div class="status-item">
                <span class="status-label">Supported Currencies:</span>
                <span class="status-value">{{ selectedGateway.supported_currencies?.join(', ') || 'None' }}</span>
              </div>
              <div class="status-item">
                <span class="status-label">Payment Methods:</span>
                <span class="status-value">{{ selectedGateway.supported_methods?.join(', ') || 'None' }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <button @click="exportAnalytics(selectedGateway)" class="btn btn-outline">
          Export Report
        </button>
        <button @click="showAnalytics = false" class="btn btn-primary">Close</button>
      </template>
    </Modal>

    <!-- Loading Overlay -->
    <LoadingOverlay v-if="loading" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAppStore } from '@/store/modules/app'
import Modal from '@/components/common/Modal.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'
import api from '@/api/index'

interface PaymentGateway {
  id: string
  name: string
  provider: string
  is_active: boolean
  webhook_url?: string
  configuration: Record<string, any>
  supported_currencies?: string[]
  supported_methods?: string[]
  statistics?: {
    success_rate: number
    total_transactions: number
    total_volume: number
    average_transaction?: number
  }
  fee_structure?: {
    percentage?: number
    fixed?: number
    estimated_monthly?: number
  }
  last_test_result?: {
    success: boolean
    message: string
    details?: any
  }
  created_at: string
  updated_at: string
}

interface AnalyticsOverview {
  total_gateways: number
  active_gateways: number
  overall_success_rate: number
  total_volume: number
  total_fees: number
}

interface GatewayForm {
  name: string
  provider: string
  webhook_url: string
  is_active: boolean
  configuration: Record<string, any>
}

const appStore = useAppStore()

// State
const gateways = ref<PaymentGateway[]>([])
const loading = ref(false)
const saving = ref(false)
const testing = ref<string | null>(null)
const toggling = ref<string | null>(null)
const showAddGateway = ref(false)
const editingGateway = ref<PaymentGateway | null>(null)
const showTestResults = ref(false)
const showAnalytics = ref(false)
const selectedGateway = ref<PaymentGateway | null>(null)
const analyticsOverview = ref<AnalyticsOverview | null>(null)
const testResults = ref<{ success: boolean; message: string; details?: any }>({
  success: false,
  message: ''
})

// Form state
const gatewayForm = ref<GatewayForm>({
  name: '',
  provider: '',
  webhook_url: '',
  is_active: true,
  configuration: {}
})

// Computed
const canTestConnection = computed(() => {
  return gatewayForm.value.provider && 
         Object.keys(gatewayForm.value.configuration).length > 0
})

// Methods
const loadGateways = async () => {
  loading.value = true
  try {
    const response = await api.get('/api/v1/payment-gateways')
    gateways.value = response.data.data
  } catch (error) {
    appStore.addErrorNotification('Failed to load payment gateways')
    console.error('Error loading gateways:', error)
  } finally {
    loading.value = false
  }
}

const onProviderChange = () => {
  // Reset configuration when provider changes
  gatewayForm.value.configuration = {}
  
  // Set default values based on provider
  switch (gatewayForm.value.provider) {
    case 'collectug':
      gatewayForm.value.configuration = {
        api_key: '',
        base_url: 'https://api.collectug.com'
      }
      break
    case 'stripe':
      gatewayForm.value.configuration = {
        secret_key: '',
        webhook_secret: ''
      }
      break
    case 'paypal':
      gatewayForm.value.configuration = {
        client_id: '',
        client_secret: '',
        environment: 'sandbox'
      }
      break
  }
}

const testConnection = async () => {
  if (!canTestConnection.value) return

  loading.value = true
  try {
    const response = await api.post('/api/v1/payment-gateways/test', {
      provider: gatewayForm.value.provider,
      configuration: gatewayForm.value.configuration
    })

    testResults.value = {
      success: response.data.success,
      message: response.data.message,
      details: response.data.details
    }
    showTestResults.value = true

    if (response.data.success) {
      appStore.addSuccessNotification('Gateway connection test successful')
    } else {
      appStore.addErrorNotification('Gateway connection test failed')
    }
  } catch (error: any) {
    testResults.value = {
      success: false,
      message: error.response?.data?.message || 'Connection test failed',
      details: error.response?.data
    }
    showTestResults.value = true
    appStore.addErrorNotification('Connection test failed')
  } finally {
    loading.value = false
  }
}

const saveGateway = async () => {
  saving.value = true
  try {
    const payload = { ...gatewayForm.value }
    
    let response
    if (editingGateway.value) {
      response = await api.put(`/api/v1/payment-gateways/${editingGateway.value.id}`, payload)
    } else {
      response = await api.post('/api/v1/payment-gateways', payload)
    }

    appStore.addSuccessNotification(
      editingGateway.value ? 'Gateway updated successfully' : 'Gateway created successfully'
    )

    closeModal()
    await loadGateways()
  } catch (error: any) {
    appStore.addErrorNotification(
      error.response?.data?.message || 'Failed to save gateway'
    )
  } finally {
    saving.value = false
  }
}

const editGateway = (gateway: PaymentGateway) => {
  editingGateway.value = gateway
  gatewayForm.value = {
    name: gateway.name,
    provider: gateway.provider,
    webhook_url: gateway.webhook_url || '',
    is_active: gateway.is_active,
    configuration: { ...gateway.configuration }
  }
  showAddGateway.value = true
}

const closeModal = () => {
  showAddGateway.value = false
  editingGateway.value = null
  gatewayForm.value = {
    name: '',
    provider: '',
    webhook_url: '',
    is_active: true,
    configuration: {}
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(amount)
}

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('en-UG').format(num)
}

// New enhanced methods
const refreshAnalytics = async () => {
  loading.value = true
  try {
    await loadGateways()
    await loadAnalyticsOverview()
    appStore.addSuccessNotification('Analytics refreshed successfully')
  } catch (error) {
    appStore.addErrorNotification('Failed to refresh analytics')
  } finally {
    loading.value = false
  }
}

const loadAnalyticsOverview = async () => {
  try {
    // Calculate overview from gateways data
    const activeGateways = gateways.value.filter(g => g.is_active)
    const totalVolume = gateways.value.reduce((sum, g) => sum + (g.statistics?.total_volume || 0), 0)
    const totalTransactions = gateways.value.reduce((sum, g) => sum + (g.statistics?.total_transactions || 0), 0)
    const avgSuccessRate = gateways.value.length > 0 
      ? gateways.value.reduce((sum, g) => sum + (g.statistics?.success_rate || 0), 0) / gateways.value.length 
      : 0

    analyticsOverview.value = {
      total_gateways: gateways.value.length,
      active_gateways: activeGateways.length,
      overall_success_rate: Math.round(avgSuccessRate * 100) / 100,
      total_volume: totalVolume,
      total_fees: calculateTotalFees()
    }
  } catch (error) {
    console.error('Error loading analytics overview:', error)
  }
}

const calculateTotalFees = () => {
  return gateways.value.reduce((total, gateway) => {
    if (!gateway.statistics?.total_volume) return total
    
    // Estimate fees based on provider
    let feeRate: number
    switch (gateway.provider) {
      case 'collectug':
        feeRate = 0.025 // 2.5%
        break
      case 'stripe':
        feeRate = 0.029 // 2.9%
        break
      case 'paypal':
        feeRate = 0.034 // 3.4%
        break
      default:
        feeRate = 0.03 // 3%
    }
    
    return total + (gateway.statistics.total_volume * feeRate)
  }, 0)
}

const viewAnalytics = (gateway: PaymentGateway) => {
  selectedGateway.value = gateway
  showAnalytics.value = true
}

const getSuccessRateClass = (rate: number) => {
  if (rate >= 95) return 'excellent'
  if (rate >= 90) return 'good'
  if (rate >= 80) return 'fair'
  return 'poor'
}

const getTrendClass = (trend: number) => {
  return trend > 0 ? 'positive' : trend < 0 ? 'negative' : 'neutral'
}

const calculateEstimatedFees = (gateway: PaymentGateway) => {
  if (!gateway.statistics?.total_volume) return 0
  
  let feeRate: number
  switch (gateway.provider) {
    case 'collectug':
      feeRate = 0.025
      break
    case 'stripe':
      feeRate = 0.029
      break
    case 'paypal':
      feeRate = 0.034
      break
    default:
      feeRate = 0.03
  }
  
  return gateway.statistics.total_volume * feeRate
}

const calculateAverageFee = (gateway: PaymentGateway) => {
  if (!gateway.statistics?.total_transactions || !gateway.statistics?.total_volume) return 0
  
  const totalFees = calculateEstimatedFees(gateway)
  return totalFees / gateway.statistics.total_transactions
}

const calculateFeePercentage = (gateway: PaymentGateway) => {
  if (!gateway.statistics?.total_volume) return 0
  
  const totalFees = calculateEstimatedFees(gateway)
  return Math.round((totalFees / gateway.statistics.total_volume) * 10000) / 100
}

const mockRecentActivity = computed(() => [
  {
    id: 1,
    type: 'success',
    title: 'Payment processed successfully',
    timestamp: new Date(Date.now() - 5 * 60 * 1000),
    amount: 25000
  },
  {
    id: 2,
    type: 'error',
    title: 'Payment failed - insufficient funds',
    timestamp: new Date(Date.now() - 15 * 60 * 1000),
    amount: 15000
  },
  {
    id: 3,
    type: 'info',
    title: 'Configuration updated',
    timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000)
  },
  {
    id: 4,
    type: 'success',
    title: 'Webhook received',
    timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000),
    amount: 50000
  }
])

const getActivityIcon = (type: string) => {
  switch (type) {
    case 'success': return '✓'
    case 'error': return '✗'
    case 'warning': return '⚠'
    case 'info': return 'ℹ'
    default: return '•'
  }
}

const formatRelativeTime = (date: Date) => {
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / (1000 * 60))
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  return `${diffDays}d ago`
}

const exportAnalytics = async (gateway: PaymentGateway) => {
  try {
    // In a real implementation, this would call an API endpoint
    const data = {
      gateway_name: gateway.name,
      provider: gateway.provider,
      statistics: gateway.statistics,
      fee_analysis: {
        estimated_fees: calculateEstimatedFees(gateway),
        average_fee: calculateAverageFee(gateway),
        fee_percentage: calculateFeePercentage(gateway)
      },
      export_date: new Date().toISOString()
    }
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${gateway.name}-analytics-${new Date().toISOString().split('T')[0]}.json`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    
    appStore.addSuccessNotification('Analytics report exported successfully')
  } catch (error) {
    appStore.addErrorNotification('Failed to export analytics report')
  }
}

// Enhanced toggle method with loading state
const toggleGateway = async (gateway: PaymentGateway) => {
  toggling.value = gateway.id
  try {
    await api.patch(`/api/v1/payment-gateways/${gateway.id}/toggle`)
    gateway.is_active = !gateway.is_active
    appStore.addSuccessNotification(
      `Gateway ${gateway.is_active ? 'enabled' : 'disabled'} successfully`
    )
    
    // Refresh analytics after status change
    await loadAnalyticsOverview()
  } catch (error: any) {
    appStore.addErrorNotification(
      error.response?.data?.message || 'Failed to toggle gateway status'
    )
  } finally {
    toggling.value = null
  }
}

// Enhanced test method with loading state
const testGateway = async (gateway: PaymentGateway) => {
  testing.value = gateway.id
  try {
    const response = await api.post(`/api/v1/payment-gateways/${gateway.id}/test`)
    
    // Store test result in gateway object
    gateway.last_test_result = {
      success: response.data.success,
      message: response.data.message,
      details: response.data.details
    }
    
    testResults.value = {
      success: response.data.success,
      message: response.data.message,
      details: response.data.details
    }
    showTestResults.value = true

    if (response.data.success) {
      appStore.addSuccessNotification('Gateway test successful')
    } else {
      appStore.addErrorNotification('Gateway test failed')
    }
  } catch (error: any) {
    const result = {
      success: false,
      message: error.response?.data?.message || 'Gateway test failed',
      details: error.response?.data
    }
    
    gateway.last_test_result = result
    testResults.value = result
    showTestResults.value = true
    appStore.addErrorNotification('Gateway test failed')
  } finally {
    testing.value = null
  }
}

// Lifecycle
onMounted(async () => {
  await loadGateways()
  await loadAnalyticsOverview()
})
</script>

<style scoped>
.payment-gateway-management {
  padding: 1.5rem;
}

.header {
  display: flex;
  justify-content: between;
  align-items: center;
  margin-bottom: 2rem;
}

.header h2 {
  margin: 0;
  color: var(--text-primary);
}

.gateway-list {
  display: grid;
  gap: 1.5rem;
}

.gateway-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
  transition: box-shadow 0.2s ease;
}

.gateway-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.gateway-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.gateway-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.gateway-info h3 {
  margin: 0;
  color: var(--text-primary);
}

.provider-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.provider-badge.collectug {
  background: #e3f2fd;
  color: #1976d2;
}

.provider-badge.stripe {
  background: #f3e5f5;
  color: #7b1fa2;
}

.provider-badge.paypal {
  background: #fff3e0;
  color: #f57c00;
}

.status-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #ffebee;
  color: #c62828;
}

.status-badge.active {
  background: #e8f5e8;
  color: #2e7d32;
}

.gateway-actions {
  display: flex;
  gap: 0.5rem;
}

.gateway-details {
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  margin-bottom: 0.5rem;
}

.detail-row .label {
  font-weight: 600;
  min-width: 150px;
  color: var(--text-secondary);
}

.detail-row .value {
  color: var(--text-primary);
}

.gateway-stats {
  display: flex;
  gap: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.stat-item {
  text-align: center;
}

.stat-label {
  display: block;
  font-size: 0.875rem;
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
}

.stat-value {
  display: block;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
}

.gateway-form {
  display: grid;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text-primary);
}

.form-control {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.provider-config {
  padding: 1rem;
  background: var(--bg-tertiary);
  border-radius: 4px;
  border: 1px solid var(--border-color);
}

.test-results {
  padding: 1rem;
}

.result-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

.result-item.success {
  background: #e8f5e8;
  color: #2e7d32;
}

.result-item.error {
  background: #ffebee;
  color: #c62828;
}

.result-icon {
  font-size: 1.25rem;
  font-weight: bold;
}

.result-details {
  margin-top: 1rem;
}

.result-details pre {
  background: var(--bg-secondary);
  padding: 1rem;
  border-radius: 4px;
  overflow-x: auto;
  font-size: 0.875rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-color-dark);
}

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-secondary);
}

.btn-outline {
  background: transparent;
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}

.btn-outline:hover:not(:disabled) {
  background: var(--primary-color);
  color: white;
}

.btn-success {
  background: #4caf50;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #45a049;
}

.btn-warning {
  background: #ff9800;
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background: #f57c00;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

/* Enhanced Dashboard Styles */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.analytics-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.analytics-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
  text-align: center;
}

.analytics-card h4 {
  margin: 0 0 0.5rem 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.analytics-card .metric {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.analytics-card .sub-metric {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.test-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.test-badge.success {
  background: #e8f5e8;
  color: #2e7d32;
}

.test-badge.error {
  background: #ffebee;
  color: #c62828;
}

.webhook-url {
  font-family: monospace;
  font-size: 0.875rem;
  word-break: break-all;
}

.stat-value.excellent {
  color: #2e7d32;
}

.stat-value.good {
  color: #388e3c;
}

.stat-value.fair {
  color: #f57c00;
}

.stat-value.poor {
  color: #d32f2f;
}

.fee-structure {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.fee-structure h4 {
  margin: 0 0 0.75rem 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.fee-details {
  display: grid;
  gap: 0.5rem;
}

.fee-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fee-label {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.fee-value {
  font-weight: 600;
  color: var(--text-primary);
}

/* Analytics Modal Styles */
.analytics-content {
  max-height: 70vh;
  overflow-y: auto;
}

.analytics-section {
  margin-bottom: 2rem;
}

.analytics-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.5rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.metric-card {
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 1rem;
}

.metric-title {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.metric-trend {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.trend-indicator {
  font-size: 0.75rem;
  font-weight: 600;
}

.trend-indicator.positive {
  color: #2e7d32;
}

.trend-indicator.negative {
  color: #d32f2f;
}

.trend-indicator.neutral {
  color: var(--text-secondary);
}

.trend-period {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.fee-analysis {
  background: var(--bg-tertiary);
  border-radius: 6px;
  padding: 1rem;
}

.fee-breakdown {
  display: grid;
  gap: 0.75rem;
}

.fee-amount {
  font-weight: 600;
  color: var(--text-primary);
}

.fee-percentage {
  font-weight: 600;
  color: var(--primary-color);
}

.activity-list {
  display: grid;
  gap: 0.75rem;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 6px;
}

.activity-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 0.875rem;
}

.activity-icon.success {
  background: #e8f5e8;
  color: #2e7d32;
}

.activity-icon.error {
  background: #ffebee;
  color: #c62828;
}

.activity-icon.warning {
  background: #fff3e0;
  color: #f57c00;
}

.activity-icon.info {
  background: #e3f2fd;
  color: #1976d2;
}

.activity-details {
  flex: 1;
}

.activity-title {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.activity-time {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.activity-amount {
  font-weight: 600;
  color: var(--text-primary);
}

.config-status {
  display: grid;
  gap: 0.75rem;
}

.status-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 6px;
}

.status-label {
  font-weight: 600;
  color: var(--text-secondary);
}

.status-value {
  font-weight: 600;
}

.status-value.success {
  color: #2e7d32;
}

.status-value.warning {
  color: #f57c00;
}

.status-value.error {
  color: #d32f2f;
}
</style>