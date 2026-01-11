<template>
  <div class="dashboard">
    <!-- Loading Overlay -->
    <LoadingOverlay v-if="isInitialLoading" />

    <!-- Dashboard Content -->
    <div v-else class="dashboard-content">
      <!-- Key Metrics Cards -->
      <div class="metrics-grid">
        <MetricCard
          title="Today's Revenue"
          :value="formatCurrency(stats.revenue?.today || 0)"
          :change="stats.revenue?.growth_percentage"
          icon="fas fa-money-bill-wave"
          color="green"
          :loading="isLoading"
        />
        
        <MetricCard
          title="Active Customers"
          :value="stats.customers?.active || 0"
          :change="stats.customers?.active_percentage"
          :subtitle="`${stats.customers?.total || 0} total`"
          icon="fas fa-users"
          color="blue"
          :loading="isLoading"
        />
        
        <MetricCard
          title="Active Vouchers"
          :value="stats.vouchers?.active || 0"
          :change="stats.vouchers?.utilization_rate"
          :subtitle="`${stats.vouchers?.total || 0} total`"
          icon="fas fa-ticket-alt"
          color="purple"
          :loading="isLoading"
        />
        
        <MetricCard
          title="MikroTik Devices"
          :value="`${stats.mikrotik?.online || 0}/${stats.mikrotik?.total_devices || 0}`"
          :change="stats.mikrotik?.uptime_percentage"
          subtitle="Online/Total"
          icon="fas fa-router"
          color="orange"
          :loading="isLoading"
        />
      </div>

      <!-- Charts and Analytics -->
      <div class="analytics-section">
        <div class="analytics-grid">
          <!-- Revenue Chart -->
          <div class="chart-card">
            <div class="chart-header">
              <h3>Revenue Analytics</h3>
              <div class="chart-controls">
                <select v-model="revenueChartPeriod" @change="updateRevenueChart">
                  <option value="30d">Last 30 Days</option>
                  <option value="7d">Last 7 Days</option>
                  <option value="90d">Last 90 Days</option>
                </select>
              </div>
            </div>
            <div class="chart-container">
              <canvas ref="revenueChart" id="revenueChart"></canvas>
            </div>
          </div>

          <!-- Device Status Map -->
          <div class="chart-card">
            <div class="chart-header">
              <h3>MikroTik Device Status</h3>
              <div class="device-legend">
                <span class="legend-item">
                  <span class="legend-dot online"></span>
                  Online ({{ stats.mikrotik?.online || 0 }})
                </span>
                <span class="legend-item">
                  <span class="legend-dot offline"></span>
                  Offline ({{ stats.mikrotik?.offline || 0 }})
                </span>
              </div>
            </div>
            <div class="device-map">
              <MikroTikMonitor 
                :show-header="false" 
                :compact="true"
                @device-status-change="handleDeviceStatusChange"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity and System Health -->
      <div class="bottom-section">
        <div class="bottom-grid">
          <!-- Recent Transactions -->
          <div class="activity-card">
            <div class="card-header">
              <h3>Recent Transactions</h3>
              <router-link to="/app/payments" class="view-all-link">
                View All
              </router-link>
            </div>
            <div class="activity-list">
              <div 
                v-for="payment in recentPayments" 
                :key="payment.id"
                class="activity-item"
              >
                <div class="activity-icon payment">
                  <i class="fas fa-credit-card"></i>
                </div>
                <div class="activity-content">
                  <div class="activity-title">
                    {{ formatCurrency(payment.amount) }} from {{ payment.customer.name }}
                  </div>
                  <div class="activity-subtitle">
                    {{ formatTime(payment.created_at) }}
                    <span 
                      class="status-badge" 
                      :class="payment.status"
                    >
                      {{ payment.status }}
                    </span>
                  </div>
                </div>
              </div>
              <div v-if="recentPayments.length === 0" class="no-data">
                No recent transactions
              </div>
            </div>
          </div>

          <!-- Recent Vouchers -->
          <div class="activity-card">
            <div class="card-header">
              <h3>Recent Vouchers</h3>
              <router-link to="/app/vouchers" class="view-all-link">
                View All
              </router-link>
            </div>
            <div class="activity-list">
              <div 
                v-for="voucher in recentVouchers" 
                :key="voucher.id"
                class="activity-item"
              >
                <div class="activity-icon voucher">
                  <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="activity-content">
                  <div class="activity-title">
                    {{ voucher.code }} ({{ voucher.duration_hours }}h)
                  </div>
                  <div class="activity-subtitle">
                    {{ formatTime(voucher.created_at) }}
                    <span 
                      class="status-badge" 
                      :class="voucher.status"
                    >
                      {{ voucher.status }}
                    </span>
                  </div>
                </div>
              </div>
              <div v-if="recentVouchers.length === 0" class="no-data">
                No recent vouchers
              </div>
            </div>
          </div>

          <!-- System Health -->
          <div class="health-card">
            <div class="card-header">
              <h3>System Health</h3>
              <div 
                class="health-status" 
                :class="stats.system_health?.overall_status || 'unknown'"
              >
                <i class="fas fa-circle"></i>
                {{ (stats.system_health?.overall_status || 'unknown').toUpperCase() }}
              </div>
            </div>
            <div class="health-checks">
              <div 
                v-for="(check, name) in stats.system_health?.checks || {}" 
                :key="name"
                class="health-check"
                :class="check.status"
              >
                <div class="check-icon">
                  <i 
                    class="fas" 
                    :class="{
                      'fa-check-circle': check.status === 'healthy',
                      'fa-exclamation-triangle': check.status === 'warning',
                      'fa-times-circle': check.status === 'unhealthy' || check.status === 'critical'
                    }"
                  ></i>
                </div>
                <div class="check-content">
                  <div class="check-name">{{ formatCheckName(name) }}</div>
                  <div class="check-message">{{ check.message }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity Timeline -->
      <div class="timeline-section" v-if="recentActivity.length > 0">
        <div class="timeline-card">
          <div class="card-header">
            <h3>Recent Activity</h3>
            <div class="timeline-filters">
              <button 
                v-for="filter in activityFilters" 
                :key="filter.value"
                @click="selectedActivityFilter = filter.value"
                class="filter-btn"
                :class="{ active: selectedActivityFilter === filter.value }"
              >
                {{ filter.label }}
              </button>
            </div>
          </div>
          <div class="timeline">
            <div 
              v-for="activity in filteredActivity" 
              :key="`${activity.type}-${activity.timestamp}`"
              class="timeline-item"
            >
              <div class="timeline-marker" :class="activity.color">
                <i :class="activity.icon"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-title">{{ activity.title }}</div>
                <div class="timeline-description">{{ activity.description }}</div>
                <div class="timeline-time">{{ formatTime(activity.timestamp) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import axios from 'axios';
import Chart from 'chart.js/auto';
import ConnectionStatus from '@/components/common/ConnectionStatus.vue';
import LoadingOverlay from '@/components/common/LoadingOverlay.vue';
import MikroTikMonitor from '@/components/MikroTikMonitor.vue';
import MetricCard from '@/components/common/MetricCard.vue';

// Stores
const realtimeStore = useRealtimeStore();
const appStore = useAppStore();

// Reactive data
const stats = ref<any>({});
const recentPayments = ref<any[]>([]);
const recentVouchers = ref<any[]>([]);
const recentActivity = ref<any[]>([]);
const isLoading = ref(false);
const isInitialLoading = ref(true);
const revenueChartPeriod = ref('30d');
const selectedActivityFilter = ref('all');

// Chart references
const revenueChart = ref<HTMLCanvasElement>();
let revenueChartInstance: Chart | null = null;

// Activity filters
const activityFilters = [
  { label: 'All', value: 'all' },
  { label: 'Payments', value: 'payment' },
  { label: 'Vouchers', value: 'voucher' },
  { label: 'Customers', value: 'customer' },
];

// Computed
const filteredActivity = computed(() => {
  if (selectedActivityFilter.value === 'all') {
    return recentActivity.value;
  }
  return recentActivity.value.filter(activity => activity.type === selectedActivityFilter.value);
});

// Auto-refresh interval
let refreshInterval: NodeJS.Timeout | null = null;

// Methods
const loadDashboardData = async () => {
  try {
    isLoading.value = true;

    // Load all dashboard data in parallel
    const [statsResponse, paymentsResponse, vouchersResponse] = await Promise.all([
      axios.get('/api/v1/dashboard/stats'),
      axios.get('/api/v1/dashboard/recent-payments?limit=10'),
      axios.get('/api/v1/dashboard/recent-vouchers?limit=10'),
    ]);

    stats.value = statsResponse.data.data;
    recentPayments.value = paymentsResponse.data.data;
    recentVouchers.value = vouchersResponse.data.data;
    recentActivity.value = stats.value.recent_activity || [];

    // Update revenue chart
    await nextTick();
    updateRevenueChart();

  } catch (error) {
    console.error('Failed to load dashboard data:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Dashboard Error',
      message: 'Failed to load dashboard data. Please try again.',
    });
  } finally {
    isLoading.value = false;
    isInitialLoading.value = false;
  }
};

const refreshData = async () => {
  if (!isLoading.value) {
    await loadDashboardData();
  }
};

const updateRevenueChart = () => {
  if (!revenueChart.value || !stats.value.revenue?.analytics) return;

  // Destroy existing chart
  if (revenueChartInstance) {
    revenueChartInstance.destroy();
  }

  const ctx = revenueChart.value.getContext('2d');
  if (!ctx) return;

  const analytics = stats.value.revenue.analytics;

  revenueChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: analytics.labels,
      datasets: analytics.datasets,
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return formatCurrency(value as number);
            },
          },
        },
      },
      elements: {
        point: {
          radius: 4,
          hoverRadius: 6,
        },
      },
    },
  });
};

