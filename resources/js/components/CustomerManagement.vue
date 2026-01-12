<template>
  <div class="customer-management">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">Customer Management</h1>
        <p class="page-description">Manage customer accounts, payments, and service plans</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="showCreateCustomerModal = true">
          <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Add Customer
        </button>
      </div>
    </div>

    <!-- Customer Data Table -->
    <DataTable
      :data="customers"
      :columns="customerColumns"
      :loading="loading"
      :show-search="true"
      :show-filters="true"
      :show-export="true"
      :show-refresh="true"
      :show-actions="true"
      :show-view-action="true"
      :show-edit-action="true"
      :show-delete-action="true"
      :selectable="true"
      :filters="customerFilters"
      search-placeholder="Search customers by name, email, or phone..."
      empty-text="No customers found"
      @view="viewCustomer"
      @edit="editCustomer"
      @delete="confirmDeleteCustomer"
      @refresh="loadCustomers"
      @export="exportCustomers"
      @selection-change="handleSelectionChange"
    >
      <!-- Custom cell renderers -->
      <template #cell(status)="{ value }">
        <span class="status-badge" :class="`status-${value}`">
          {{ formatStatus(value) }}
        </span>
      </template>

      <template #cell(location)="{ value }">
        <div v-if="value" class="location-info">
          <span class="location-text">{{ value.region }}, {{ value.district }}</span>
          <button 
            v-if="value.coordinates" 
            class="location-btn"
            @click="showLocationOnMap(value.coordinates)"
            title="View on map"
          >
            <svg class="location-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        </div>
        <span v-else class="text-muted">Not specified</span>
      </template>

      <template #cell(service_plan)="{ row }">
        <div v-if="row.service_plan" class="service-plan-info">
          <span class="plan-name">{{ row.service_plan.name }}</span>
          <span class="plan-price">{{ formatCurrency(row.service_plan.price) }}</span>
        </div>
        <span v-else class="text-muted">No plan assigned</span>
      </template>

      <template #cell(last_payment)="{ row }">
        <div v-if="row.last_payment" class="payment-info">
          <span class="payment-amount">{{ formatCurrency(row.last_payment.amount) }}</span>
          <span class="payment-date">{{ formatDate(row.last_payment.created_at) }}</span>
        </div>
        <span v-else class="text-muted">No payments</span>
      </template>

      <!-- Bulk actions -->
      <template #bulk-actions="{ selectedRows }">
        <button class="bulk-action-btn" @click="bulkSuspendCustomers(selectedRows)">
          <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Suspend
        </button>
        <button class="bulk-action-btn" @click="bulkActivateCustomers(selectedRows)">
          <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-9 5a9 9 0 1118 0 9 9 0 01-18 0z" />
          </svg>
          Activate
        </button>
        <button class="bulk-action-btn danger" @click="bulkDeleteCustomers(selectedRows)">
          <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          Delete
        </button>
      </template>
    </DataTable>

    <!-- Customer Detail Modal -->
    <Modal
      v-if="showCustomerDetail"
      :show="showCustomerDetail"
      @close="closeCustomerDetail"
      size="large"
      title="Customer Details"
    >
      <CustomerDetailView
        v-if="selectedCustomer"
        :customer="selectedCustomer"
        :payment-history="customerPaymentHistory"
        :sms-history="customerSmsHistory"
        :loading-payments="loadingPayments"
        :loading-sms="loadingSms"
        @record-payment="recordPayment"
        @suspend-service="suspendService"
        @activate-service="activateService"
        @send-sms="sendSms"
        @refresh-payments="loadCustomerPaymentHistory"
        @refresh-sms="loadCustomerSmsHistory"
      />
    </Modal>

    <!-- Create/Edit Customer Modal -->
    <Modal
      v-if="showCreateCustomerModal || showEditCustomerModal"
      :show="showCreateCustomerModal || showEditCustomerModal"
      @close="closeCustomerModals"
      title="Customer Information"
    >
      <CustomerForm
        :customer="editingCustomer"
        :service-plans="servicePlans"
        :loading="savingCustomer"
        @save="saveCustomer"
        @cancel="closeCustomerModals"
      />
    </Modal>

    <!-- Delete Confirmation Modal -->
    <ConfirmDialog
      v-if="showDeleteConfirm"
      :show="showDeleteConfirm"
      title="Delete Customer"
      :message="`Are you sure you want to delete ${customerToDelete?.name}? This action cannot be undone.`"
      confirm-text="Delete"
      confirm-variant="danger"
      @confirm="deleteCustomer"
      @cancel="showDeleteConfirm = false"
    />

    <!-- Location Map Modal -->
    <Modal
      v-if="showLocationMap"
      :show="showLocationMap"
      @close="showLocationMap = false"
      title="Customer Location"
      size="large"
    >
      <div class="map-container">
        <!-- Map component would go here -->
        <div class="map-placeholder">
          <p>Map showing location: {{ selectedLocation?.lat }}, {{ selectedLocation?.lng }}</p>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/store/modules/app'
