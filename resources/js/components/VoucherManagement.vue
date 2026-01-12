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
          @click="showAdvancedGenerateModal = true"
          class="btn btn-primary"
          :disabled="isGenerating"
        >
          <i class="icon-settings"></i>
          Advanced Generate
        </button>
        <button 
          @click="showBulkGenerateModal = true"
          class="btn btn-primary"
          :disabled="isGenerating"
        >
          <i class="icon-plus"></i>
          Bulk Generate
        </button>
        <button 
          @click="showRouterModal = true"
          class="btn btn-secondary"
        >
          <i class="icon-router"></i>
          Add Router
        </button>
        <button 
          @click="showAnalyticsModal = true"
          class="btn btn-secondary"
        >
          <i class="icon-bar-chart"></i>
          Analytics
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
      :data="safeVouchers"
      :columns="voucherColumns"
      :loading="isLoading"
      :pagination="pagination"
      :showActions="true"
      @page-change="handlePageChange"
      @sort="handleSort"
      @export="handleExport"
    >
      <template #cell(status)="{ row }">
        <span 
          v-if="row"
          class="status-badge" 
          :class="getStatusClass(row.status, row.expires_at)"
        >
          {{ getStatusText(row.status, row.expires_at) }}
        </span>
      </template>
      
      <template #cell(customer)="{ row }">
        <div v-if="row && row.customer" class="customer-info">
          <div class="customer-name">{{ row.customer?.name || 'N/A' }}</div>
          <div class="customer-phone">{{ row.customer?.phone || 'N/A' }}</div>
        </div>
        <span v-else class="text-muted">No customer</span>
      </template>
      
      <template #cell(price)="{ row }">
        {{ row ? formatCurrency(row.price) : 'N/A' }}
      </template>
      
      <template #cell(remaining_time)="{ row }">
        <div v-if="row" class="time-info">
          <div class="remaining-time">{{ row.remaining_time_formatted || 'Expired' }}</div>
          <div class="expires-at">{{ formatDate(row.expires_at) }}</div>
        </div>
      </template>
      
      <template #actions-cell="{ row }">
        <div v-if="row" class="action-buttons">
          <button 
            @click="viewVoucherDetails(row)"
            class="btn btn-sm btn-outline"
            title="View Details"
          >
            View
          </button>
          <button 
            v-if="row.customer?.phone"
            @click="resendSms(row)"
            class="btn btn-sm btn-outline"
            title="Resend SMS"
            :disabled="isSendingSms[row.id]"
          >
            SMS
          </button>
          <button 
            v-if="row.status === 'active'"
            @click="openTransferModal(row)"
            class="btn btn-sm btn-outline"
            title="Transfer Voucher"
          >
            Transfer
          </button>
          <button 
            v-if="row.status === 'active'"
            @click="openRefundModal(row)"
            class="btn btn-sm btn-warning"
            title="Refund Voucher"
          >
            Refund
          </button>
          <button 
            v-if="row.status === 'active'"
            @click="disableVoucher(row)"
            class="btn btn-sm btn-danger"
            title="Disable Voucher"
          >
            Disable
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
                <span>{{ selectedVoucher.customer?.name || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <label>Phone:</label>
                <span>{{ selectedVoucher.customer?.phone || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <label>Email:</label>
                <span>{{ selectedVoucher.customer?.email || 'N/A' }}</span>
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

    <!-- Advanced Generate Modal -->
    <Modal 
      v-if="showAdvancedGenerateModal"
      @close="showAdvancedGenerateModal = false"
      title="Advanced Voucher Generation"
      size="large"
    >
      <div class="advanced-generate-form">
        <div class="form-section">
          <h3>Voucher Configuration</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Profile</label>
              <select v-model="advancedForm.profile">
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
                v-model.number="advancedForm.validity_hours"
                type="number"
                min="1"
                max="8760"
                placeholder="Validity in hours"
              />
            </div>
            <div class="form-group">
              <label>Price</label>
              <input
                v-model.number="advancedForm.price"
                type="number"
                min="0"
                placeholder="Price"
              />
            </div>
            <div class="form-group">
              <label>Data Limit (MB)</label>
              <input
                v-model.number="advancedForm.data_limit_mb"
                type="number"
                min="1"
                placeholder="Data limit in MB (optional)"
              />
            </div>
            <div class="form-group">
              <label>Code Prefix</label>
              <input
                v-model="advancedForm.code_prefix"
                type="text"
                maxlength="10"
                placeholder="Code prefix (e.g., BIL)"
              />
            </div>
            <div class="form-group">
              <label>Currency</label>
              <select v-model="advancedForm.currency">
                <option value="UGX">UGX</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3>Customer Information (Optional)</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Customer Name</label>
              <input
                v-model="advancedForm.customer_name"
                type="text"
                placeholder="Customer name"
              />
            </div>
            <div class="form-group">
              <label>Customer Phone</label>
              <input
                v-model="advancedForm.customer_phone"
                type="text"
                placeholder="Customer phone"
              />
            </div>
            <div class="form-group">
              <label>Customer Email</label>
              <input
                v-model="advancedForm.customer_email"
                type="email"
                placeholder="Customer email"
              />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3>Options</h3>
          <div class="form-options">
            <label class="checkbox-label">
              <input
                v-model="advancedForm.auto_activate"
                type="checkbox"
              />
              Auto-activate voucher
            </label>
            <label class="checkbox-label">
              <input
                v-model="advancedForm.send_sms"
                type="checkbox"
              />
              Send SMS notification
            </label>
          </div>
        </div>

        <div class="modal-actions">
          <button 
            @click="showAdvancedGenerateModal = false"
            class="btn btn-secondary"
            :disabled="isGenerating"
          >
            Cancel
          </button>
          <button 
            @click="generateAdvancedVoucher"
            class="btn btn-primary"
            :disabled="isGenerating"
          >
            <i v-if="isGenerating" class="icon-loader spinning"></i>
            {{ isGenerating ? 'Generating...' : 'Generate Voucher' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Transfer Modal -->
    <Modal 
      v-if="showTransferModal && selectedVoucher"
      @close="showTransferModal = false"
      :title="`Transfer Voucher - ${selectedVoucher.code}`"
    >
      <div class="transfer-form">
        <div class="form-group">
          <label>New Customer ID</label>
          <input
            v-model="transferForm.new_customer_id"
            type="text"
            placeholder="Enter customer ID"
          />
        </div>
        <div class="form-group">
          <label>Reason for Transfer</label>
          <textarea
            v-model="transferForm.reason"
            placeholder="Enter reason for transfer"
            rows="3"
          ></textarea>
        </div>

        <div class="modal-actions">
          <button 
            @click="showTransferModal = false"
            class="btn btn-secondary"
          >
            Cancel
          </button>
          <button 
            @click="transferVoucher"
            class="btn btn-primary"
          >
            Transfer Voucher
          </button>
        </div>
      </div>
    </Modal>

    <!-- Refund Modal -->
    <Modal 
      v-if="showRefundModal && selectedVoucher"
      @close="showRefundModal = false"
      :title="`Refund Voucher - ${selectedVoucher.code}`"
    >
      <div class="refund-form">
        <div class="form-group">
          <label>Refund Amount</label>
          <input
            v-model.number="refundForm.refund_amount"
            type="number"
            min="0"
            :max="selectedVoucher.price"
            placeholder="Refund amount"
          />
          <small>Original price: {{ formatCurrency(selectedVoucher.price) }}</small>
        </div>
        <div class="form-group">
          <label>Refund Method</label>
          <select v-model="refundForm.method">
            <option value="manual">Manual Refund</option>
            <option value="automatic">Automatic Refund</option>
          </select>
        </div>
        <div class="form-group">
          <label>Reason for Refund</label>
          <textarea
            v-model="refundForm.reason"
            placeholder="Enter reason for refund"
            rows="3"
            required
          ></textarea>
        </div>
        <div class="form-options">
          <label class="checkbox-label">
            <input
              v-model="refundForm.allow_expired_refund"
              type="checkbox"
            />
            Allow refund for expired voucher
          </label>
        </div>

        <div class="modal-actions">
          <button 
            @click="showRefundModal = false"
            class="btn btn-secondary"
          >
            Cancel
          </button>
          <button 
            @click="refundVoucher"
            class="btn btn-danger"
          >
            Process Refund
          </button>
        </div>
      </div>
    </Modal>

    <!-- Analytics Modal -->
    <Modal 
      v-if="showAnalyticsModal"
      @close="showAnalyticsModal = false"
      title="Voucher Analytics"
      size="extra-large"
    >
      <div class="analytics-content">
        <div v-if="analyticsData.overview" class="analytics-grid">
          <div class="analytics-section">
            <h3>Overview</h3>
            <div class="stats-mini-grid">
              <div class="stat-mini">
                <span class="value">{{ analyticsData.overview.total_vouchers }}</span>
                <span class="label">Total Vouchers</span>
              </div>
              <div class="stat-mini">
                <span class="value">{{ analyticsData.overview.active_vouchers }}</span>
                <span class="label">Active</span>
              </div>
              <div class="stat-mini">
                <span class="value">{{ formatCurrency(analyticsData.overview.total_revenue) }}</span>
                <span class="label">Revenue</span>
              </div>
            </div>
          </div>

          <div class="analytics-section">
            <h3>Profile Breakdown</h3>
            <div class="profile-list">
              <div 
                v-for="profile in analyticsData.profile_breakdown" 
                :key="profile.profile"
                class="profile-item"
              >
                <span class="profile-name">{{ profile.profile }}</span>
                <span class="profile-count">{{ profile.count }} vouchers</span>
                <span class="profile-revenue">{{ formatCurrency(profile.revenue) }}</span>
              </div>
            </div>
          </div>

          <div class="analytics-section">
            <h3>Customer Insights</h3>
            <div class="customer-stats">
              <p>Vouchers with customers: {{ analyticsData.customer_insights?.vouchers_with_customers || 0 }}</p>
              <p>Vouchers without customers: {{ analyticsData.customer_insights?.vouchers_without_customers || 0 }}</p>
            </div>
          </div>
        </div>
        <div v-else class="loading-analytics">
          Loading analytics...
        </div>
      </div>
    </Modal>

    <!-- Router Add Modal -->
    <RouterAddModal 
      :show="showRouterModal"
      @close="showRouterModal = false"
      @success="handleRouterAdded"
    />

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
import RouterAddModal from '@/components/common/RouterAddModal.vue'
import api from '@/api/client'
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

// Computed property to ensure vouchers is always a valid array
const safeVouchers = computed(() => {
  return Array.isArray(vouchers.value) ? vouchers.value.filter(v => v && typeof v === 'object') : []
})

// Loading states
const isLoading = ref(false)
const isGenerating = ref(false)
const isSendingSms = ref<Record<string, boolean>>({})

// Modal states
const showBulkGenerateModal = ref(false)
const showAdvancedGenerateModal = ref(false)
const showAnalyticsModal = ref(false)
const showRouterModal = ref(false)
const showTransferModal = ref(false)
const showRefundModal = ref(false)
const showDetailsModal = ref(false)
const selectedVoucher = ref<Voucher | null>(null)
const voucherUsage = ref<VoucherUsage | null>(null)

// Advanced generation form
const advancedForm = reactive({
  profile: '1GB-DAILY',
  validity_hours: 24,
  price: 5000,
  data_limit_mb: 1024,
  currency: 'UGX',
  code_prefix: 'BIL',
  auto_activate: true,
  send_sms: false,
  customer_id: '',
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  metadata: {}
})

// Transfer form
const transferForm = reactive({
  new_customer_id: '',
  reason: ''
})

// Refund form
const refundForm = reactive({
  refund_amount: 0,
  reason: '',
  method: 'manual',
  allow_expired_refund: false
})

// Analytics data
const analyticsData = ref<any>({})

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
  { key: 'code', title: 'Code', sortable: true },
  { key: 'profile', title: 'Profile', sortable: true },
  { key: 'status', title: 'Status', sortable: true },
  { key: 'customer', title: 'Customer' },
  { key: 'price', title: 'Price', sortable: true },
  { key: 'remaining_time', title: 'Remaining Time' },
  { key: 'created_at', title: 'Created', sortable: true }
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
    
    // Check if user is authenticated
    const authToken = localStorage.getItem('auth_token');
    if (!authToken) {
      console.log('No auth token found for vouchers, redirecting to login');
      window.location.href = '/login';
      return;
    }
    
    console.log('Loading vouchers with token:', authToken.substring(0, 20) + '...');
    
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
    
    const response = await api.get(`/api/v1/vouchers?${params}`)
    
    console.log('Vouchers API response:', response.data);
    
    if (response.data.success) {
      // Filter out any undefined or null entries and ensure each voucher has required properties
      const rawVouchers = response.data.data || []
      vouchers.value = rawVouchers.filter(voucher => 
        voucher && 
        typeof voucher === 'object' && 
        voucher.id && 
        voucher.code
      )
      
      pagination.value = response.data.pagination || {}
      console.log('Loaded vouchers:', vouchers.value.length, 'out of', rawVouchers.length);
      
      // Load statistics separately since it's not included in the vouchers response
      await loadStatistics()
    } else {
      throw new Error(response.data.message || 'Failed to load vouchers');
    }
  } catch (error: any) {
    console.error('Failed to load vouchers:', error)
    
    // If unauthorized, redirect to login
    if (error.response?.status === 401) {
      console.log('Unauthorized vouchers request, clearing auth and redirecting to login');
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('tenant');
      window.location.href = '/login';
      return;
    }
    
    // For testing, use mock voucher data
    console.log('Using mock voucher data for testing');
    vouchers.value = [
      {
        id: '1',
        code: 'TEST-001',
        profile: 'daily_1gb',
        status: 'active',
        price: 5000,
        currency: 'UGX',
        expires_at: '2026-01-13T18:00:00Z',
        created_at: '2026-01-12T18:00:00Z',
        remaining_time_formatted: '23 hours 30 minutes',
        customer: {
          id: '1',
          name: 'John Doe',
          phone: '+256700123456',
          email: 'john@example.com'
        },
        payment: {
          id: '1',
          transaction_id: 'TXN-001',
          amount: 5000,
          currency: 'UGX'
        }
      },
      {
        id: '2',
        code: 'TEST-002',
        profile: 'weekly_5gb',
        status: 'expired',
        price: 15000,
        currency: 'UGX',
        expires_at: '2026-01-10T18:00:00Z',
        created_at: '2026-01-05T18:00:00Z',
        remaining_time_formatted: 'Expired',
        customer: {
          id: '2',
          name: 'Jane Smith',
          phone: '+256700654321',
          email: 'jane@example.com'
        },
        payment: {
          id: '2',
          transaction_id: 'TXN-002',
          amount: 15000,
          currency: 'UGX'
        }
      }
    ];
    
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 2
    };
    
    // Load mock statistics
    await loadStatistics()
    
    appStore.addNotification({
      type: 'warning',
      message: 'Using mock data for testing - API connection failed'
    })
  } finally {
    isLoading.value = false
  }
}

const loadStatistics = async () => {
  try {
    console.log('Loading voucher statistics...');
    const response = await api.get('/api/v1/vouchers/statistics')
    console.log('Statistics API response:', response.data);
    
    if (response.data.success) {
      statistics.value = response.data.data
      console.log('Loaded statistics:', statistics.value);
    } else {
      throw new Error(response.data.message || 'Failed to load statistics');
    }
  } catch (error: any) {
    console.error('Failed to load statistics:', error)
    
    // If unauthorized, redirect to login
    if (error.response?.status === 401) {
      console.log('Unauthorized statistics request, clearing auth and redirecting to login');
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('tenant');
      window.location.href = '/login';
      return;
    }
    
    // For now, use mock data to test frontend functionality
    console.log('Using mock statistics data for testing');
    statistics.value = {
      active_vouchers: 150,
      expired_vouchers: 45,
      total_revenue: 2500000,
      today_vouchers: 12,
      total_vouchers: 195,
      unused_vouchers: 30,
      used_vouchers: 120,
      disabled_vouchers: 25,
    };
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
    
    const response = await api.post('/api/v1/vouchers/batch-generate', {
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
    const response = await api.get(`/api/v1/vouchers/${voucher.code}`)
    if (response.data.success) {
      selectedVoucher.value = response.data.data
      // Set usage data if available
      if (response.data.data.usage) {
        voucherUsage.value = {
          voucher: response.data.data,
          usage: response.data.data.usage,
          customer: response.data.data.customer,
          payment: response.data.data.payment
        }
      }
    }
  } catch (error) {
    console.error('Failed to load voucher details:', error)
  }
}

const resendSms = async (voucher: Voucher) => {
  try {
    isSendingSms.value[voucher.id] = true
    
    const response = await api.post(`/api/v1/vouchers/${voucher.code}/resend-sms`)
    
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
    const response = await api.post(`/api/v1/vouchers/${voucher.code}/disable`)
    
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

// Advanced voucher generation
const generateAdvancedVoucher = async () => {
  try {
    isGenerating.value = true
    
    const response = await api.post('/api/v1/vouchers/generate-advanced', advancedForm)
    
    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'Advanced voucher generated successfully'
      })
      
      // Refresh voucher list
      await loadVouchers()
      await loadStatistics()
      
      showAdvancedGenerateModal.value = false
      resetAdvancedForm()
    }
  } catch (error) {
    console.error('Failed to generate advanced voucher:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to generate advanced voucher'
    })
  } finally {
    isGenerating.value = false
  }
}

const resetAdvancedForm = () => {
  Object.assign(advancedForm, {
    profile: '1GB-DAILY',
    validity_hours: 24,
    price: 5000,
    data_limit_mb: 1024,
    currency: 'UGX',
    code_prefix: 'BIL',
    auto_activate: true,
    send_sms: false,
    customer_id: '',
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    metadata: {}
  })
}

// Transfer voucher
const openTransferModal = (voucher: Voucher) => {
  selectedVoucher.value = voucher
  transferForm.new_customer_id = ''
  transferForm.reason = ''
  showTransferModal.value = true
}

const transferVoucher = async () => {
  if (!selectedVoucher.value) return
  
  try {
    const response = await api.post(`/api/v1/vouchers/${selectedVoucher.value.code}/transfer`, transferForm)
    
    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'Voucher transferred successfully'
      })
      
      // Refresh voucher list
      await loadVouchers()
      showTransferModal.value = false
    }
  } catch (error) {
    console.error('Failed to transfer voucher:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to transfer voucher'
    })
  }
}

