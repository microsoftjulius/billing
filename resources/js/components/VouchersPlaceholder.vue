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
          <div class="stat-number">5,678</div>
          <div class="stat-change positive">+25% this month</div>
        </div>
        <div class="stat-card">
          <h3>Active Vouchers</h3>
          <div class="stat-number">2,345</div>
          <div class="stat-change positive">+18% this month</div>
        </div>
        <div class="stat-card">
          <h3>Used Today</h3>
          <div class="stat-number">156</div>
          <div class="stat-change positive">+12% vs yesterday</div>
        </div>
        <div class="stat-card">
          <h3>Revenue Today</h3>
          <div class="stat-number">UGX 780K</div>
          <div class="stat-change positive">+22% vs yesterday</div>
        </div>
      </div>

      <div class="vouchers-table">
        <h2>Recent Vouchers</h2>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Value</th>
                <th>Status</th>
                <th>Created</th>
                <th>Used</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="voucher in dummyVouchers" :key="voucher.id">
                <td><code>{{ voucher.code }}</code></td>
                <td>{{ voucher.type }}</td>
                <td>{{ voucher.value }}</td>
                <td>
                  <span class="status-badge" :class="voucher.status">
                    {{ voucher.status }}
                  </span>
                </td>
                <td>{{ voucher.created }}</td>
                <td>{{ voucher.used || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const dummyVouchers = ref([
  {
    id: 1,
    code: 'VCH-ABC123',
    type: '1 Hour',
    value: 'UGX 2,000',
    status: 'active',
    created: '2024-01-12',
    used: null
  },
  {
    id: 2,
    code: 'VCH-DEF456',
    type: '24 Hours',
    value: 'UGX 5,000',
    status: 'used',
    created: '2024-01-11',
    used: '2024-01-12'
  },
  {
    id: 3,
    code: 'VCH-GHI789',
    type: '1 Week',
    value: 'UGX 15,000',
    status: 'active',
    created: '2024-01-10',
    used: null
  },
  {
    id: 4,
    code: 'VCH-JKL012',
    type: '1 Hour',
    value: 'UGX 2,000',
    status: 'expired',
    created: '2024-01-05',
    used: null
  }
])
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
</style>