<template>
  <div class="sms-placeholder">
    <div class="page-header">
      <h1>SMS Configuration</h1>
      <p>Configure SMS settings and manage message templates</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Messages Sent</h3>
          <div class="stat-number">2,456</div>
          <div class="stat-change positive">+18% this month</div>
        </div>
        <div class="stat-card">
          <h3>Delivery Rate</h3>
          <div class="stat-number">97.8%</div>
          <div class="stat-change positive">+1.2% this month</div>
        </div>
        <div class="stat-card">
          <h3>Balance</h3>
          <div class="stat-number">UGX 45K</div>
          <div class="stat-change">156 messages left</div>
        </div>
        <div class="stat-card">
          <h3>Templates</h3>
          <div class="stat-number">8</div>
          <div class="stat-change">5 active</div>
        </div>
      </div>

      <div class="config-section">
        <h2>SMS Configuration</h2>
        <div class="config-grid">
          <div class="config-card">
            <h3>Provider Settings</h3>
            <div class="config-item">
              <label>SMS Provider</label>
              <div class="config-value">UG SMS Gateway</div>
            </div>
            <div class="config-item">
              <label>API Key</label>
              <div class="config-value">••••••••••••••••</div>
            </div>
            <div class="config-item">
              <label>Sender ID</label>
              <div class="config-value">BillingSystem</div>
            </div>
            <div class="config-actions">
              <button class="btn btn-outline">Test Connection</button>
              <button class="btn btn-primary">Update Settings</button>
            </div>
          </div>

          <div class="config-card">
            <h3>Message Templates</h3>
            <div class="templates-list">
              <div v-for="template in dummyTemplates" :key="template.id" class="template-item">
                <div class="template-info">
                  <div class="template-name">{{ template.name }}</div>
                  <div class="template-preview">{{ template.preview }}</div>
                </div>
                <div class="template-actions">
                  <button class="btn btn-sm btn-outline">Edit</button>
                </div>
              </div>
            </div>
            <div class="config-actions">
              <button class="btn btn-primary">Add Template</button>
            </div>
          </div>
        </div>
      </div>

      <div class="recent-messages">
        <h2>Recent Messages</h2>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Recipient</th>
                <th>Message</th>
                <th>Status</th>
                <th>Sent</th>
                <th>Delivered</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="message in dummyMessages" :key="message.id">
                <td>{{ message.recipient }}</td>
                <td class="message-preview">{{ message.message }}</td>
                <td>
                  <span class="status-badge" :class="message.status">
                    {{ message.status }}
                  </span>
                </td>
                <td>{{ message.sent }}</td>
                <td>{{ message.delivered || '-' }}</td>
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

const dummyTemplates = ref([
  {
    id: 1,
    name: 'Welcome Message',
    preview: 'Welcome to BillingSystem! Your account has been created successfully.'
  },
  {
    id: 2,
    name: 'Payment Confirmation',
    preview: 'Payment of {amount} received. Your voucher code is {code}.'
  },
  {
    id: 3,
    name: 'Low Balance Alert',
    preview: 'Your account balance is low. Please top up to continue service.'
  },
  {
    id: 4,
    name: 'Service Suspension',
    preview: 'Your service has been suspended due to non-payment.'
  }
])

const dummyMessages = ref([
  {
    id: 1,
    recipient: '+256 700 123 456',
    message: 'Payment of UGX 5,000 received. Your voucher code is VCH-ABC123.',
    status: 'delivered',
    sent: '2024-01-12 10:30',
    delivered: '2024-01-12 10:31'
  },
  {
    id: 2,
    recipient: '+256 700 234 567',
    message: 'Welcome to BillingSystem! Your account has been created successfully.',
    status: 'delivered',
    sent: '2024-01-12 09:15',
    delivered: '2024-01-12 09:16'
  },
  {
    id: 3,
    recipient: '+256 700 345 678',
    message: 'Your account balance is low. Please top up to continue service.',
    status: 'pending',
    sent: '2024-01-12 08:45',
    delivered: null
  },
  {
    id: 4,
    recipient: '+256 700 456 789',
    message: 'Your service has been suspended due to non-payment.',
    status: 'failed',
    sent: '2024-01-12 08:20',
    delivered: null
  }
])
</script>

<style scoped>
.sms-placeholder {
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

.config-section {
  margin-bottom: 2rem;
}

.config-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.config-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.config-card {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.config-card h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.config-item {
  margin-bottom: 1rem;
}

.config-item label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
}

.config-value {
  font-size: 0.875rem;
  color: var(--text-primary);
  padding: 0.5rem 0;
}

.config-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}

.templates-list {
  margin-bottom: 1rem;
}

.template-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-color);
}

.template-item:last-child {
  border-bottom: none;
}

.template-info {
  flex: 1;
}

.template-name {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.template-preview {
  font-size: 0.75rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.template-actions {
  margin-left: 1rem;
}

.recent-messages {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.recent-messages h2 {
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

.message-preview {
  max-width: 300px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.delivered {
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

.btn {
  padding: 0.5rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 0.25rem;
  background: var(--bg-color);
  color: var(--text-secondary);
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.btn-primary {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background: var(--primary-hover);
  border-color: var(--primary-hover);
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}
</style>