import { useRealtimeStore } from '@/store/modules/realtime'
import DataTable from '@/components/common/DataTable.vue'
import Modal from '@/components/common/Modal.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import CustomerDetailView from './CustomerDetailView.vue'
import CustomerForm from './CustomerForm.vue'
import api from '@/api/client'
import type { Customer, Payment, SmsLog, ApiResponse } from '@/types'

// Stores
const appStore = useAppStore()
const realtimeStore = useRealtimeStore()
const router = useRouter()

// Reactive state
const customers = ref<Customer[]>([])
const selectedCustomer = ref<Customer | null>(null)
const customerPaymentHistory = ref<Payment[]>([])
const customerSmsHistory = ref<SmsLog[]>([])
const servicePlans = ref([])
const editingCustomer = ref<Customer | null>(null)
const customerToDelete = ref<Customer | null>(null)
const selectedLocation = ref<{ lat: number; lng: number } | null>(null)

// Loading states
const loading = ref(false)
const loadingPayments = ref(false)
const loadingSms = ref(false)
const savingCustomer = ref(false)

// Modal states
const showCustomerDetail = ref(false)
const showCreateCustomerModal = ref(false)
const showEditCustomerModal = ref(false)
const showDeleteConfirm = ref(false)
const showLocationMap = ref(false)

// Table configuration
const customerColumns = [
  {
    key: 'name',
    title: 'Name',
    sortable: true,
    filterable: true,
    filterType: 'text'
  },
  {
    key: 'email',
    title: 'Email',
    sortable: true,
    filterable: true,
    filterType: 'text'
  },
  {
    key: 'phone',
    title: 'Phone',
    sortable: true,
    filterable: true,
    filterType: 'text'
  },
  {
    key: 'status',
    title: 'Status',
    sortable: true,
    filterable: true,
    filterType: 'select',
    filterOptions: [
      { value: 'active', label: 'Active' },
      { value: 'suspended', label: 'Suspended' },
      { value: 'inactive', label: 'Inactive' }
    ]
  },
  {
    key: 'location',
    title: 'Location',
    sortable: false,
    filterable: true,
    filterType: 'text'
  },
  {
    key: 'service_plan',
    title: 'Service Plan',
    sortable: false,
    filterable: false
  },
  {
    key: 'last_payment',
    title: 'Last Payment',
    sortable: true,
    filterable: false
  },
  {
    key: 'created_at',
    title: 'Created',
    sortable: true,
    format: 'date'
  }
]

const customerFilters = [
  {
    key: 'status',
    label: 'Status',
    type: 'select',
    options: [
      { value: 'active', label: 'Active' },
      { value: 'suspended', label: 'Suspended' },
      { value: 'inactive', label: 'Inactive' }
    ]
  },
  {
    key: 'has_service_plan',
    label: 'Service Plan',
    type: 'select',
    options: [
      { value: 'yes', label: 'Has Plan' },
      { value: 'no', label: 'No Plan' }
    ]
  },
  {
    key: 'created_after',
    label: 'Created After',
    type: 'date'
  }
]

// Methods
const loadCustomers = async () => {
  try {
    loading.value = true
    const response = await api.get('/api/v1/customers?include=service_plan,last_payment')
    customers.value = response.data.data
  } catch (error) {
    console.error('Failed to load customers:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to load customers'
    })
  } finally {
    loading.value = false
  }
}

const viewCustomer = async (customer: Customer) => {
  selectedCustomer.value = customer
  showCustomerDetail.value = true
  
  // Load customer details
  await Promise.all([
    loadCustomerPaymentHistory(customer.id),
    loadCustomerSmsHistory(customer.id)
  ])
}

const loadCustomerPaymentHistory = async (customerId: string) => {
  try {
    loadingPayments.value = true
    const response = await api.get(`/api/v1/customers/${customerId}/payments`)
    customerPaymentHistory.value = response.data.data
  } catch (error) {
    console.error('Failed to load payment history:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to load payment history'
    })
  } finally {
    loadingPayments.value = false
  }
}

