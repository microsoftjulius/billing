<template>
  <div class="payment-analytics">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">Payment Analytics</h1>
        <p class="page-description">Comprehensive payment reporting, gateway testing, and transaction management</p>
      </div>
      <div class="header-actions">
        <button @click="refreshAnalytics" class="btn btn-outline" :disabled="loading">
          <i v-if="loading" class="fas fa-spinner fa-spin"></i>
          <span v-if="loading">Refreshing...</span>
          <span v-else>Refresh Analytics</span>
        </button>
        <button @click="exportReport" class="btn btn-outline">
          <i class="fas fa-download"></i>
          Export Report
        </button>
        <button @click="showTestGateway = true" class="btn btn-primary">
          <i class="fas fa-vial"></i>
          Test Gateways
        </button>
      </div>
    </div>

    <!-- Analytics Overview -->
    <div v-if="analyticsData" class="analytics-overview">
      <div class="analytics-card">
        <div class="card-icon success">
          <i class="fas fa-chart-line"></i>
        </div>
        <h4>Total Revenue</h4>
        <div class="metric">{{ formatCurrency(analyticsData.total_revenue) }}</div>
        <div class="sub-metric">{{ analyticsData.period }}</div>
      </div>
      
      <div class="analytics-card">
        <div class="card-icon info">
          <i class="fas fa-percentage"></i>
        </div>
        <h4>Success Rate</h4>
        <div class="metric">{{ analyticsData.success_rate }}%</div>
        <div class="sub-metric">{{ analyticsData.completed_payments }} completed</div>
      </div>
      
      <div class="analytics-card">
        <div class="card-icon warning">
          <i class="fas fa-credit-card"></i>
        </div>
        <h4>Total Transactions</h4>
        <div class="metric">{{ analyticsData.total_payments }}</div>
        <div class="sub-metric">{{ analyticsData.pending_payments }} pending</div>
      </div>
      
      <div class="analytics-card">
        <div class="card-icon primary">
          <i class="fas fa-dollar-sign"></i>
        </div>
        <h4>Average Amount</h4>
        <div class="metric">{{ formatCurrency(analyticsData.average_amount) }}</div>
        <div class="sub-metric">Per transaction</div>
      </div>
    </div>

    <!-- Period Filter -->
    <div class="filter-section">
      <div class="filter-group">
        <label>Period:</label>
        <select v-model="selectedPeriod" @change="loadAnalytics" class="form-select">
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="year">This Year</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>
      
      <div v-if="selectedPeriod === 'custom'" class="filter-group">
        <label>Start Date:</label>
        <input v-model="customStartDate" type="date" class="form-input" @change="loadAnalytics">
      </div>
      
      <div v-if="selectedPeriod === 'custom'" class="filter-group">
        <label>End Date:</label>
        <input v-model="customEndDate" type="date" class="form-input" @change="loadAnalytics">
      </div>
    </div>

    <!-- Gateway Performance -->
    <div v-if="gatewayPerformance" class="gateway-performance">
      <h3>Gateway Performance</h3>
      <div class="performance-grid">
        <div v-for="gateway in gatewayPerformance" :key="gateway.id" class="gateway-card">
          <div class="gateway-header">
            <h4>{{ gateway.name }}</h4>
            <span :class="['status-badge', gateway.is_active ? 'active' : 'inactive']">
              {{ gateway.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="gateway-metrics">
            <div class="metric-item">
              <span class="metric-label">Success Rate</span>
              <span class="metric-value">{{ gateway.success_rate }}%</span>
            </div>
            <div class="metric-item">
              <span class="metric-label">Total Volume</span>
              <span class="metric-value">{{ formatCurrency(gateway.total_volume) }}</span>
            </div>
            <div class="metric-item">
              <span class="metric-label">Transaction Fees</span>
              <span class="metric-value">{{ formatCurrency(gateway.total_fees) }}</span>
            </div>
            <div class="metric-item">
              <span class="metric-label">Transactions</span>
              <span class="metric-value">{{ gateway.transaction_count }}</span>
            </div>
          </div>
          <div class="gateway-actions">
            <button @click="testSpecificGateway(gateway)" class="btn btn-sm btn-outline" :disabled="testingGateway === gateway.id">
              <i v-if="testingGateway === gateway.id" class="fas fa-spinner fa-spin"></i>
              Test
            </button>
            <button @click="viewGatewayDetails(gateway)" class="btn btn-sm btn-primary">
              Details
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Records Table -->
    <div class="payment-records">
      <div class="section-header">
        <h3>Payment Records</h3>
        <div class="table-actions">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search payments..." 
            class="form-input search-input"
            @input="searchPayments"
          >
          <select v-model="statusFilter" @change="filterPayments" class="form-select">
            <option value="">All Status</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
          </select>
        </div>
      </div>

      <LazyDataTable
        :data="payments"
        :columns="paymentColumns"
        :loading="loadingPayments"
        :pagination="pagination"
        @page-change="changePage"
        @sort="sortPayments"
        @edit="editPayment"
        @view="viewPayment"
      />
    </div>

    <!-- Gateway Testing Modal -->
    <Modal v-if="showTestGateway" @close="showTestGateway = false" size="large">
      <template #header>
        <h3>Gateway Connectivity Testing</h3>
      </template>
      
      <template #body>
        <div class="gateway-testing">
          <div v-if="!testResults" class="test-selection">
            <h4>Select Gateways to Test</h4>
            <div class="gateway-list">
              <div v-for="gateway in availableGateways" :key="gateway.id" class="gateway-item">
                <label class="checkbox-label">
                  <input 
                    type="checkbox" 
                    v-model="selectedGateways" 
                    :value="gateway.id"
                  >
                  <span>{{ gateway.name }} ({{ gateway.provider }})</span>
                </label>
              </div>
            </div>
            <div class="test-options">
              <label class="checkbox-label">
                <input type="checkbox" v-model="performTestTransaction">
                <span>Perform test transaction (small amount)</span>
              </label>
            </div>
          </div>

          <div v-if="testResults" class="test-results">
            <h4>Test Results</h4>
            <div v-for="result in testResults" :key="result.gateway_id" class="test-result">
              <div class="result-header">
                <h5>{{ result.gateway_name }}</h5>
                <span :class="['status-badge', result.success ? 'success' : 'error']">
                  {{ result.success ? 'Success' : 'Failed' }}
                </span>
              </div>
              <div class="result-details">
                <p><strong>Response Time:</strong> {{ result.response_time }}ms</p>
                <p><strong>Message:</strong> {{ result.message }}</p>
                <div v-if="result.details" class="result-data">
                  <strong>Details:</strong>
                  <pre>{{ JSON.stringify(result.details, null, 2) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
      
      <template #footer>
        <button v-if="!testResults" @click="runGatewayTests" class="btn btn-primary" :disabled="testingGateways || selectedGateways.length === 0">
          <i v-if="testingGateways" class="fas fa-spinner fa-spin"></i>
          {{ testingGateways ? 'Testing...' : 'Run Tests' }}
        </button>
        <button v-if="testResults" @click="resetTests" class="btn btn-outline">
          Run New Tests
        </button>
        <button @click="showTestGateway = false" class="btn btn-secondary">
          Close
        </button>
      </template>
    </Modal>

    <!-- Payment Edit Modal -->
    <Modal v-if="showEditPayment && editingPayment" @close="closeEditModal" size="large">
      <template #header>
        <h3>Edit Payment Record</h3>
      </template>
      
      <template #body>
        <div class="payment-edit-form">
          <div class="form-group">
            <label>Transaction ID</label>
            <input v-model="editForm.transaction_id" type="text" class="form-input" readonly>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>Amount</label>
              <input v-model="editForm.amount" type="number" step="0.01" class="form-input" :readonly="!canEditAmount">
            </div>
            <div class="form-group">
              <label>Currency</label>
              <select v-model="editForm.currency" class="form-select" :disabled="!canEditAmount">
                <option value="UGX">UGX</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label>Status</label>
            <select v-model="editForm.status" class="form-select">
              <option value="pending">Pending</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Notes (Reason for modification)</label>
            <textarea v-model="editForm.notes" class="form-textarea" rows="3" placeholder="Enter reason for this modification..."></textarea>
          </div>
          
          <div v-if="editingPayment.audit_trail" class="audit-trail">
            <h4>Audit Trail</h4>
            <div v-for="entry in editingPayment.audit_trail" :key="entry.id" class="audit-entry">
              <div class="audit-header">
                <span class="audit-action">{{ entry.action }}</span>
                <span class="audit-date">{{ formatDate(entry.created_at) }}</span>
              </div>
              <div class="audit-details">
                <p><strong>User:</strong> {{ entry.user_name }}</p>
                <p v-if="entry.notes"><strong>Notes:</strong> {{ entry.notes }}</p>
                <div v-if="entry.changes" class="audit-changes">
                  <strong>Changes:</strong>
                  <pre>{{ JSON.stringify(entry.changes, null, 2) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
      
      <template #footer>
        <button @click="savePaymentChanges" class="btn btn-primary" :disabled="savingPayment">
          <i v-if="savingPayment" class="fas fa-spinner fa-spin"></i>
          Save Changes
        </button>
        <button @click="closeEditModal" class="btn btn-secondary">
          Cancel
        </button>
      </template>
    </Modal>

    <!-- Reconciliation Tools Modal -->
    <Modal v-if="showReconciliation" @close="showReconciliation = false" size="xl">
      <template #header>
        <h3>Payment Reconciliation Tools</h3>
      </template>
      
      <template #body>
        <div class="reconciliation-tools">
          <div class="reconciliation-filters">
            <div class="filter-row">
              <div class="form-group">
                <label>Date Range</label>
                <input v-model="reconciliationStartDate" type="date" class="form-input">
              </div>
              <div class="form-group">
                <label>To</label>
                <input v-model="reconciliationEndDate" type="date" class="form-input">
              </div>
              <div class="form-group">
                <label>Gateway</label>
                <select v-model="reconciliationGateway" class="form-select">
                  <option value="">All Gateways</option>
                  <option v-for="gateway in availableGateways" :key="gateway.id" :value="gateway.id">
                    {{ gateway.name }}
                  </option>
                </select>
              </div>
              <button @click="runReconciliation" class="btn btn-primary" :disabled="runningReconciliation">
                <i v-if="runningReconciliation" class="fas fa-spinner fa-spin"></i>
                Run Reconciliation
              </button>
            </div>
          </div>

          <div v-if="reconciliationResults" class="reconciliation-results">
            <div class="reconciliation-summary">
              <h4>Reconciliation Summary</h4>
              <div class="summary-grid">
                <div class="summary-item">
                  <span class="label">Total Transactions</span>
                  <span class="value">{{ reconciliationResults.total_transactions }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Matched</span>
                  <span class="value success">{{ reconciliationResults.matched_transactions }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Unmatched</span>
                  <span class="value warning">{{ reconciliationResults.unmatched_transactions }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Disputed</span>
                  <span class="value error">{{ reconciliationResults.disputed_transactions }}</span>
                </div>
              </div>
            </div>

            <div v-if="reconciliationResults.discrepancies.length > 0" class="discrepancies">
              <h4>Discrepancies Found</h4>
              <div class="discrepancy-list">
                <div v-for="discrepancy in reconciliationResults.discrepancies" :key="discrepancy.id" class="discrepancy-item">
                  <div class="discrepancy-header">
                    <span class="transaction-id">{{ discrepancy.transaction_id }}</span>
                    <span :class="['discrepancy-type', discrepancy.type]">{{ discrepancy.type }}</span>
                  </div>
                  <div class="discrepancy-details">
                    <p><strong>Issue:</strong> {{ discrepancy.description }}</p>
                    <p><strong>Expected:</strong> {{ formatCurrency(discrepancy.expected_amount) }}</p>
                    <p><strong>Actual:</strong> {{ formatCurrency(discrepancy.actual_amount) }}</p>
                  </div>
                  <div class="discrepancy-actions">
                    <button @click="resolveDiscrepancy(discrepancy)" class="btn btn-sm btn-primary">
                      Resolve
                    </button>
                    <button @click="flagDispute(discrepancy)" class="btn btn-sm btn-warning">
                      Flag Dispute
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
      
      <template #footer>
        <button @click="exportReconciliationReport" class="btn btn-outline">
          Export Report
        </button>
        <button @click="showReconciliation = false" class="btn btn-secondary">
          Close
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAppStore } from '@/store/modules/app'
import Modal from '@/components/common/Modal.vue'
import LazyDataTable from '@/components/common/LazyDataTable.vue'
import api from '@/api/client'

// Store
const appStore = useAppStore()

// Reactive state
const loading = ref(false)
const loadingPayments = ref(false)
const analyticsData = ref(null)
const gatewayPerformance = ref([])
const payments = ref([])
const pagination = ref({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})

// Filters
const selectedPeriod = ref('month')
const customStartDate = ref('')
const customEndDate = ref('')
const searchQuery = ref('')
const statusFilter = ref('')

// Gateway testing
const showTestGateway = ref(false)
const availableGateways = ref([])
const selectedGateways = ref([])
const performTestTransaction = ref(false)
const testingGateways = ref(false)
const testingGateway = ref(null)
const testResults = ref(null)

// Payment editing
const showEditPayment = ref(false)
const editingPayment = ref(null)
const savingPayment = ref(false)
const editForm = ref({
  transaction_id: '',
  amount: 0,
  currency: 'UGX',
  status: 'pending',
  notes: ''
})

// Reconciliation
const showReconciliation = ref(false)
const reconciliationStartDate = ref('')
const reconciliationEndDate = ref('')
const reconciliationGateway = ref('')
const runningReconciliation = ref(false)
const reconciliationResults = ref(null)

// Table columns
const paymentColumns = [
  { key: 'transaction_id', label: 'Transaction ID', sortable: true },
  { key: 'customer_name', label: 'Customer', sortable: true },
  { key: 'amount', label: 'Amount', sortable: true, formatter: (value) => formatCurrency(value) },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'payment_method', label: 'Method', sortable: true },
  { key: 'created_at', label: 'Date', sortable: true, formatter: (value) => formatDate(value) },
  { key: 'actions', label: 'Actions', sortable: false }
]

// Computed
const canEditAmount = computed(() => {
  return editingPayment.value?.status === 'pending' || editingPayment.value?.status === 'failed'
})

// Methods
const loadAnalytics = async () => {
  loading.value = true
  try {
    const params = {
      period: selectedPeriod.value,
      ...(selectedPeriod.value === 'custom' && {
        start_date: customStartDate.value,
        end_date: customEndDate.value
      })
    }

    const response = await api.get('/api/v1/payments/statistics', { params })
    analyticsData.value = response.data.data
  } catch (error) {
    appStore.addErrorNotification('Failed to load analytics data')
    console.error('Analytics loading error:', error)
  } finally {
    loading.value = false
  }
}

const loadGatewayPerformance = async () => {
  try {
    const response = await api.get('/api/v1/payment-gateways/analytics')
    gatewayPerformance.value = response.data.data
  } catch (error) {
    console.error('Gateway performance loading error:', error)
  }
}

const loadPayments = async (page = 1) => {
  loadingPayments.value = true
  try {
    const params = {
      page,
      per_page: pagination.value.per_page,
      ...(searchQuery.value && { search: searchQuery.value }),
      ...(statusFilter.value && { status: statusFilter.value })
    }

    const response = await api.get('/api/v1/payments', { params })
    payments.value = response.data.data.payments
    pagination.value = response.data.data.pagination
  } catch (error) {
    appStore.addErrorNotification('Failed to load payments')
    console.error('Payments loading error:', error)
  } finally {
    loadingPayments.value = false
  }
}

const loadAvailableGateways = async () => {
  try {
    const response = await api.get('/api/v1/payment-gateways')
    availableGateways.value = response.data.data
  } catch (error) {
    console.error('Gateways loading error:', error)
  }
}

const refreshAnalytics = async () => {
  await Promise.all([
    loadAnalytics(),
    loadGatewayPerformance(),
    loadPayments()
  ])
}

const exportReport = async () => {
  try {
    const params = {
      period: selectedPeriod.value,
      ...(selectedPeriod.value === 'custom' && {
        start_date: customStartDate.value,
        end_date: customEndDate.value
      })
    }

    const response = await api.get('/api/v1/payments/export', { 
      params,
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `payment-analytics-${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    appStore.addSuccessNotification('Report exported successfully')
  } catch (error) {
    appStore.addErrorNotification('Failed to export report')
    console.error('Export error:', error)
  }
}

const runGatewayTests = async () => {
  testingGateways.value = true
  try {
    const response = await api.post('/api/v1/payment-gateways/test', {
      gateway_ids: selectedGateways.value,
      test_transaction: performTestTransaction.value
    })

    testResults.value = response.data.data
    appStore.addSuccessNotification('Gateway tests completed')
  } catch (error) {
    appStore.addErrorNotification('Gateway testing failed')
    console.error('Gateway testing error:', error)
  } finally {
    testingGateways.value = false
  }
}

const testSpecificGateway = async (gateway) => {
  testingGateway.value = gateway.id
  try {
    const response = await api.post(`/api/v1/payment-gateways/${gateway.id}/test`)
    
    appStore.addSuccessNotification(
      response.data.success 
        ? `${gateway.name} test successful` 
        : `${gateway.name} test failed: ${response.data.message}`
    )
  } catch (error) {
    appStore.addErrorNotification(`Failed to test ${gateway.name}`)
    console.error('Gateway test error:', error)
  } finally {
    testingGateway.value = null
  }
}

const resetTests = () => {
  testResults.value = null
  selectedGateways.value = []
  performTestTransaction.value = false
}

const editPayment = (payment) => {
  editingPayment.value = payment
  editForm.value = {
    transaction_id: payment.transaction_id,
    amount: payment.amount,
    currency: payment.currency,
    status: payment.status,
    notes: ''
  }
  showEditPayment.value = true
}

const savePaymentChanges = async () => {
  savingPayment.value = true
  try {
    const response = await api.put(`/api/v1/payments/${editingPayment.value.id}`, editForm.value)
    
    // Update the payment in the list
    const index = payments.value.findIndex(p => p.id === editingPayment.value.id)
    if (index !== -1) {
      payments.value[index] = response.data.data
    }

    appStore.addSuccessNotification('Payment updated successfully')
    closeEditModal()
  } catch (error) {
    appStore.addErrorNotification('Failed to update payment')
    console.error('Payment update error:', error)
  } finally {
    savingPayment.value = false
  }
}

const closeEditModal = () => {
  showEditPayment.value = false
  editingPayment.value = null
  editForm.value = {
    transaction_id: '',
    amount: 0,
    currency: 'UGX',
    status: 'pending',
    notes: ''
  }
}

const viewPayment = (payment) => {
  // Navigate to payment details or show details modal
  console.log('View payment:', payment)
}

const viewGatewayDetails = (gateway) => {
  // Navigate to gateway details
  console.log('View gateway details:', gateway)
}

const changePage = (page) => {
  loadPayments(page)
}

const sortPayments = (column, direction) => {
  // Implement sorting logic
  console.log('Sort payments:', column, direction)
}

const searchPayments = () => {
  // Debounced search
  setTimeout(() => {
    loadPayments(1)
  }, 300)
}

const filterPayments = () => {
  loadPayments(1)
}

const runReconciliation = async () => {
  runningReconciliation.value = true
  try {
    const params = {
      start_date: reconciliationStartDate.value,
      end_date: reconciliationEndDate.value,
      ...(reconciliationGateway.value && { gateway_id: reconciliationGateway.value })
    }

    const response = await api.post('/api/v1/payments/reconciliation', params)
    reconciliationResults.value = response.data.data
    appStore.addSuccessNotification('Reconciliation completed')
  } catch (error) {
    appStore.addErrorNotification('Reconciliation failed')
    console.error('Reconciliation error:', error)
  } finally {
    runningReconciliation.value = false
  }
}

const resolveDiscrepancy = async (discrepancy) => {
  try {
    await api.post(`/api/v1/payments/discrepancies/${discrepancy.id}/resolve`)
    appStore.addSuccessNotification('Discrepancy resolved')
    runReconciliation()
  } catch (error) {
    appStore.addErrorNotification('Failed to resolve discrepancy')
    console.error('Discrepancy resolution error:', error)
  }
}

const flagDispute = async (discrepancy) => {
  try {
    await api.post(`/api/v1/payments/discrepancies/${discrepancy.id}/dispute`)
    appStore.addSuccessNotification('Dispute flagged')
    runReconciliation()
  } catch (error) {
    appStore.addErrorNotification('Failed to flag dispute')
    console.error('Dispute flagging error:', error)
  }
}

const exportReconciliationReport = async () => {
  try {
    const response = await api.get('/api/v1/payments/reconciliation/export', {
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `reconciliation-report-${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    appStore.addSuccessNotification('Reconciliation report exported')
  } catch (error) {
    appStore.addErrorNotification('Failed to export reconciliation report')
    console.error('Reconciliation export error:', error)
  }
}

// Utility functions
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-UG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    loadAnalytics(),
    loadGatewayPerformance(),
    loadPayments(),
    loadAvailableGateways()
  ])
})
</script>

<style scoped>
.payment-analytics {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.header-content h1 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.header-content p {
  margin: 0;
  color: var(--text-secondary);
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.analytics-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.analytics-card {
  background: var(--card-background);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
  position: relative;
}

.card-icon {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.card-icon.success { background: var(--success-light); color: var(--success); }
.card-icon.info { background: var(--info-light); color: var(--info); }
.card-icon.warning { background: var(--warning-light); color: var(--warning); }
.card-icon.primary { background: var(--primary-light); color: var(--primary); }

.analytics-card h4 {
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.sub-metric {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.filter-section {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1rem;
  background: var(--card-background);
  border: 1px solid var(--border-color);
  border-radius: 8px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-group label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-secondary);
}

.gateway-performance {
  margin-bottom: 2rem;
}

.gateway-performance h3 {
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.performance-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.gateway-card {
  background: var(--card-background);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
}

.gateway-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.gateway-header h4 {
  margin: 0;
  color: var(--text-primary);
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--success-light);
  color: var(--success);
}

.status-badge.inactive {
  background: var(--error-light);
  color: var(--error);
}

.gateway-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.metric-label {
  font-size: 0.8rem;
  color: var(--text-secondary);
}

.metric-value {
  font-weight: 600;
  color: var(--text-primary);
}

.gateway-actions {
  display: flex;
  gap: 0.5rem;
}

.payment-records {
  background: var(--card-background);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.table-actions {
  display: flex;
  gap: 0.75rem;
}

.search-input {
  min-width: 250px;
}

.gateway-testing {
  padding: 1rem 0;
}

.gateway-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin: 1rem 0;
}

.gateway-item {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.test-options {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.test-results {
  max-height: 400px;
  overflow-y: auto;
}

.test-result {
  margin-bottom: 1rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.result-header h5 {
  margin: 0;
}

.status-badge.success {
  background: var(--success-light);
  color: var(--success);
}

.status-badge.error {
  background: var(--error-light);
  color: var(--error);
}

.result-details p {
  margin: 0.25rem 0;
}

.result-data pre {
  background: var(--background-secondary);
  padding: 0.75rem;
  border-radius: 4px;
  font-size: 0.8rem;
  overflow-x: auto;
}

.payment-edit-form {
  padding: 1rem 0;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.audit-trail {
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.audit-trail h4 {
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.audit-entry {
  margin-bottom: 1rem;
  padding: 1rem;
  background: var(--background-secondary);
  border-radius: 6px;
}

.audit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.audit-action {
  font-weight: 600;
  color: var(--text-primary);
}

.audit-date {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.audit-details p {
  margin: 0.25rem 0;
  font-size: 0.9rem;
}

.audit-changes pre {
  background: var(--card-background);
  padding: 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  margin-top: 0.5rem;
}

.reconciliation-tools {
  padding: 1rem 0;
}

.reconciliation-filters {
  margin-bottom: 2rem;
}

.filter-row {
  display: flex;
  gap: 1rem;
  align-items: end;
}

.reconciliation-summary {
  margin-bottom: 2rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1rem;
  background: var(--background-secondary);
  border-radius: 6px;
}

.summary-item .label {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.summary-item .value {
  font-size: 1.5rem;
  font-weight: 600;
}

.summary-item .value.success { color: var(--success); }
.summary-item .value.warning { color: var(--warning); }
.summary-item .value.error { color: var(--error); }

.discrepancies h4 {
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.discrepancy-list {
  max-height: 400px;
  overflow-y: auto;
}

.discrepancy-item {
  margin-bottom: 1rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
}

.discrepancy-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.transaction-id {
  font-weight: 600;
  color: var(--text-primary);
}

.discrepancy-type {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.discrepancy-type.amount_mismatch {
  background: var(--warning-light);
  color: var(--warning);
}

.discrepancy-type.missing_transaction {
  background: var(--error-light);
  color: var(--error);
}

.discrepancy-details p {
  margin: 0.25rem 0;
  font-size: 0.9rem;
}

.discrepancy-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .analytics-overview {
    grid-template-columns: 1fr;
  }

  .filter-section {
    flex-direction: column;
  }

  .performance-grid {
    grid-template-columns: 1fr;
  }

  .gateway-metrics {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>