<template>
  <div class="voucher-management">
    <!-- Header Section -->
    <div class="header-section">
      <div class="header-content">
        <h1 class="page-title">Voucher Management</h1>
        <p class="page-description">Manage internet vouchers with real-time tracking and analytics</p>
      </div>
      <div class="header-actions">
        <button 
          @click="showBulkGenerateModal = true"
          class="btn btn-primary"
          :disabled="isGenerating"
        >
          <i class="icon-plus"></i>
          Generate Vouchers
        </button>
        <button 
          @click="refreshData"
          class="btn btn-secondary"
          :disabled="isLoading"
        >
          <i class="icon-refresh" :class="{ 'spinning': isLoading }"></i>
          Refresh
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon active">
          <i class="icon-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.active_vouchers || 0 }}</h3>
          <p>Active Vouchers</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon expired">
          <i class="icon-clock"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.expired_vouchers || 0 }}</h3>
          <p>Expired Vouchers</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon revenue">
          <i class="icon-dollar-sign"></i>
        </div>
        <div class="stat-content">
          <h3>{{ formatCurrency(statistics.total_revenue || 0) }}</h3>
          <p>Total Revenue</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon today">
          <i class="icon-calendar"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.today_vouchers || 0 }}</h3>
          <p>Today's Vouchers</p>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-box">
        <i class="icon-search"></i>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search vouchers by code, customer name, or phone..."
          @input="debouncedSearch"
        />
      </div>
      <div class="filters">
        <select v-model="filters.status" @change="applyFilters">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="expired">Expired</option>
          <option value="disabled">Disabled</option>
        </select>
        <select v-model="filters.profile" @change="applyFilters">
          <option value="">All Profiles</option>
          <option value="1GB-DAILY">1GB Daily</option>
          <option value="5GB-WEEKLY">5GB Weekly</option>
          <option value="20GB-MONTHLY">20GB Monthly</option>
          <option value="UNLIMITED-DAILY">Unlimited Daily</option>
          <option value="UNLIMITED-WEEKLY">Unlimited Weekly</option>
          <option value="UNLIMITED-MONTHLY">Unlimited Monthly</option>
        </select>
        <input
          v-model="filters.start_date"
          type="date"
          @change="applyFilters"
        />
        <input
          v-model="filters.end_date"
          type="date"
          @change="applyFilters"
        />
      </div>
    </div>

    <!-- Vouchers Data Table -->
    <DataTable
      :data="vouchers"
      :columns="voucherColumns"
      :loading="isLoading"
      :pagination="pagination"
      @page-change="handlePageChange"
      @sort="handleSort"
      @export="handleExport"
    >
      <template #status="{ row }">
        <span 
          class="status-badge" 
          :class="getStatusClass(row.status, row.expires_at)"
        >
          {{ getStatusText(row.status, row.expires_at) }}
        </span>
      </template>
      
      <template #customer="{ row }">
        <div v-if="row.customer" class="customer-info">
          <div class="customer-name">{{ row.customer.name }}</div>
          <div class="customer-phone">{{ row.customer.phone }}</div>
        </div>
        <span v-else class="text-muted">No customer</span>
      </template>
      
      <template #price="{ row }">
        {{ formatCurrency(row.price) }}
      </template>
      
      <template #remaining_time="{ row }">
        <div class="time-info">
          <div class="remaining-time">{{ row.remaining_time_formatted || 'Expired' }}</div>
          <div class="expires-at">{{ formatDate(row.expires_at) }}</div>
        </div>
      </template>
      
      <template #actions="{ row }">
        <div class="action-buttons">
          <button 
            @click="viewVoucherDetails(row)"
            class="btn btn-sm btn-outline"
            title="View Details"
          >
            <i class="icon-eye"></i>
          </button>
          <button 
            v-if="row.customer && row.customer.phone"
            @click="resendSms(row)"
            class="btn btn-sm btn-outline"
            title="Resend SMS"
            :disabled="isSendingSms[row.id]"
          >
            <i class="icon-message-circle" :class="{ 'spinning': isSendingSms[row.id] }"></i>
          </button>
          <button 
            v-if="row.status === 'active'"
            @click="disableVoucher(row)"
            class="btn btn-sm btn-danger"
            title="Disable Voucher"
          >
            <i class="icon-x-circle"></i>
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Bulk Generate Modal -->
    <Modal 
      v-if="showBulkGenerateModal"
      @close="showBulkGenerateModal = false"
      title="Bulk Generate Vouchers"
      size="large"
    >
      <div class="bulk-generate-form">
        <div class="form-section">
          <h3>Voucher Configuration</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Quantity</label>
              <input
                v-model.number="bulkForm.quantity"
                type="number"
                min="1"
                max="100"
                placeholder="Number of vouchers"
              />
            </div>
            <div class="form-group">
              <label>Profile</label>
              <select v-model="bulkForm.profile">
                <option value="1GB-DAILY">1GB Daily</option>
                <option value="5GB-WEEKLY">5GB Weekly</option>
                <option value="20GB-MONTHLY">20GB Monthly</option>
                <option value="UNLIMITED-DAILY">Unlimited Daily</option>
                <option value="UNLIMITED-WEEKLY">Unlimited Weekly</option>
                <option value="UNLIMITED-MONTHLY">Unlimited Monthly</option>
              </select>
            </div>
            <div class="form-group">
              <label>Validity (Hours)</label>
              <input
                v-model.number="bulkForm.validity_hours"
                type="number"
                min="1"
                max="720"
                placeholder="Validity in hours"
              />
            </div>
            <div class="form-group">
              <label>Price (UGX)</label>
              <input
                v-model.number="bulkForm.price"
                type="number"
                min="0"
                placeholder="Price per voucher"
              />
            </div>
            <div class="form-group">
              <label>Data Limit (MB)</label>
              <input
                v-model.number="bulkForm.data_limit_mb"
                type="number"
                min="1"
                placeholder="Data limit in MB (optional)"
              />
            </div>
          </div>
        </div>

        <!-- Progress Section (shown during generation) -->
        <div v-if="isGenerating" class="progress-section">
          <h3>Generation Progress</h3>
          <div class="progress-bar">
            <div 
              class="progress-fill" 
              :style="{ width: generationProgress + '%' }"
            ></div>
          </div>
          <div class="progress-stats">
            <span>{{ generationStats.successful || 0 }} successful</span>
            <span>{{ generationStats.failed || 0 }} failed</span>
            <span>{{ generationStats.total || 0 }} total</span>
          </div>
        </div>

        <div class="modal-actions">
          <button 
            @click="showBulkGenerateModal = false"
            class="btn btn-secondary"
            :disabled="isGenerating"
          >
            Cancel
          </button>
          <button 
            @click="generateBulkVouchers"
            class="btn btn-primary"
            :disabled="isGenerating || !isValidBulkForm"
          >
            <i v-if="isGenerating" class="icon-loader spinning"></i>
            {{ isGenerating ? 'Generating...' : 'Generate Vouchers' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Voucher Details Modal -->
    <Modal 
      v-if="showDetailsModal && selectedVoucher"
      @close="showDetailsModal = false"
      :title="`Voucher Details - ${selectedVoucher.code}`"
      size="large"
    >
      <div class="voucher-details">
        <div class="details-grid">
          <div class="detail-section">
            <h3>Voucher Information</h3>
            <div class="detail-item">
              <label>Code:</label>
              <span class="code">{{ selectedVoucher.code }}</span>
            </div>
            <div class="detail-item">
              <label>Password:</label>
              <span class="password">{{ selectedVoucher.password || 'N/A' }}</span>
            </div>
            <div class="detail-item">
              <label>Profile:</label>
              <span>{{ selectedVoucher.profile }}</span>
            </div>
            <div class="detail-item">
              <label>Status:</label>
              <span 
                class="status-badge" 
                :class="getStatusClass(selectedVoucher.status, selectedVoucher.expires_at)"
              >
                {{ getStatusText(selectedVoucher.status, selectedVoucher.expires_at) }}
              </span>
            </div>
            <div class="detail-item">
              <label>Price:</label>
              <span>{{ formatCurrency(selectedVoucher.price) }}</span>
            </div>
            <div class="detail-item">
              <label>Validity:</label>
              <span>{{ selectedVoucher.validity_hours }} hours</span>
            </div>
            <div class="detail-item">
              <label>Data Limit:</label>
              <span>{{ selectedVoucher.data_limit_formatted || 'Unlimited' }}</span>
            </div>
          </div>

          <div class="detail-section">
            <h3>Customer Information</h3>
            <div v-if="selectedVoucher.customer">
              <div class="detail-item">
                <label>Name:</label>
                <span>{{ selectedVoucher.customer.name }}</span>
              </div>
              <div class="detail-item">
                <label>Phone:</label>
                <span>{{ selectedVoucher.customer.phone }}</span>
              </div>
              <div class="detail-item">
                <label>Email:</label>
                <span>{{ selectedVoucher.customer.email || 'N/A' }}</span>
              </div>
            </div>
            <div v-else class="no-customer">
              No customer assigned
            </div>
          </div>

          <div class="detail-section">
            <h3>Payment Information</h3>
            <div v-if="selectedVoucher.payment">
              <div class="detail-item">
                <label>Transaction ID:</label>
                <span>{{ selectedVoucher.payment.transaction_id }}</span>
              </div>
              <div class="detail-item">
                <label>Amount:</label>
                <span>{{ formatCurrency(selectedVoucher.payment.amount) }}</span>
              </div>
              <div class="detail-item">
                <label>Paid At:</label>
                <span>{{ formatDate(selectedVoucher.payment.paid_at) }}</span>
              </div>
            </div>
            <div v-else class="no-payment">
              No payment information
            </div>
          </div>

          <div class="detail-section">
            <h3>Usage Information</h3>
            <div v-if="voucherUsage">
              <div class="detail-item">
                <label>Active Connections:</label>
                <span>{{ voucherUsage.usage.active_connections || 0 }}</span>
              </div>
              <div class="detail-item">
                <label>Data Used:</label>
                <span>{{ voucherUsage.usage.total_data_used_formatted || '0 B' }}</span>
              </div>
              <div class="detail-item" v-if="voucherUsage.usage.data_usage_percentage !== null">
                <label>Usage Percentage:</label>
                <span>{{ Math.round(voucherUsage.usage.data_usage_percentage) }}%</span>
              </div>
            </div>
            <div v-else class="loading-usage">
              Loading usage data...
            </div>
          </div>

          <div class="detail-section">
            <h3>Timeline</h3>
            <div class="timeline">
              <div class="timeline-item">
                <label>Created:</label>
                <span>{{ formatDate(selectedVoucher.created_at) }}</span>
              </div>
              <div class="timeline-item" v-if="selectedVoucher.activated_at">
                <label>Activated:</label>
                <span>{{ formatDate(selectedVoucher.activated_at) }}</span>
              </div>
              <div class="timeline-item" v-if="selectedVoucher.expires_at">
                <label>Expires:</label>
                <span>{{ formatDate(selectedVoucher.expires_at) }}</span>
              </div>
              <div class="timeline-item" v-if="selectedVoucher.sms_sent_at">
                <label>SMS Sent:</label>
                <span>{{ formatDate(selectedVoucher.sms_sent_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Loading Overlay -->
    <LoadingOverlay v-if="isLoading && vouchers.length === 0" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRealtimeStore } from '@/store/modules/realtime'
import { useAppStore } from '@/store/modules/app'
import DataTable from '@/components/common/DataTable.vue'
import Modal from '@/components/common/Modal.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'
import { api } from '@/api'
import { debounce } from 'lodash-es'

// Types
interface Voucher {
  id: string
  code: string
  password?: string
  profile: string
  validity_hours: number
  data_limit_mb?: number
  data_limit_formatted?: string
  price: number
  currency: string
  status: string
  activated_at?: string
  expires_at?: string
  sms_sent_at?: string
  created_at: string
  remaining_hours?: number
  remaining_time_formatted?: string
  customer?: {
    id: string
    name: string
    phone: string
    email?: string
  }
  payment?: {
    id: string
    transaction_id: string
    amount: number
    currency: string
    paid_at?: string
  }
}

interface VoucherUsage {
  voucher: any
  usage: {
    active_connections: number
    total_data_used_formatted: string
    data_usage_percentage?: number
  }
  customer?: any
  payment?: any
}

interface BulkForm {
  quantity: number
  profile: string
  validity_hours: number
  price: number
  data_limit_mb?: number
}

interface GenerationStats {
  total: number
  successful: number
  failed: number
}

// Stores
const realtimeStore = useRealtimeStore()
const appStore = useAppStore()

// Reactive data
const vouchers = ref<Voucher[]>([])
const statistics = ref<any>({})
const pagination = ref<any>({})
const searchQuery = ref('')
const filters = reactive({
  status: '',
  profile: '',
  start_date: '',
  end_date: ''
})

// Loading states
const isLoading = ref(false)
const isGenerating = ref(false)
const isSendingSms = ref<Record<string, boolean>>({})

// Modal states
const showBulkGenerateModal = ref(false)
const showDetailsModal = ref(false)
const selectedVoucher = ref<Voucher | null>(null)
const voucherUsage = ref<VoucherUsage | null>(null)

// Bulk generation
const bulkForm = reactive<BulkForm>({
  quantity: 10,
  profile: '1GB-DAILY',
  validity_hours: 24,
  price: 5000,
  data_limit_mb: 1024
})

const generationProgress = ref(0)
const generationStats = ref<GenerationStats>({
  total: 0,
  successful: 0,
  failed: 0
})

// Table columns configuration
const voucherColumns = [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'profile', label: 'Profile', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'customer', label: 'Customer' },
  { key: 'price', label: 'Price', sortable: true },
  { key: 'remaining_time', label: 'Remaining Time' },
  { key: 'created_at', label: 'Created', sortable: true },
  { key: 'actions', label: 'Actions', width: '120px' }
]

// Computed properties
const isValidBulkForm = computed(() => {
  return bulkForm.quantity > 0 && 
         bulkForm.profile && 
         bulkForm.validity_hours > 0 && 
         bulkForm.price >= 0
})

// Methods
const loadVouchers = async (page = 1) => {
  try {
    isLoading.value = true
    
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: '15'
    })
    
    // Add filters
    if (filters.status) params.append('status', filters.status)
    if (filters.profile) params.append('profile', filters.profile)
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)
    if (searchQuery.value) params.append('search', searchQuery.value)
    
    const response = await api.get(`/vouchers?${params}`)
    
    if (response.data.success) {
      vouchers.value = response.data.data.vouchers
      pagination.value = response.data.data.pagination
      statistics.value = response.data.data.summary
    }
  } catch (error) {
    console.error('Failed to load vouchers:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to load vouchers'
    })
  } finally {
    isLoading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const response = await api.get('/vouchers/statistics')
    if (response.data.success) {
      statistics.value = response.data.data.overall_statistics
    }
  } catch (error) {
    console.error('Failed to load statistics:', error)
  }
}

