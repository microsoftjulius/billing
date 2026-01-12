<template>
  <div class="payments-placeholder">
    <div class="page-header">
      <h1>Payment Management</h1>
      <p>Manage payment transactions and monitor payment activity</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Payments</h3>
          <div class="stat-number">{{ stats.total_payments || 0 }}</div>
          <div class="stat-change positive">{{ stats.completed_payments || 0 }} completed</div>
        </div>
        <div class="stat-card">
          <h3>Success Rate</h3>
          <div class="stat-number">{{ stats.success_rate || 0 }}%</div>
          <div class="stat-change positive">{{ stats.pending_payments || 0 }} pending</div>
        </div>
        <div class="stat-card">
          <h3>Total Revenue</h3>
          <div class="stat-number">{{ formatCurrency(stats.total_revenue || 0) }}</div>
          <div class="stat-change positive">{{ formatCurrency(stats.average_amount || 0) }} avg</div>
        </div>
        <div class="stat-card">
          <h3>Failed Payments</h3>
          <div class="stat-number">{{ stats.failed_payments || 0 }}</div>
          <div class="stat-change">{{ ((stats.failed_payments || 0) / (stats.total_payments || 1) * 100).toFixed(1) }}% of total</div>
        </div>
      </div>

      <div class="payments-section">
        <h2>Recent Payments</h2>
        <div v-if="loading" class="loading-state">
          <p>Loading payments...</p>
        </div>
        <div v-else-if="payments.length === 0" class="empty-state">
          <p>No payments found</p>
        </div>
        <div v-else class="payments-table">
          <table class="data-table">
            <thead>
              <tr>
                <th>Transaction ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Package</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in payments" :key="payment.id">
                <td>{{ payment.transaction_id }}</td>
                <td>
                  <div class="customer-info">
                    <div class="customer-name">{{ payment.customer?.name || 'N/A' }}</div>
                    <div class="customer-phone">{{ payment.customer?.phone || 'N/A' }}</div>
                  </div>
                </td>
                <td class="amount">{{ formatCurrency(payment.amount) }}</td>
                <td>
                  <span class="status-badge" :class="payment.status">
                    {{ payment.status }}
                  </span>
                </td>
                <td>{{ payment.metadata?.package || 'N/A' }}</td>
                <td>{{ formatDate(payment.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/api/client'

const payments = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(false)

const loadPayments = async () => {
  try {
    loading.value = true
    console.log('Loading payments data...');
    
    // Load payments and calculate statistics from the data
    const paymentsResponse = await api.get('/api/v1/payments?per_page=20')
    
    console.log('Payments API response:', paymentsResponse.data);
    
    // The API returns data in 'data' field, not 'payments'
    payments.value = paymentsResponse.data.data || []
    
    // Calculate statistics from the summary data
    const summary = paymentsResponse.data.summary || {}
    stats.value = {
      total_payments: summary.total_amount ? Math.floor(summary.total_amount / 1000) : payments.value.length, // Use actual count if no summary
      completed_payments: summary.completed_count || payments.value.filter(p => p.status === 'completed').length,
      pending_payments: summary.pending_count || payments.value.filter(p => p.status === 'pending').length,
      failed_payments: summary.failed_count || payments.value.filter(p => p.status === 'failed').length,
      total_revenue: summary.total_amount || payments.value.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0),
      average_amount: summary.total_amount && summary.completed_count 
        ? Math.floor(summary.total_amount / summary.completed_count) 
        : payments.value.length > 0 ? Math.floor(payments.value.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0) / payments.value.length) : 0,
      success_rate: summary.completed_count && (summary.completed_count + summary.failed_count)
        ? Math.floor((summary.completed_count / (summary.completed_count + summary.failed_count)) * 100)
        : payments.value.length > 0 ? Math.floor((payments.value.filter(p => p.status === 'completed').length / payments.value.length) * 100) : 0
    }
    
    console.log('Processed payments data:', { payments: payments.value.length, stats: stats.value });
  } catch (error) {
    console.error('Failed to load payments:', error)
    // Set empty data on error
    stats.value = {}
    payments.value = []
  } finally {
    loading.value = false
  }
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0,
  }).format(amount)
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString('en-UG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  loadPayments()
})
</script>

<style scoped>
.payments-placeholder {
  padding: 2rem;
}

.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.page-header p {
  color: var(--text-secondary);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.stat-card h3 {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.stat-number {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.stat-change {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-secondary);
}

.stat-change.positive {
  color: var(--success-color);
}

.payments-section {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.payments-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 2rem;
  color: var(--text-secondary);
}

.payments-table {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.data-table th {
  font-weight: 600;
  color: var(--text-primary);
  background: var(--bg-color);
}

.data-table td {
  color: var(--text-secondary);
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.customer-name {
  font-weight: 500;
  color: var(--text-primary);
}

.customer-phone {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.amount {
  font-weight: 600;
  color: var(--text-primary);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.completed {
  background: #dcfce7;
  color: #166534;
}

.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.failed {
  background: #fee2e2;
  color: #991b1b;
}
</style>