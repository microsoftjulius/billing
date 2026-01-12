<template>
  <div class="mikrotik-placeholder">
    <div class="page-header">
      <h1>MikroTik Monitoring</h1>
      <p>Monitor MikroTik router status and connectivity across all locations</p>
    </div>

    <div class="content-area">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Routers</h3>
          <div class="stat-number">12</div>
          <div class="stat-change">8 active, 4 offline</div>
        </div>
        <div class="stat-card">
          <h3>Uptime Average</h3>
          <div class="stat-number">98.5%</div>
          <div class="stat-change positive">+0.8% this month</div>
        </div>
        <div class="stat-card">
          <h3>Active Users</h3>
          <div class="stat-number">1,456</div>
          <div class="stat-change positive">+12% today</div>
        </div>
        <div class="stat-card">
          <h3>Data Usage</h3>
          <div class="stat-number">2.4 TB</div>
          <div class="stat-change positive">+18% this week</div>
        </div>
      </div>

      <div class="routers-section">
        <div class="section-header">
          <h2>Router Status</h2>
          <div class="header-actions">
            <button class="btn btn-outline">
              <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Refresh All
            </button>
            <button class="btn btn-primary">
              <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Add Router
            </button>
          </div>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Router Name</th>
                <th>IP Address</th>
                <th>Location</th>
                <th>Status</th>
                <th>Active Users</th>
                <th>Uptime</th>
                <th>Last Active</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="router in dummyRouters" :key="router.id">
                <td>
                  <div class="router-name">
                    <svg class="router-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                      <line x1="8" y1="21" x2="16" y2="21"/>
                      <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    {{ router.name }}
                  </div>
                </td>
                <td>
                  <code class="ip-address">{{ router.ipAddress }}</code>
                </td>
                <td>{{ router.location }}</td>
                <td>
                  <span class="status-badge" :class="router.status">
                    <span class="status-dot"></span>
                    {{ router.status === 'active' ? 'Active' : 'Offline' }}
                  </span>
                </td>
                <td>
                  <span v-if="router.status === 'active'" class="user-count">
                    {{ router.activeUsers }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>
                  <span v-if="router.status === 'active'" class="uptime">
                    {{ router.uptime }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>
                  <span v-if="router.status === 'active'" class="last-active">
                    Online
                  </span>
                  <span v-else class="last-active offline">
                    {{ router.lastActive }}
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" :disabled="router.status !== 'active'">
                      Monitor
                    </button>
                    <button class="btn btn-sm btn-outline">
                      Configure
                    </button>
                    <button 
                      class="btn btn-sm"
                      :class="router.status === 'active' ? 'btn-warning' : 'btn-success'"
                    >
                      {{ router.status === 'active' ? 'Disable' : 'Enable' }}
                    </button>
                  </div>
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
import { ref } from 'vue'

const dummyRouters = ref([
  {
    id: 1,
    name: 'Kyengera MikroTik',
    ipAddress: '192.168.1.1',
    location: 'Kyengera, Wakiso',
    status: 'active',
    activeUsers: 45,
    uptime: '99.2%',
    lastActive: null
  },
  {
    id: 2,
    name: 'Nansana MikroTik',
    ipAddress: '192.168.2.1',
    location: 'Nansana, Wakiso',
    status: 'active',
    activeUsers: 32,
    uptime: '98.8%',
    lastActive: null
  },
  {
    id: 3,
    name: 'Bweyogerere MikroTik',
    ipAddress: '192.168.3.1',
    location: 'Bweyogerere, Wakiso',
    status: 'offline',
    activeUsers: 0,
    uptime: '0%',
    lastActive: '2 hours ago'
  },
  {
    id: 4,
    name: 'Kira MikroTik',
    ipAddress: '192.168.4.1',
    location: 'Kira, Wakiso',
    status: 'active',
    activeUsers: 28,
    uptime: '97.5%',
    lastActive: null
  },
  {
    id: 5,
    name: 'Mukono MikroTik',
    ipAddress: '192.168.5.1',
    location: 'Mukono Town',
    status: 'offline',
    activeUsers: 0,
    uptime: '0%',
    lastActive: '1 day ago'
  },
  {
    id: 6,
    name: 'Entebbe MikroTik',
    ipAddress: '192.168.6.1',
    location: 'Entebbe, Wakiso',
    status: 'active',
    activeUsers: 67,
    uptime: '99.8%',
    lastActive: null
  },
  {
    id: 7,
    name: 'Kasangati MikroTik',
    ipAddress: '192.168.7.1',
    location: 'Kasangati, Wakiso',
    status: 'offline',
    activeUsers: 0,
    uptime: '0%',
    lastActive: '3 hours ago'
  },
  {
    id: 8,
    name: 'Mpigi MikroTik',
    ipAddress: '192.168.8.1',
    location: 'Mpigi Town',
    status: 'active',
    activeUsers: 19,
    uptime: '96.2%',
    lastActive: null
  },
  {
    id: 9,
    name: 'Bombo MikroTik',
    ipAddress: '192.168.9.1',
    location: 'Bombo, Luweero',
    status: 'active',
    activeUsers: 41,
    uptime: '98.1%',
    lastActive: null
  },
  {
    id: 10,
    name: 'Mityana MikroTik',
    ipAddress: '192.168.10.1',
    location: 'Mityana Town',
    status: 'offline',
    activeUsers: 0,
    uptime: '0%',
    lastActive: '5 hours ago'
  },
  {
    id: 11,
    name: 'Masaka MikroTik',
    ipAddress: '192.168.11.1',
    location: 'Masaka Town',
    status: 'active',
    activeUsers: 53,
    uptime: '99.5%',
    lastActive: null
  },
  {
    id: 12,
    name: 'Jinja MikroTik',
    ipAddress: '192.168.12.1',
    location: 'Jinja Town',
    status: 'active',
    activeUsers: 38,
    uptime: '97.9%',
    lastActive: null
  }
])
</script>

<style scoped>
.mikrotik-placeholder {
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

.routers-section {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 0.25rem;
  background: var(--bg-color);
  color: var(--text-secondary);
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:hover:not(:disabled) {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

.btn-warning {
  background: var(--warning-color);
  color: white;
  border-color: var(--warning-color);
}

.btn-success {
  background: var(--success-color);
  color: white;
  border-color: var(--success-color);
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

.btn-icon {
  width: 16px;
  height: 16px;
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
  font-size: 0.875rem;
}

.data-table td {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.router-name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.router-icon {
  color: var(--text-secondary);
}

.ip-address {
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.8rem;
  border: 1px solid var(--border-color);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.offline {
  background: #fee2e2;
  color: #991b1b;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.user-count {
  font-weight: 500;
  color: var(--text-primary);
}

.uptime {
  font-weight: 500;
  color: var(--success-color);
}

.last-active {
  font-size: 0.8rem;
}

.last-active.offline {
  color: var(--error-color);
}

.text-muted {
  color: var(--text-tertiary);
  font-style: italic;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Responsive design */
@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
  
  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .header-actions {
    width: 100%;
    justify-content: flex-end;
  }
  
  .table-container {
    font-size: 0.8rem;
  }
  
  .action-buttons {
    flex-direction: column;
  }
}
</style>