const handleDeviceStatusChange = (deviceId: string, status: string) => {
  // Update device status in real-time
  realtimeStore.updateMikroTikDevice(deviceId, { status });
  
  // Refresh stats to update device counts
  refreshData();
};

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0,
  }).format(amount);
};

const formatTime = (dateString: string): string => {
  return new Date(dateString).toLocaleString('en-UG', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatCheckName = (name: string): string => {
  return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Lifecycle
onMounted(async () => {
  await loadDashboardData();
  
  // Set up auto-refresh every 30 seconds
  refreshInterval = setInterval(() => {
    if (!isLoading.value) {
      loadDashboardData();
    }
  }, 30000);
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
  
  if (revenueChartInstance) {
    revenueChartInstance.destroy();
  }
});
</script>

<style scoped>
.dashboard {
  background: var(--bg-color);
  min-height: 100vh;
}

.dashboard-content {
  padding: 2rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.analytics-section {
  margin-bottom: 2rem;
}

.analytics-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.chart-card {
  background: var(--card-bg);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.chart-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.chart-controls select {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  background: var(--card-bg);
  color: var(--text-primary);
}

.chart-container {
  height: 300px;
  position: relative;
}

.device-legend {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.legend-dot.online {
  background: #10b981;
}

.legend-dot.offline {
  background: #ef4444;
}

.device-map {
  height: 300px;
  overflow: auto;
}

.bottom-section {
  margin-bottom: 2rem;
}

.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1.5rem;
}

.activity-card,
.health-card {
  background: var(--card-bg);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.view-all-link {
  color: var(--primary-color);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
}

.view-all-link:hover {
  text-decoration: underline;
}

.health-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.health-status.healthy {
  color: #10b981;
}

.health-status.warning {
  color: #f59e0b;
}

.health-status.unhealthy,
.health-status.critical {
  color: #ef4444;
}

.activity-list {
  max-height: 300px;
  overflow-y: auto;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.activity-icon.payment {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.activity-icon.voucher {
  background: rgba(147, 51, 234, 0.1);
  color: #9333ea;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-title {
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.activity-subtitle {
  font-size: 0.875rem;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-badge {
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.completed {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.pending {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-badge.active {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-badge.unused {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.health-checks {
  space-y: 1rem;
}

.health-check {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0;
}

.check-icon {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.health-check.healthy .check-icon {
  color: #10b981;
}

.health-check.warning .check-icon {
  color: #f59e0b;
}

.health-check.unhealthy .check-icon,
.health-check.critical .check-icon {
  color: #ef4444;
}

.check-content {
  flex: 1;
}

.check-name {
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.125rem;
}

.check-message {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.timeline-section {
  margin-bottom: 2rem;
}

.timeline-card {
  background: var(--card-bg);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

.timeline-filters {
  display: flex;
  gap: 0.5rem;
}

.filter-btn {
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.filter-btn:hover {
  background: var(--hover-bg);
}

.filter-btn.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.timeline {
  margin-top: 1.5rem;
}

.timeline-item {
  display: flex;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--border-color);
}

.timeline-item:last-child {
  border-bottom: none;
}

.timeline-marker {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 0.25rem;
}

.timeline-marker.green {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.timeline-marker.blue {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.timeline-marker.yellow {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.timeline-marker.red {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.timeline-content {
  flex: 1;
}

.timeline-title {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.timeline-description {
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.timeline-time {
  font-size: 0.875rem;
  color: var(--text-tertiary);
}

.no-data {
  text-align: center;
  color: var(--text-secondary);
  padding: 2rem;
  font-style: italic;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .analytics-grid {
    grid-template-columns: 1fr;
  }
  
  .bottom-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-content {
    padding: 1rem;
  }
  
  .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .bottom-grid {
    grid-template-columns: 1fr;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .timeline-filters {
    flex-wrap: wrap;
  }
}
</style>