const loadCustomerSmsHistory = async (customerId: string) => {
  try {
    loadingSms.value = true
    const response = await api.get(`/api/v1/customers/${customerId}/sms-history`)
    customerSmsHistory.value = response.data.data
  } catch (error) {
    console.error('Failed to load SMS history:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to load SMS history'
    })
  } finally {
    loadingSms.value = false
  }
}

const editCustomer = (customer: Customer) => {
  editingCustomer.value = { ...customer }
  showEditCustomerModal.value = true
}

const confirmDeleteCustomer = (customer: Customer) => {
  customerToDelete.value = customer
  showDeleteConfirm.value = true
}

const deleteCustomer = async () => {
  if (!customerToDelete.value) return

  try {
    await api.delete(`/api/v1/customers/${customerToDelete.value.id}`)
    
    // Remove from local list
    const index = customers.value.findIndex(c => c.id === customerToDelete.value!.id)
    if (index !== -1) {
      customers.value.splice(index, 1)
    }

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Customer deleted successfully'
    })
  } catch (error) {
    console.error('Failed to delete customer:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to delete customer'
    })
  } finally {
    showDeleteConfirm.value = false
    customerToDelete.value = null
  }
}

const saveCustomer = async (customerData: Partial<Customer>) => {
  try {
    savingCustomer.value = true
    
    let response: ApiResponse<Customer>
    if (editingCustomer.value?.id) {
      // Update existing customer
      response = await api.put(`/api/v1/customers/${editingCustomer.value.id}`, customerData)
      
      // Update in local list
      const index = customers.value.findIndex(c => c.id === editingCustomer.value!.id)
      if (index !== -1) {
        customers.value[index] = response.data
      }
    } else {
      // Create new customer
      response = await api.post('/api/v1/customers', customerData)
      customers.value.unshift(response.data)
    }

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: editingCustomer.value?.id ? 'Customer updated successfully' : 'Customer created successfully'
    })

    closeCustomerModals()
  } catch (error) {
    console.error('Failed to save customer:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to save customer'
    })
  } finally {
    savingCustomer.value = false
  }
}

const recordPayment = async (paymentData: any) => {
  try {
    const response = await api.post('/api/v1/payments', {
      ...paymentData,
      customer_id: selectedCustomer.value?.id
    })

    // Add to payment history
    customerPaymentHistory.value.unshift(response.data)

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Payment recorded successfully'
    })
  } catch (error) {
    console.error('Failed to record payment:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to record payment'
    })
  }
}

const suspendService = async (customerId: string) => {
  try {
    await api.patch(`/api/v1/customers/${customerId}/suspend`)
    
    // Update customer status
    if (selectedCustomer.value?.id === customerId) {
      selectedCustomer.value.status = 'suspended'
    }
    
    const customerIndex = customers.value.findIndex(c => c.id === customerId)
    if (customerIndex !== -1) {
      customers.value[customerIndex].status = 'suspended'
    }

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Service suspended successfully'
    })
  } catch (error) {
    console.error('Failed to suspend service:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to suspend service'
    })
  }
}

const activateService = async (customerId: string) => {
  try {
    await api.patch(`/api/v1/customers/${customerId}/activate`)
    
    // Update customer status
    if (selectedCustomer.value?.id === customerId) {
      selectedCustomer.value.status = 'active'
    }
    
    const customerIndex = customers.value.findIndex(c => c.id === customerId)
    if (customerIndex !== -1) {
      customers.value[customerIndex].status = 'active'
    }

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Service activated successfully'
    })
  } catch (error) {
    console.error('Failed to activate service:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to activate service'
    })
  }
}

const sendSms = async (smsData: any) => {
  try {
    const response = await api.post('/api/v1/sms/send', {
      ...smsData,
      customer_id: selectedCustomer.value?.id,
      phone_number: selectedCustomer.value?.phone
    })

    // Add to SMS history
    customerSmsHistory.value.unshift(response.data)

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'SMS sent successfully'
    })
  } catch (error) {
    console.error('Failed to send SMS:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to send SMS'
    })
  }
}

const showLocationOnMap = (coordinates: { lat: number; lng: number }) => {
  selectedLocation.value = coordinates
  showLocationMap.value = true
}

const closeCustomerDetail = () => {
  showCustomerDetail.value = false
  selectedCustomer.value = null
  customerPaymentHistory.value = []
  customerSmsHistory.value = []
}

