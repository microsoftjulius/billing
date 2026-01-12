<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <h1>Dashboard</h1>
      <p>Welcome back! Here's what's happening with your billing system.</p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="metrics-grid" v-if="dashboardData">
      <div class="metric-card">
        <div class="metric-icon revenue">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="metric-content">
          <div class="metric-value">{{ formatCurrency(dashboardData.overview.total_revenue) }}</div>
          <div class="metric-title">Total Revenue</div>
          <div class="metric-subtitle">Across all tenants</div>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-icon customers">
          <i class="fas fa-users"></i>
        </div>
        <div class="metric-content">
          <div class="metric-value">{{ dashboardData.overview.total_customers }}</div>
          <div class="metric-title">Total Customers</div>
          <div class="metric-subtitle">{{ dashboardData.overview.active_tenants }} active tenants</div>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-icon payments">
          <i class="fas fa-credit-card"></i>
        </div>
        <div class="metric-content">
          <div class="metric-value">{{ dashboardData.overview.total_payments }}</div>
          <div class="metric-title">Total Payments</div>
          <div class="metric-subtitle">All transactions</div>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-icon vouchers">
          <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="metric-content">
          <div class="metric-value">{{ dashboardData.overview.total_vouchers }}</div>
          <div class="metric-title">Total Vouchers</div>
          <div class="metric-subtitle">Generated vouchers</div>
        </div>
      </div>
    </div>

    <!-- Plan Distribution -->
    <div class="plan-section" v-if="dashboardData">
      <h2>Plan Distribution</h2>
      <div class="plan-grid">
        <div class="plan-card" v-for="(plan, planName) in dashboardData.by_plan" :key="planName">
          <div class="plan-header">
            <h3>{{ formatPlanName(planName) }}</h3>
            <div class="plan-count">{{ plan.count }} tenants</div>
          </div>
          <div class="plan-revenue">{{ formatCurrency(plan.revenue) }}</div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="activity-section" v-if="dashboardData && dashboardData.recent_activity">
      <h2>Recent Activity</h2>
      <div class="activity-list">
        <div v-for="activity in dashboardData.recent_activity.slice(0, 10)" :key="`${activity.type}-${activity.timestamp}`" class="activity-item">
          <div class="activity-icon" :class="activity.color">
            <i :class="`fas fa-${activity.icon}`"></i>
          </div>
          <div class="activity-content">
            <div class="activity-title">{{ activity.title }}</div>
            <div class="activity-description">{{ activity.description }}</div>
            <div class="activity-time">{{ formatTime(activity.timestamp) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading dashboard data...</p>
    </div>

    <!-- Error State -->
    <div v-if="apiError" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Unable to load dashboard data</h3>
      <p>{{ apiError }}</p>
      <button @click="loadDashboardData" class="retry-btn">
        <i class="fas fa-redo"></i>
        Try Again
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/api/client';

const isLoading = ref(false);
const dashboardData = ref(null);
const apiError = ref(null);

const loadDashboardData = async () => {
  try {
    isLoading.value = true;
    apiError.value = null;
    
    // Check if user is authenticated
    const authToken = localStorage.getItem('auth_token');
    if (!authToken) {
      console.log('No auth token found, redirecting to login');
      // Redirect to login if no token
      window.location.href = '/login';
      return;
    }
    
    console.log('Loading dashboard data with token:', authToken.substring(0, 20) + '...');
    
    const response = await api.get('/api/v1/dashboard/stats');
    
    if (response.data && response.data.data) {
      dashboardData.value = response.data.data;
      console.log('Dashboard data loaded successfully:', response.data.data);
    } else {
      throw new Error('Invalid response format');
    }
    
  } catch (error: any) {
    console.error('Dashboard API Error:', error);
    
    // If unauthorized, redirect to login
    if (error.response?.status === 401) {
      console.log('Unauthorized, clearing auth and redirecting to login');
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('tenant');
      window.location.href = '/login';
      return;
    }
    
    apiError.value = error.message || 'Failed to load dashboard data';
  } finally {
    isLoading.value = false;
  }
};

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0,
  }).format(amount);
};

const formatPlanName = (planName: string | null | undefined): string => {
  if (!planName) return 'Unknown'
  return planName.charAt(0).toUpperCase() + planName.slice(1);
};

const formatTime = (dateString: string): string => {
  return new Date(dateString).toLocaleString('en-UG', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(async () => {
  // Wait a bit for app initialization to complete
  await new Promise(resolve => setTimeout(resolve, 100));
  
  console.log('Dashboard mounted, loading data...');
  loadDashboardData();
});
</script>

<style scoped>
.dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 2rem;
}

.dashboard-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.dashboard-header p {
  color: var(--text-secondary);
  font-size: 1.1rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.metric-card {
  background: var(--card-bg);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.metric-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
  flex-shrink: 0;
}

.metric-icon.revenue {
  background: linear-gradient(135deg, #10b981, #059669);
}

.metric-icon.customers {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.metric-icon.payments {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.metric-icon.vouchers {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.metric-content {
  flex: 1;
}

.metric-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.metric-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 0.125rem;
}

.metric-subtitle {
  font-size: 0.75rem;
  color: var(--text-tertiary);
}

.plan-section {
  margin-bottom: 3rem;
}

.plan-section h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.plan-card {
  background: var(--card-bg);
  border-radius: 0.75rem;
  padding: 1.25rem;
  border: 1px solid var(--border-color);
  transition: transform 0.2s ease;
}

.plan-card:hover {
  transform: translateY(-1px);
}

.plan-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.plan-header h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.plan-count {
  font-size: 0.75rem;
  color: var(--text-secondary);
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.plan-revenue {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--primary-color);
}

.activity-section {
  margin-bottom: 2rem;
}

.activity-section h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
}

.activity-list {
  background: var(--card-bg);
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.2s ease;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-item:hover {
  background: var(--hover-bg);
}

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: white;
}

.activity-icon.green {
  background: #10b981;
}

.activity-icon.blue {
  background: #3b82f6;
}

.activity-icon.yellow {
  background: #f59e0b;
}

.activity-icon.red {
  background: #ef4444;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-title {
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.activity-description {
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
  font-size: 0.875rem;
}

.activity-time {
  font-size: 0.75rem;
  color: var(--text-tertiary);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--border-color);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.error-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #fee2e2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.error-state h3 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.error-state p {
  color: var(--text-secondary);
  margin-bottom: 1.5rem;
}

.retry-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.retry-btn:hover {
  background: var(--primary-hover);
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .dashboard {
    padding: 1rem;
  }
  
  .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .plan-grid {
    grid-template-columns: 1fr;
  }
  
  .activity-item {
    padding: 0.75rem 1rem;
  }
}
</style>

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