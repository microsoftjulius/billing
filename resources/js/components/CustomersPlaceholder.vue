<template>
  <div class="customers-placeholder">
    <div class="page-header">
      <h1>Customer Management</h1>
      <p>Manage customer accounts, payments, and service plans</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Customers</h3>
          <div class="stat-number">1,234</div>
          <div class="stat-change positive">+12% this month</div>
        </div>
        <div class="stat-card">
          <h3>Active Customers</h3>
          <div class="stat-number">1,156</div>
          <div class="stat-change positive">+8% this month</div>
        </div>
        <div class="stat-card">
          <h3>New This Month</h3>
          <div class="stat-number">78</div>
          <div class="stat-change positive">+15% vs last month</div>
        </div>
      </div>

      <div class="customers-table">
        <h2>Recent Customers</h2>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Plan</th>
                <th>Last Payment</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="customer in dummyCustomers" :key="customer.id">
                <td>{{ customer.name }}</td>
                <td>{{ customer.email }}</td>
                <td>{{ customer.phone }}</td>
                <td>
                  <span class="status-badge" :class="customer.status">
                    {{ customer.status }}
                  </span>
                </td>
                <td>{{ customer.plan }}</td>
                <td>{{ customer.lastPayment }}</td>
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

const dummyCustomers = ref([
  {
    id: 1,
    name: 'John Doe',
    email: 'john@example.com',
    phone: '+256 700 123 456',
    status: 'active',
    plan: 'Premium 50MB',
    lastPayment: '2024-01-10'
  },
  {
    id: 2,
    name: 'Jane Smith',
    email: 'jane@example.com',
    phone: '+256 700 234 567',
    status: 'active',
    plan: 'Basic 20MB',
    lastPayment: '2024-01-08'
  },
  {
    id: 3,
    name: 'Bob Johnson',
    email: 'bob@example.com',
    phone: '+256 700 345 678',
    status: 'suspended',
    plan: 'Premium 50MB',
    lastPayment: '2023-12-15'
  },
  {
    id: 4,
    name: 'Alice Brown',
    email: 'alice@example.com',
    phone: '+256 700 456 789',
    status: 'active',
    plan: 'Ultra 100MB',
    lastPayment: '2024-01-12'
  }
])
</script>

<style scoped>
.customers-placeholder {
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
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
  font-size: 2rem;
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

.customers-table {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.customers-table h2 {
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

.status-badge.suspended {
  background: #fef3c7;
  color: #92400e;
}
</style>