const generateBulkVouchers = async () => {
  try {
    isGenerating.value = true
    generationProgress.value = 0
    generationStats.value = { total: 0, successful: 0, failed: 0 }
    
    const batchData = [{
      quantity: bulkForm.quantity,
      profile: bulkForm.profile,
      validity_hours: bulkForm.validity_hours,
      price: bulkForm.price,
      data_limit_mb: bulkForm.data_limit_mb
    }]
    
    // Simulate progress updates
    const progressInterval = setInterval(() => {
      if (generationProgress.value < 90) {
        generationProgress.value += Math.random() * 10
      }
    }, 500)
    
    const response = await api.post('/vouchers/batch-generate', {
      vouchers: batchData
    })
    
    clearInterval(progressInterval)
    generationProgress.value = 100
    
    if (response.data.success) {
      generationStats.value = response.data.data
      
      appStore.addNotification({
        type: 'success',
        message: `Successfully generated ${response.data.data.successful} vouchers`
      })
      
      // Refresh voucher list
      await loadVouchers()
      await loadStatistics()
      
      // Close modal after a delay
      setTimeout(() => {
        showBulkGenerateModal.value = false
        resetBulkForm()
      }, 2000)
    }
  } catch (error) {
    console.error('Failed to generate vouchers:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to generate vouchers'
    })
  } finally {
    isGenerating.value = false
  }
}

