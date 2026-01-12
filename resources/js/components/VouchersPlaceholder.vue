<template>
  <div class="vouchers-placeholder">
    <div class="page-header">
      <h1>Voucher Management</h1>
      <p>Generate and manage internet vouchers with real-time tracking</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Vouchers</h3>
          <div class="stat-number">{{ stats.total_vouchers || 0 }}</div>
          <div class="stat-change positive">{{ stats.active_vouchers || 0 }} active</div>
        </div>
        <div class="stat-card">
          <h3>Used Vouchers</h3>
          <div class="stat-number">{{ stats.used_vouchers || 0 }}</div>
          <div class="stat-change">{{ ((stats.used_vouchers || 0) / (stats.total_vouchers || 1) * 100).toFixed(1) }}% usage rate</div>
        </div>
        <div class="stat-card">
          <h3>Expired Vouchers</h3>
          <div class="stat-number">{{ stats.expired_vouchers || 0 }}</div>
          <div class="stat-change">{{ stats.unused_vouchers || 0 }} unused</div>
        </div>
        <div class="stat-card">
          <h3>Total Revenue</h3>
          <div class="stat-number">{{ formatCurrency(stats.total_revenue || 0) }}</div>
          <div class="stat-change positive">{{ formatCurrency(stats.average_value || 0) }} avg</div>
        </div>
      </div>

      <div class="vouchers-table">
        <h2>Recent Vouchers</h2>
        <div v-if="loading" class="loading-state">
          <p>Loading vouchers...</p>
        </div>
        <div v-else-if="vouchers.length === 0" class="empty-state">
          <p>No vouchers found</p>
        </div>
        <div v-else class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Profile</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Customer</th>
                <th>Created</th>
                <th>Expires</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="voucher in vouchers" :key="voucher.id">
                <td><code>{{ voucher.code }}</code></td>
                <td>{{ voucher.profile || 'N/A' }}</td>
                <td>{{ voucher.validity_hours }}h</td>
                <td>
                  <span class="status-badge" :class="voucher.status">
                    {{ voucher.status }}
                  </span>
                </td>
                <td>
                  <div v-if="voucher.customer" class="customer-info">
                    <div class="customer-name">{{ voucher.customer.name }}</div>
                    <div class="customer-phone">{{ voucher.customer.phone }}</div>
                  </div>
                  <span v-else class="text-muted">Not assigned</span>
                </td>
                <td>{{ formatDate(voucher.created_at) }}</td>
                <td>
                  <span v-if="voucher.expires_at">{{ formatDate(voucher.expires_at) }}</span>
                  <span v-else class="text-muted">No expiry</span>
                </td>
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

const vouchers = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(false)

const loadVouchers = async () => {
  try {
    loading.value = true
    
    // Load vouchers and calculate statistics from the data
    const vouchersResponse = await api.get('/api/v1/vouchers?per_page=20')
    
    console.log('Vouchers API response:', vouchersResponse.data);
    
    // The API returns data in 'data' field, not 'vouchers'
    vouchers.value = vouchersResponse.data.data || []
    
    // Calculate statistics from the voucher data
    const totalVouchers = vouchers.value.length
    const activeVouchers = vouchers.value.filter(v => v.status === 'active').length
    const usedVouchers = vouchers.value.filter(v => v.status === 'used').length
    const expiredVouchers = vouchers.value.filter(v => v.status === 'expired').length
    const unusedVouchers = vouchers.value.filter(v => v.status === 'unused').length
    const totalRevenue = vouchers.value.reduce((sum, v) => sum + parseFloat(v.price || 0), 0)
    const averageValue = totalVouchers > 0 ? totalRevenue / totalVouchers : 0
    
    stats.value = {
      total_vouchers: totalVouchers,
      active_vouchers: activeVouchers,
      used_vouchers: usedVouchers,
      expired_vouchers: expiredVouchers,
      unused_vouchers: unusedVouchers,
      total_revenue: totalRevenue,
      average_value: averageValue
    }
    
    console.log('Processed vouchers data:', { vouchers: vouchers.value.length, stats: stats.value });
  } catch (error) {
    console.error('Failed to load vouchers:', error)
    // Set empty data on error
    stats.value = {}
    vouchers.value = []
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
  loadVouchers()
})
</script>

<style scoped>
.vouchers-placeholder {
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

.vouchers-table {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.vouchers-table h2 {
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

.table-container {
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

.data-table code {
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.875rem;
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

.text-muted {
  color: var(--text-secondary);
  font-style: italic;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.used {
  background: #e0e7ff;
  color: #3730a3;
}

.status-badge.expired {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.unused {
  background: #f3f4f6;
  color: #6b7280;
}
</style>