// Refund voucher
const openRefundModal = (voucher: Voucher) => {
  selectedVoucher.value = voucher
  refundForm.refund_amount = voucher.price
  refundForm.reason = ''
  refundForm.method = 'manual'
  refundForm.allow_expired_refund = false
  showRefundModal.value = true
}

const refundVoucher = async () => {
  if (!selectedVoucher.value) return
  
  try {
    const response = await api.post(`/api/v1/vouchers/${selectedVoucher.value.code}/refund`, refundForm)
    
    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        message: 'Voucher refunded successfully'
      })
      
      // Refresh voucher list
      await loadVouchers()
      showRefundModal.value = false
    }
  } catch (error) {
    console.error('Failed to refund voucher:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to refund voucher'
    })
  }
}

// Analytics
const loadAnalytics = async () => {
  try {
    const response = await api.get('/api/v1/vouchers/analytics', {
      params: {
        start_date: filters.start_date,
        end_date: filters.end_date,
        profile: filters.profile,
        status: filters.status
      }
    })
    
    if (response.data.success) {
      analyticsData.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load analytics:', error)
    appStore.addNotification({
      type: 'error',
      message: 'Failed to load analytics'
    })
  }
}

const showAnalytics = async () => {
  showAnalyticsModal.value = true
  await loadAnalytics()
}

// Router management
const handleRouterAdded = (router: any) => {
  console.log('Router added successfully:', router)
  appStore.addNotification({
    type: 'success',
    message: `Router "${router.name}" has been added successfully!`
  })
}

// Utility functions
const getStatusClass = (status: string, expiresAt?: string) => {
  if (!status) return 'status-unknown'
  
  if (status === 'expired' || (expiresAt && new Date(expiresAt) < new Date())) {
    return 'status-expired'
  }
  if (status === 'active') return 'status-active'
  if (status === 'disabled') return 'status-disabled'
  return 'status-unknown'
}

const getStatusText = (status: string, expiresAt?: string) => {
  if (!status) return 'Unknown'
  
  if (status === 'expired' || (expiresAt && new Date(expiresAt) < new Date())) {
    return 'Expired'
  }
  if (status === 'active') return 'Active'
  if (status === 'disabled') return 'Disabled'
  return status
}

const formatCurrency = (amount: number) => {
  if (typeof amount !== 'number' || isNaN(amount)) {
    return 'UGX 0'
  }
  
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (date: string) => {
  if (!date) return 'N/A'
  
  try {
    return new Intl.DateTimeFormat('en-UG', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date(date))
  } catch (error) {
    console.warn('Invalid date format:', date)
    return 'Invalid Date'
  }
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
onMounted(async () => {
  // Wait a bit for app initialization to complete
  await new Promise(resolve => setTimeout(resolve, 100));
  
  console.log('VoucherManagement mounted, loading data...');
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
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.action-buttons .btn {
  flex-shrink: 0;
  min-width: 60px;
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
  padding: 6px 12px;
  font-size: 12px;
  min-width: 60px;
  text-align: center;
  white-space: nowrap;
}

.text-muted {
  color: var(--text-secondary);
}

.advanced-generate-form,
.transfer-form,
.refund-form {
  padding: 20px;
}

.form-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin: 0;
}

.analytics-content {
  padding: 20px;
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.analytics-section h3 {
  margin: 0 0 16px 0;
  color: var(--text-primary);
  font-size: 16px;
  font-weight: 600;
}

.stats-mini-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 16px;
}

.stat-mini {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  background: var(--bg-secondary);
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.stat-mini .value {
  font-size: 20px;
  font-weight: 600;
  color: var(--text-primary);
}

.stat-mini .label {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 4px;
}

.profile-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.profile-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: var(--bg-secondary);
  border-radius: 6px;
  border: 1px solid var(--border-color);
}

.profile-name {
  font-weight: 500;
  color: var(--text-primary);
}

.profile-count {
  font-size: 14px;
  color: var(--text-secondary);
}

.profile-revenue {
  font-weight: 500;
  color: var(--primary-color);
}

.customer-stats {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.customer-stats p {
  margin: 0;
  padding: 8px 12px;
  background: var(--bg-secondary);
  border-radius: 6px;
  border: 1px solid var(--border-color);
}

.loading-analytics {
  text-align: center;
  padding: 40px;
  color: var(--text-secondary);
}

.btn-warning {
  background: var(--warning-color);
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background: var(--warning-hover);
}

/* Responsive Design */
@media (max-width: 768px) {
  .action-buttons {
    gap: 4px;
  }
  
  .action-buttons .btn {
    min-width: 50px;
    padding: 4px 6px;
    font-size: 11px;
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
  
  .filters-section {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-box {
    min-width: auto;
    width: 100%;
  }
  
  .filters {
    justify-content: stretch;
  }
  
  .filters select,
  .filters input {
    flex: 1;
    min-width: auto;
  }
}

@media (max-width: 480px) {
  .action-buttons {
    gap: 2px;
  }
  
  .action-buttons .btn {
    min-width: 45px;
    padding: 3px 5px;
    font-size: 10px;
  }
  
  .header-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .header-actions .btn {
    width: 100%;
  }
}
</style>