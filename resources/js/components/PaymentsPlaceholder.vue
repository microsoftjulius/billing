<template>
  <div class="payments-placeholder">
    <div class="page-header">
      <h1>Payment Management</h1>
      <p>Manage payment gateways and transaction processing</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Gateways</h3>
          <div class="stat-number">3</div>
          <div class="stat-change">2 active</div>
        </div>
        <div class="stat-card">
          <h3>Success Rate</h3>
          <div class="stat-number">98.5%</div>
          <div class="stat-change positive">+0.3% this month</div>
        </div>
        <div class="stat-card">
          <h3>Total Volume</h3>
          <div class="stat-number">UGX 2.4M</div>
          <div class="stat-change positive">+15% this month</div>
        </div>
        <div class="stat-card">
          <h3>Transaction Fees</h3>
          <div class="stat-number">UGX 72K</div>
          <div class="stat-change">3% of volume</div>
        </div>
      </div>

      <div class="gateways-section">
        <h2>Payment Gateways</h2>
        <div class="gateways-grid">
          <div v-for="gateway in dummyGateways" :key="gateway.id" class="gateway-card">
            <div class="gateway-header">
              <div class="gateway-info">
                <h3>{{ gateway.name }}</h3>
                <span class="provider-badge" :class="gateway.provider">
                  {{ gateway.provider.toUpperCase() }}
                </span>
                <span class="status-badge" :class="{ active: gateway.isActive }">
                  {{ gateway.isActive ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div class="gateway-actions">
                <button class="btn btn-sm btn-outline">Analytics</button>
                <button class="btn btn-sm btn-outline">Test</button>
                <button class="btn btn-sm btn-outline">Edit</button>
              </div>
            </div>
            <div class="gateway-stats">
              <div class="stat-item">
                <span class="stat-label">Success Rate</span>
                <span class="stat-value" :class="getSuccessRateClass(gateway.successRate)">
                  {{ gateway.successRate }}%
                </span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Transactions</span>
                <span class="stat-value">{{ gateway.transactions }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Volume</span>
                <span class="stat-value">{{ gateway.volume }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const dummyGateways = ref([
  {
    id: 1,
    name: 'CollectUG Gateway',
    provider: 'collectug',
    isActive: true,
    successRate: 99.2,
    transactions: '1,234',
    volume: 'UGX 1.2M'
  },
  {
    id: 2,
    name: 'Stripe Gateway',
    provider: 'stripe',
    isActive: true,
    successRate: 98.8,
    transactions: '856',
    volume: 'UGX 890K'
  },
  {
    id: 3,
    name: 'PayPal Gateway',
    provider: 'paypal',
    isActive: false,
    successRate: 97.5,
    transactions: '234',
    volume: 'UGX 310K'
  }
])

const getSuccessRateClass = (rate: number) => {
  if (rate >= 99) return 'excellent'
  if (rate >= 95) return 'good'
  if (rate >= 90) return 'fair'
  return 'poor'
}
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

.gateways-section {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.gateways-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.gateways-grid {
  display: grid;
  gap: 1rem;
}

.gateway-card {
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1rem;
  background: var(--bg-color);
}

.gateway-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.gateway-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.gateway-info h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.provider-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  background: var(--primary-light);
  color: var(--primary-color);
}

.status-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.gateway-actions {
  display: flex;
  gap: 0.5rem;
}

.btn {
  padding: 0.25rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.25rem;
  background: var(--bg-color);
  color: var(--text-secondary);
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.gateway-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stat-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.stat-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
}

.stat-value.excellent {
  color: var(--success-color);
}

.stat-value.good {
  color: #059669;
}

.stat-value.fair {
  color: var(--warning-color);
}

.stat-value.poor {
  color: var(--error-color);
}
</style>