const viewVoucherDetails = async (voucher: Voucher) => {
  selectedVoucher.value = voucher
  showDetailsModal.value = true
  voucherUsage.value = null
  
  // Load usage data
  try {
    const response = await api.get(`/vouchers/${voucher.code}`)
    if (response.data.success) {
      selectedVoucher.value = response.data.data.voucher
      voucherUsage.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load voucher details:', error)
  }
}

const resendSms = async (voucher: Voucher) => {
  try {
    isSendingSms.value[voucher.id] = true
    
    const response = await api.post(`/vouchers/${voucher.code}/resend-sms`)
    
    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'SMS sent successfully'
      })
    }
  } catch (error) {
    console.error('Failed to resend SMS:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to send SMS'
    })
  } finally {
    isSendingSms.value[voucher.id] = false
  }
}

const disableVoucher = async (voucher: Voucher) => {
  if (!confirm(`Are you sure you want to disable voucher ${voucher.code}?`)) {
    return
  }
  
  try {
    const response = await api.post(`/vouchers/${voucher.code}/disable`)
    
    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'Voucher disabled successfully'
      })
      
      // Refresh voucher list
      await loadVouchers()
    }
  } catch (error) {
    console.error('Failed to disable voucher:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to disable voucher'
    })
  }
}

const refreshData = async () => {
  await Promise.all([
    loadVouchers(),
    loadStatistics()
  ])
}