const closeCustomerModals = () => {
  showCreateCustomerModal.value = false
  showEditCustomerModal.value = false
  editingCustomer.value = null
}

const exportCustomers = (data: any) => {
  // Handle export functionality
  console.log('Exporting customers:', data)
}

const handleSelectionChange = (selectedRows: Customer[]) => {
  console.log('Selected customers:', selectedRows)
}

const bulkSuspendCustomers = async (customers: Customer[]) => {
  try {
    const customerIds = customers.map(c => c.id)
    await api.post('/api/v1/customers/bulk-suspend', { customer_ids: customerIds })
    
    // Update local state
    customers.forEach(customer => {
      const index = customers.value.findIndex(c => c.id === customer.id)
      if (index !== -1) {
        customers.value[index].status = 'suspended'
      }
    })

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: `${customers.length} customers suspended successfully`
    })
  } catch (error) {
    console.error('Failed to suspend customers:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to suspend customers'
    })
  }
}

const bulkActivateCustomers = async (customers: Customer[]) => {
  try {
    const customerIds = customers.map(c => c.id)
    await api.post('/api/v1/customers/bulk-activate', { customer_ids: customerIds })
    
    // Update local state
    customers.forEach(customer => {
      const index = customers.value.findIndex(c => c.id === customer.id)
      if (index !== -1) {
        customers.value[index].status = 'active'
      }
    })

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: `${customers.length} customers activated successfully`
    })
  } catch (error) {
    console.error('Failed to activate customers:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to activate customers'
    })
  }
}

const bulkDeleteCustomers = async (customers: Customer[]) => {
  if (!confirm(`Are you sure you want to delete ${customers.length} customers? This action cannot be undone.`)) {
    return
  }

  try {
    const customerIds = customers.map(c => c.id)
    await api.post('/api/v1/customers/bulk-delete', { customer_ids: customerIds })
    
    // Remove from local state
    customerIds.forEach(id => {
      const index = customers.value.findIndex(c => c.id === id)
      if (index !== -1) {
        customers.value.splice(index, 1)
      }
    })

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: `${customers.length} customers deleted successfully`
    })
  } catch (error) {
    console.error('Failed to delete customers:', error)
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to delete customers'
    })
  }
}

// Utility functions
const formatStatus = (status: string | null | undefined) => {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX'
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

// Lifecycle
onMounted(async () => {
  await loadCustomers()
  
  // Load service plans for customer form
  try {
    const response = await api.get('/api/v1/service-plans')
    servicePlans.value = response.data.data
  } catch (error) {
    console.error('Failed to load service plans:', error)
  }
})

// Real-time updates
watch(() => realtimeStore.customerUpdates, (updates) => {
  if (updates) {
    // Handle real-time customer updates
    const index = customers.value.findIndex(c => c.id === updates.customer_id)
    if (index !== -1) {
      customers.value[index] = { ...customers.value[index], ...updates.data }
    }
  }
})
</script>

<style scoped>
.customer-management {
  padding: 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.header-content {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 8px;
}

.page-description {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-icon {
  width: 16px;
  height: 16px;
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

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-suspended {
  background: #fef3c7;
  color: #92400e;
}

.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.location-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.location-text {
  font-size: 0.875rem;
}

.location-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  border-radius: 4px;
  background: #f3f4f6;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.location-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.location-icon {
  width: 14px;
  height: 14px;
}

.service-plan-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.plan-name {
  font-size: 0.875rem;
  font-weight: 500;
}

.plan-price {
  font-size: 0.75rem;
  color: #6b7280;
}

.payment-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.payment-amount {
  font-size: 0.875rem;
  font-weight: 500;
}

.payment-date {
  font-size: 0.75rem;
  color: #6b7280;
}

.text-muted {
  color: #9ca3af;
  font-style: italic;
}

.bulk-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: white;
  color: #374151;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.bulk-action-btn:hover {
  background: #f9fafb;
  border-color: #9ca3af;
}

.bulk-action-btn.danger {
  color: #dc2626;
  border-color: #fca5a5;
}

.bulk-action-btn.danger:hover {
  background: #fef2f2;
  border-color: #f87171;
}

.action-icon {
  width: 14px;
  height: 14px;
}

.map-container {
  height: 400px;
  border-radius: 8px;
  overflow: hidden;
}

.map-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: #f3f4f6;
  color: #6b7280;
}
</style>