const applyFilters = () => {
  loadVouchers(1)
}

const debouncedSearch = debounce(() => {
  loadVouchers(1)
}, 500)

const handlePageChange = (page: number) => {
  loadVouchers(page)
}

const handleSort = (column: string, direction: string) => {
  // Implement sorting logic
  console.log('Sort:', column, direction)
}

const handleExport = (format: string) => {
  // Implement export logic
  console.log('Export:', format)
}

const resetBulkForm = () => {
  Object.assign(bulkForm, {
    quantity: 10,
    profile: '1GB-DAILY',
    validity_hours: 24,
    price: 5000,
    data_limit_mb: 1024
  })
  generationProgress.value = 0
  generationStats.value = { total: 0, successful: 0, failed: 0 }
}

// Utility functions
const getStatusClass = (status: string, expiresAt?: string) => {
  if (status === 'expired' || (expiresAt && new Date(expiresAt) < new Date())) {
    return 'status-expired'
  }
  if (status === 'active') return 'status-active'
  if (status === 'disabled') return 'status-disabled'
  return 'status-unknown'
}

const getStatusText = (status: string, expiresAt?: string) => {
  if (status === 'expired' || (expiresAt && new Date(expiresAt) < new Date())) {
    return 'Expired'
  }
  if (status === 'active') return 'Active'
  if (status === 'disabled') return 'Disabled'
  return status
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (date: string) => {
  if (!date) return 'N/A'
  return new Intl.DateTimeFormat('en-UG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(date))
}

// Real-time updates
const setupRealtimeUpdates = () => {
  // Listen for voucher events
  window.Echo?.channel('vouchers')
    .listen('VoucherActivated', (e: any) => {
      console.log('Voucher activated:', e.voucher)
      refreshData()
    })
    .listen('VoucherExpired', (e: any) => {
      console.log('Voucher expired:', e.voucher)
      refreshData()
    })
    .listen('VoucherGenerated', (e: any) => {
      console.log('Voucher generated:', e.voucher)
      refreshData()
    })
}

// Lifecycle
onMounted(() => {
  loadVouchers()
  loadStatistics()
  setupRealtimeUpdates()
})

onUnmounted(() => {
  // Clean up real-time listeners
  window.Echo?.leave('vouchers')
})
</script>

<style scoped>
.voucher-management {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 32px;
}

.header-content h1 {
  font-size: 28px;
  font-weight: 600;
  margin: 0 0 8px 0;
  color: var(--text-primary);
}

.header-content p {
  color: var(--text-secondary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-icon.active {
  background: var(--success-bg);
  color: var(--success-color);
}

.stat-icon.expired {
  background: var(--warning-bg);
  color: var(--warning-color);
}

.stat-icon.revenue {
  background: var(--primary-bg);
  color: var(--primary-color);
}

.stat-icon.today {
  background: var(--info-bg);
  color: var(--info-color);
}

.stat-content h3 {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 4px 0;
  color: var(--text-primary);
}

.stat-content p {
  color: var(--text-secondary);
  margin: 0;
  font-size: 14px;
}

.filters-section {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 300px;
}

.search-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
}

.search-box input {
  width: 100%;
  padding: 12px 12px 12px 40px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filters select,
.filters input {
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--bg-primary);
  color: var(--text-primary);
  min-width: 120px;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
}

.status-active {
  background: var(--success-bg);
  color: var(--success-color);
}

.status-expired {
  background: var(--warning-bg);
  color: var(--warning-color);
}

.status-disabled {
  background: var(--error-bg);
  color: var(--error-color);
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.customer-name {
  font-weight: 500;
  color: var(--text-primary);
}

.customer-phone {
  font-size: 12px;
  color: var(--text-secondary);
}

.time-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.remaining-time {
  font-weight: 500;
  color: var(--text-primary);
}

.expires-at {
  font-size: 12px;
  color: var(--text-secondary);
}

.action-buttons {
  display: flex;
  gap: 4px;
}

.bulk-generate-form {
  padding: 20px;
}

.form-section h3 {
  margin: 0 0 16px 0;
  color: var(--text-primary);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 500;
  color: var(--text-primary);
}

.form-group input,
.form-group select {
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.progress-section {
  margin: 24px 0;
  padding: 20px;
  background: var(--bg-tertiary);
  border-radius: 8px;
}

.progress-section h3 {
  margin: 0 0 12px 0;
  color: var(--text-primary);
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: var(--border-color);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 12px;
}

.progress-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.3s ease;
}

.progress-stats {
  display: flex;
  gap: 16px;
  font-size: 14px;
  color: var(--text-secondary);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border-color);
}

.voucher-details {
  padding: 20px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.detail-section h3 {
  margin: 0 0 16px 0;
  color: var(--text-primary);
  font-size: 16px;
  font-weight: 600;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--border-light);
}

.detail-item:last-child {
  border-bottom: none;
}

.detail-item label {
  font-weight: 500;
  color: var(--text-secondary);
}

.detail-item span {
  color: var(--text-primary);
}

.code, .password {
  font-family: monospace;
  background: var(--bg-tertiary);
  padding: 2px 6px;
  border-radius: 4px;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.timeline-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}

.no-customer,
.no-payment,
.loading-usage {
  color: var(--text-secondary);
  font-style: italic;
  text-align: center;
  padding: 20px;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
  background: var(--primary-hover);
}

.btn-secondary {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-tertiary);
}

.btn-outline {
  background: transparent;
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover:not(:disabled) {
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.btn-danger {
  background: var(--error-color);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: var(--error-hover);
}

.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
}

.text-muted {
  color: var(--text-secondary);
}
</style>