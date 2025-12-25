<template>
    <div class="reports-dashboard">
        <div class="page-header">
            <h1 class="page-title">Reports & Analytics</h1>
            <div class="page-actions">
                <button class="btn btn-secondary" @click="refreshAll">
                    <i class="fas fa-sync-alt" :class="{ 'fa-spin': refreshing }"></i> Refresh
                </button>
                <button class="btn btn-primary" @click="exportAll">
                    <i class="fas fa-download"></i> Export All
                </button>
            </div>
        </div>

        <div class="reports-nav">
            <nav class="nav-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="{ active: activeTab === tab.id }"
                    class="nav-tab"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.name }}</span>
                </button>
            </nav>
        </div>

        <!-- Tenants Report -->
        <div v-if="activeTab === 'tenants'" class="report-content">
            <div class="report-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Period</label>
                        <select v-model="tenantFilters.period" @change="fetchTenantsReport" class="form-control">
                            <option value="month">Last Month</option>
                            <option value="quarter">Last Quarter</option>
                            <option value="year">Last Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Group By</label>
                        <select v-model="tenantFilters.group_by" @change="fetchTenantsReport" class="form-control">
                            <option value="plan">Plan</option>
                            <option value="status">Status</option>
                            <option value="billing_cycle">Billing Cycle</option>
                            <option value="creation_month">Creation Month</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Limit</label>
                        <select v-model="tenantFilters.limit" @change="fetchTenantsReport" class="form-control">
                            <option value="10">Top 10</option>
                            <option value="20">Top 20</option>
                            <option value="50">Top 50</option>
                            <option value="100">Top 100</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="report-grid">
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card total">
                        <div class="card-header">
                            <h4>Total Tenants</h4>
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ tenantsReport.summary?.total_tenants || 0 }}</h2>
                            <div class="card-trend">
                <span class="trend positive" v-if="tenantsReport.growth?.growth_rate > 0">
                  <i class="fas fa-arrow-up"></i>
                  {{ tenantsReport.growth?.growth_rate }}%
                </span>
                                <span class="trend negative" v-else-if="tenantsReport.growth?.growth_rate < 0">
                  <i class="fas fa-arrow-down"></i>
                  {{ Math.abs(tenantsReport.growth?.growth_rate) }}%
                </span>
                                <span class="trend neutral" v-else>
                  {{ tenantsReport.growth?.growth_rate }}%
                </span>
                                <span class="trend-label">Growth</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card active">
                        <div class="card-header">
                            <h4>Active Tenants</h4>
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ tenantsReport.summary?.active_tenants || 0 }}</h2>
                            <div class="card-stats">
                                <span class="stat-value">{{ tenantsReport.summary?.new_tenants || 0 }}</span>
                                <span class="stat-label">New this period</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card suspended">
                        <div class="card-header">
                            <h4>Suspended</h4>
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ tenantsReport.summary?.suspended_tenants || 0 }}</h2>
                            <div class="card-stats">
                                <span class="stat-value">{{ tenantsReport.summary?.churned_tenants || 0 }}</span>
                                <span class="stat-label">Churned</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card retention">
                        <div class="card-header">
                            <h4>Retention Rate</h4>
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ tenantsReport.summary?.retention_rate || 0 }}%</h2>
                            <div class="progress-bar">
                                <div class="progress-fill" :style="{ width: tenantsReport.summary?.retention_rate + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Growth Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h4>Tenant Growth</h4>
                        <div class="chart-actions">
                            <button class="btn-chart-action" @click="chartType = 'line'" :class="{ active: chartType === 'line' }">
                                <i class="fas fa-chart-line"></i>
                            </button>
                            <button class="btn-chart-action" @click="chartType = 'bar'" :class="{ active: chartType === 'bar' }">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <LineChart
                            v-if="chartType === 'line' && growthChartData"
                            :data="growthChartData"
                            :options="chartOptions"
                        />
                        <BarChart
                            v-else-if="chartType === 'bar' && growthChartData"
                            :data="growthChartData"
                            :options="chartOptions"
                        />
                        <div v-else class="chart-placeholder">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Group Analysis -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h4>Tenants by {{ tenantFilters.group_by }}</h4>
                    </div>
                    <div class="chart-body">
                        <div class="group-analysis">
                            <div v-for="item in tenantsReport.group_analysis?.data" :key="item.group" class="group-item">
                                <div class="group-header">
                                    <span class="group-name">{{ item.group || 'Unknown' }}</span>
                                    <span class="group-count">{{ item.count }}</span>
                                </div>
                                <div class="group-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" :style="{ width: item.percentage + '%' }"></div>
                                    </div>
                                    <span class="group-percentage">{{ item.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Tenants -->
                <div class="table-card">
                    <div class="table-header">
                        <h4>Recent Tenants</h4>
                        <router-link to="/tenants" class="btn-link">
                            View All <i class="fas fa-arrow-right"></i>
                        </router-link>
                    </div>
                    <div class="table-body">
                        <table class="mini-table">
                            <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="tenant in tenantsReport.recent_activity" :key="tenant.id">
                                <td>
                                    <div class="tenant-cell">
                                        <div class="tenant-avatar" :style="{ backgroundColor: getAvatarColor(tenant.name) }">
                                            {{ tenant.name?.charAt(0) }}
                                        </div>
                                        <div class="tenant-info">
                                            <strong>{{ tenant.name }}</strong>
                                            <small>{{ tenant.domains?.[0] }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                    <span class="plan-badge mini" :class="tenant.plan">
                      {{ tenant.plan }}
                    </span>
                                </td>
                                <td>
                    <span class="status-badge mini" :class="tenant.status">
                      {{ tenant.status }}
                    </span>
                                </td>
                                <td>
                                    <span class="timestamp">{{ formatDate(tenant.created_at) }}</span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Report -->
        <div v-else-if="activeTab === 'revenue'" class="report-content">
            <div class="report-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Period</label>
                        <select v-model="revenueFilters.period" @change="fetchRevenueReport" class="form-control">
                            <option value="month">Last Month</option>
                            <option value="quarter">Last Quarter</option>
                            <option value="year">Last Year</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Group By</label>
                        <select v-model="revenueFilters.group_by" @change="fetchRevenueReport" class="form-control">
                            <option value="plan">Plan</option>
                            <option value="tenant">Tenant</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <input type="checkbox" v-model="revenueFilters.include_projections" @change="fetchRevenueReport">
                            Include Projections
                        </label>
                    </div>
                </div>
            </div>

            <div class="report-grid">
                <!-- Revenue Summary -->
                <div class="summary-cards">
                    <div class="summary-card total-revenue">
                        <div class="card-header">
                            <h4>Total Revenue</h4>
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="card-body">
                            <h2>${{ formatNumber(revenueReport.summary?.total_revenue || 0) }}</h2>
                            <div class="card-trend">
                <span class="trend positive" v-if="revenueReport.summary?.revenue_growth > 0">
                  <i class="fas fa-arrow-up"></i>
                  {{ revenueReport.summary?.revenue_growth }}%
                </span>
                                <span class="trend negative" v-else-if="revenueReport.summary?.revenue_growth < 0">
                  <i class="fas fa-arrow-down"></i>
                  {{ Math.abs(revenueReport.summary?.revenue_growth) }}%
                </span>
                                <span class="trend neutral" v-else>
                  {{ revenueReport.summary?.revenue_growth }}%
                </span>
                                <span class="trend-label">Growth</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card recurring">
                        <div class="card-header">
                            <h4>Recurring Revenue</h4>
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="card-body">
                            <h2>${{ formatNumber(revenueReport.summary?.recurring_revenue || 0) }}</h2>
                            <div class="card-subtitle">MRR</div>
                        </div>
                    </div>

                    <div class="summary-card average">
                        <div class="card-header">
                            <h4>Average per Tenant</h4>
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="card-body">
                            <h2>${{ formatNumber(revenueReport.summary?.average_revenue_per_tenant || 0) }}</h2>
                            <div class="card-stats">
                                <span class="stat-value">{{ revenueReport.top_performers?.tenants?.length || 0 }}</span>
                                <span class="stat-label">Top Performers</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card transactions">
                        <div class="card-header">
                            <h4>Transactions</h4>
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ formatNumber(revenueReport.summary?.total_transactions || 0) }}</h2>
                            <div class="card-subtitle">This Period</div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Breakdown -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h4>Revenue Breakdown</h4>
                        <div class="chart-actions">
                            <button class="btn-chart-action" @click="revenueChartType = 'pie'" :class="{ active: revenueChartType === 'pie' }">
                                <i class="fas fa-chart-pie"></i>
                            </button>
                            <button class="btn-chart-action" @click="revenueChartType = 'bar'" :class="{ active: revenueChartType === 'bar' }">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <PieChart
                            v-if="revenueChartType === 'pie' && revenueChartData"
                            :data="revenueChartData"
                            :options="revenueChartOptions"
                        />
                        <BarChart
                            v-else-if="revenueChartType === 'bar' && revenueChartData"
                            :data="revenueChartData"
                            :options="revenueChartOptions"
                        />
                        <div v-else class="chart-placeholder">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="table-card">
                    <div class="table-header">
                        <h4>Top Performing Tenants</h4>
                    </div>
                    <div class="table-body">
                        <table class="mini-table">
                            <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Revenue</th>
                                <th>Plan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="tenant in revenueReport.top_performers?.tenants" :key="tenant.id">
                                <td>
                                    <div class="tenant-cell">
                                        <div class="tenant-avatar" :style="{ backgroundColor: getAvatarColor(tenant.name) }">
                                            {{ tenant.name?.charAt(0) }}
                                        </div>
                                        <div class="tenant-info">
                                            <strong>{{ tenant.name }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="revenue-value">${{ formatNumber(tenant.revenue || 0) }}</span>
                                </td>
                                <td>
                    <span class="plan-badge mini" :class="tenant.plan">
                      {{ tenant.plan }}
                    </span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Revenue Projections -->
                <div class="chart-card" v-if="revenueFilters.include_projections">
                    <div class="chart-header">
                        <h4>Revenue Projections</h4>
                    </div>
                    <div class="chart-body">
                        <div class="projections-list">
                            <div v-for="projection in revenueReport.projections" :key="projection.month" class="projection-item">
                                <div class="projection-month">{{ projection.month }}</div>
                                <div class="projection-amount">${{ formatNumber(projection.projected_revenue) }}</div>
                                <div class="projection-growth" :class="{ positive: projection.growth_from_current > 0, negative: projection.growth_from_current < 0 }">
                                    <i :class="projection.growth_from_current > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
                                    {{ Math.abs(projection.growth_from_current) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Report -->
        <div v-else-if="activeTab === 'usage'" class="report-content">
            <div class="report-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Period</label>
                        <select v-model="usageFilters.period" @change="fetchUsageReport" class="form-control">
                            <option value="day">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Metric</label>
                        <select v-model="usageFilters.metric" @change="fetchUsageReport" class="form-control">
                            <option value="all">All Metrics</option>
                            <option value="users">Users</option>
                            <option value="vouchers">Vouchers</option>
                            <option value="storage">Storage</option>
                            <option value="api_calls">API Calls</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Tenant</label>
                        <select v-model="usageFilters.tenant_id" @change="fetchUsageReport" class="form-control">
                            <option value="">All Tenants</option>
                            <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                                {{ tenant.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="report-grid">
                <!-- Usage Summary -->
                <div class="summary-cards">
                    <div class="summary-card users">
                        <div class="card-header">
                            <h4>Total Users</h4>
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ formatNumber(usageReport.summary?.total_users || 0) }}</h2>
                            <div class="card-stats">
                                <span class="stat-value">{{ formatNumber(usageReport.summary?.avg_users_per_tenant || 0) }}</span>
                                <span class="stat-label">Avg per Tenant</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card vouchers">
                        <div class="card-header">
                            <h4>Total Vouchers</h4>
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ formatNumber(usageReport.summary?.total_vouchers || 0) }}</h2>
                            <div class="card-stats">
                                <span class="stat-value">{{ formatNumber(usageReport.summary?.avg_vouchers_per_tenant || 0) }}</span>
                                <span class="stat-label">Avg per Tenant</span>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card storage">
                        <div class="card-header">
                            <h4>Storage Used</h4>
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ formatStorage(usageReport.summary?.storage_used_mb || 0) }}</h2>
                            <div class="progress-bar">
                                <div class="progress-fill" :style="{ width: (usageReport.summary?.storage_used_mb / 1024 / 100 * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card api">
                        <div class="card-header">
                            <h4>API Calls Today</h4>
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="card-body">
                            <h2>{{ formatNumber(usageReport.summary?.api_calls_today || 0) }}</h2>
                            <div class="card-trend">
                <span class="trend positive">
                  <i class="fas fa-arrow-up"></i>
                  15%
                </span>
                                <span class="trend-label">vs Yesterday</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Trends -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h4>Usage Trends</h4>
                    </div>
                    <div class="chart-body">
                        <LineChart
                            v-if="usageChartData"
                            :data="usageChartData"
                            :options="usageChartOptions"
                        />
                        <div v-else class="chart-placeholder">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Limit Utilization -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h4>Limit Utilization</h4>
                    </div>
                    <div class="chart-body">
                        <div class="utilization-stats">
                            <div class="utilization-item">
                                <div class="utilization-label">
                                    <span>User Utilization</span>
                                    <span>{{ usageReport.limit_utilization?.avg_user_utilization || 0 }}%</span>
                                </div>
                                <div class="utilization-bar">
                                    <div class="bar-fill" :style="{ width: usageReport.limit_utilization?.avg_user_utilization + '%' }"></div>
                                </div>
                            </div>

                            <div class="utilization-item">
                                <div class="utilization-label">
                                    <span>Voucher Utilization</span>
                                    <span>{{ usageReport.limit_utilization?.avg_voucher_utilization || 0 }}%</span>
                                </div>
                                <div class="utilization-bar">
                                    <div class="bar-fill" :style="{ width: usageReport.limit_utilization?.avg_voucher_utilization + '%' }"></div>
                                </div>
                            </div>

                            <div class="utilization-metrics">
                                <div class="metric">
                                    <span class="metric-value">{{ usageReport.limit_utilization?.tenants_near_limit || 0 }}</span>
                                    <span class="metric-label">Tenants Near Limit</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value">{{ usageReport.limit_utilization?.tenants_over_limit || 0 }}</span>
                                    <span class="metric-label">Tenants Over Limit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Usage Tenants -->
                <div class="table-card">
                    <div class="table-header">
                        <h4>Top Usage Tenants</h4>
                    </div>
                    <div class="table-body">
                        <table class="mini-table">
                            <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Users</th>
                                <th>Vouchers</th>
                                <th>Usage %</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="tenant in usageReport.top_tenants" :key="tenant.id">
                                <td>
                                    <div class="tenant-cell">
                                        <div class="tenant-avatar" :style="{ backgroundColor: getAvatarColor(tenant.name) }">
                                            {{ tenant.name?.charAt(0) }}
                                        </div>
                                        <div class="tenant-info">
                                            <strong>{{ tenant.name }}</strong>
                                            <small>{{ tenant.plan }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="metric-value">{{ tenant.users }}</span>
                                </td>
                                <td>
                                    <span class="metric-value">{{ tenant.vouchers_today }}</span>
                                </td>
                                <td>
                                    <div class="usage-percentage">
                                        <div class="percentage-bar">
                                            <div class="percentage-fill" :style="{ width: tenant.usage_percentage + '%' }"></div>
                                        </div>
                                        <span class="percentage-value">{{ tenant.usage_percentage }}%</span>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Anomalies -->
                <div class="table-card" v-if="usageReport.anomalies?.length > 0">
                    <div class="table-header">
                        <h4>Usage Anomalies</h4>
                        <span class="badge badge-warning">{{ usageReport.anomalies.length }}</span>
                    </div>
                    <div class="table-body">
                        <div class="anomalies-list">
                            <div v-for="anomaly in usageReport.anomalies" :key="anomaly.tenant_id" class="anomaly-item">
                                <div class="anomaly-header">
                                    <strong>{{ anomaly.tenant_name }}</strong>
                                    <span class="anomaly-type">High Utilization</span>
                                </div>
                                <div class="anomaly-metrics">
                  <span class="metric">
                    <i class="fas fa-users"></i>
                    {{ anomaly.user_utilization }}%
                  </span>
                                    <span class="metric">
                    <i class="fas fa-ticket-alt"></i>
                    {{ anomaly.voucher_utilization }}%
                  </span>
                                </div>
                                <div class="anomaly-recommendation">
                                    <i class="fas fa-lightbulb"></i>
                                    {{ anomaly.recommendation }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import LineChart from '@/components/charts/LineChart.vue'
import BarChart from '@/components/charts/BarChart.vue'
import PieChart from '@/components/charts/PieChart.vue'

export default {
    name: 'ReportsDashboard',
    components: { LineChart, BarChart, PieChart },

    data() {
        return {
            activeTab: 'tenants',
            tabs: [
                { id: 'tenants', name: 'Tenants', icon: 'fas fa-building' },
                { id: 'revenue', name: 'Revenue', icon: 'fas fa-dollar-sign' },
                { id: 'usage', name: 'Usage', icon: 'fas fa-chart-line' }
            ],
            tenantFilters: {
                period: 'month',
                group_by: 'plan',
                limit: 10
            },
            revenueFilters: {
                period: 'month',
                group_by: 'plan',
                include_projections: true
            },
            usageFilters: {
                period: 'week',
                metric: 'all',
                tenant_id: ''
            },
            chartType: 'line',
            revenueChartType: 'pie',
            refreshing: false,
            tenantsReport: {},
            revenueReport: {},
            usageReport: {}
        }
    },

    computed: {
        ...mapState(['tenants']),

        growthChartData() {
            if (!this.tenantsReport.growth?.daily_growth) return null

            return {
                labels: this.tenantsReport.growth.daily_growth.map(item => item.period),
                datasets: [
                    {
                        label: 'New Tenants',
                        data: this.tenantsReport.growth.daily_growth.map(item => item.new_tenants),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Cumulative Total',
                        data: this.tenantsReport.growth.daily_growth.map(item => item.cumulative_total),
                        borderColor: '#10b981',
                        borderDash: [5, 5],
                        fill: false
                    }
                ]
            }
        },

        chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString()
                            }
                        }
                    }
                }
            }
        },

        revenueChartData() {
            if (!this.revenueReport.breakdown?.by_plan) return null

            if (this.revenueChartType === 'pie') {
                return {
                    labels: this.revenueReport.breakdown.by_plan.map(item => item.plan),
                    datasets: [{
                        data: this.revenueReport.breakdown.by_plan.map(item => item.revenue),
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        ]
                    }]
                }
            } else {
                return {
                    labels: this.revenueReport.breakdown.by_plan.map(item => item.plan),
                    datasets: [{
                        label: 'Revenue',
                        data: this.revenueReport.breakdown.by_plan.map(item => item.revenue),
                        backgroundColor: '#3b82f6'
                    }]
                }
            }
        },

        revenueChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        },

        usageChartData() {
            if (!this.usageReport.trends?.data) return null

            const metrics = this.usageFilters.metric === 'all'
                ? ['users', 'vouchers', 'api_calls', 'storage_mb']
                : [this.usageFilters.metric]

            const datasets = metrics.map((metric, index) => {
                const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
                return {
                    label: this.formatMetricLabel(metric),
                    data: this.usageReport.trends.data.map(item => item[metric]),
                    borderColor: colors[index],
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: index === 0
                }
            })

            return {
                labels: this.usageReport.trends.data.map(item => item.period),
                datasets
            }
        },

        usageChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString()
                            }
                        }
                    }
                }
            }
        }
    },

    created() {
        this.fetchTenantsReport()
        this.fetchTenants()
    },

    methods: {
        ...mapActions(['fetchTenantsReportData', 'fetchRevenueReportData', 'fetchUsageReportData', 'fetchTenants']),

        async fetchTenantsReport() {
            try {
                const data = await this.fetchTenantsReportData(this.tenantFilters)
                this.tenantsReport = data
            } catch (error) {
                this.$toast.error('Failed to fetch tenants report')
            }
        },

        async fetchRevenueReport() {
            try {
                const data = await this.fetchRevenueReportData(this.revenueFilters)
                this.revenueReport = data
            } catch (error) {
                this.$toast.error('Failed to fetch revenue report')
            }
        },

        async fetchUsageReport() {
            try {
                const data = await this.fetchUsageReportData(this.usageFilters)
                this.usageReport = data
            } catch (error) {
                this.$toast.error('Failed to fetch usage report')
            }
        },

        async refreshAll() {
            this.refreshing = true
            try {
                await Promise.all([
                    this.fetchTenantsReport(),
                    this.fetchRevenueReport(),
                    this.fetchUsageReport()
                ])
                this.$toast.success('Reports refreshed')
            } catch (error) {
                this.$toast.error('Failed to refresh reports')
            } finally {
                this.refreshing = false
            }
        },

        exportAll() {
            // Implement export functionality
            this.$toast.info('Export feature coming soon')
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(value)
        },

        formatStorage(mb) {
            if (mb >= 1024) {
                return `${(mb / 1024).toFixed(1)} GB`
            }
            return `${mb} MB`
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString()
        },

        formatMetricLabel(metric) {
            const labels = {
                users: 'Users',
                vouchers: 'Vouchers',
                storage_mb: 'Storage (MB)',
                api_calls: 'API Calls'
            }
            return labels[metric] || metric
        },

        getAvatarColor(name) {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            const index = name?.charCodeAt(0) % colors.length
            return colors[index]
        }
    }
}
</script>

<style scoped>
.reports-dashboard {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.reports-nav {
    margin-bottom: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.nav-tabs {
    display: flex;
    gap: 4px;
}

.nav-tab {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6b7280;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.nav-tab:hover {
    color: #4b5563;
}

.nav-tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
    background: rgba(59, 130, 246, 0.05);
}

.report-filters {
    background: #f9fafb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
}

.filter-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.filter-group input[type="checkbox"] {
    margin-right: 8px;
}

.report-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 20px;
}

.summary-cards {
    grid-column: span 12;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.summary-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.summary-card.total {
    border-top: 4px solid #3b82f6;
}

.summary-card.active {
    border-top: 4px solid #10b981;
}

.summary-card.suspended {
    border-top: 4px solid #ef4444;
}

.summary-card.retention {
    border-top: 4px solid #8b5cf6;
}

.summary-card.total-revenue {
    border-top: 4px solid #f59e0b;
}

.summary-card.recurring {
    border-top: 4px solid #3b82f6;
}

.summary-card.average {
    border-top: 4px solid #10b981;
}

.summary-card.transactions {
    border-top: 4px solid #8b5cf6;
}

.summary-card.users {
    border-top: 4px solid #3b82f6;
}

.summary-card.vouchers {
    border-top: 4px solid #10b981;
}

.summary-card.storage {
    border-top: 4px solid #f59e0b;
}

.summary-card.api {
    border-top: 4px solid #8b5cf6;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.card-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.card-header i {
    font-size: 1.25rem;
    color: #9ca3af;
}

.card-body h2 {
    margin: 0 0 8px;
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
}

.card-trend {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
}

.trend {
    display: flex;
    align-items: center;
    gap: 2px;
    font-weight: 600;
}

.trend.positive {
    color: #10b981;
}

.trend.negative {
    color: #ef4444;
}

.trend.neutral {
    color: #6b7280;
}

.trend-label {
    color: #9ca3af;
}

.card-stats {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
}

.card-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 4px;
}

.progress-bar {
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 12px;
}

.progress-fill {
    height: 100%;
    background: #10b981;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.chart-card {
    grid-column: span 6;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.chart-card.full-width {
    grid-column: span 12;
}

.chart-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.chart-actions {
    display: flex;
    gap: 4px;
}

.btn-chart-action {
    width: 32px;
    height: 32px;
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 6px;
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-chart-action:hover {
    background: #f9fafb;
}

.btn-chart-action.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.chart-body {
    padding: 20px;
    height: 300px;
}

.chart-placeholder {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.group-analysis {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.group-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-name {
    font-weight: 500;
    color: #374151;
}

.group-count {
    font-weight: 600;
    color: #1f2937;
}

.group-progress {
    display: flex;
    align-items: center;
    gap: 12px;
}

.group-percentage {
    font-size: 0.875rem;
    color: #6b7280;
    min-width: 40px;
}

.table-card {
    grid-column: span 6;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.table-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.btn-link {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
}

.btn-link:hover {
    text-decoration: underline;
}

.table-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
}

.mini-table th {
    padding: 8px 0;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e5e7eb;
}

.mini-table td {
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.mini-table tr:last-child td {
    border-bottom: none;
}

.tenant-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tenant-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.tenant-info {
    flex: 1;
    min-width: 0;
}

.tenant-info strong {
    display: block;
    font-size: 0.875rem;
    color: #374151;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tenant-info small {
    display: block;
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.plan-badge.mini {
    padding: 2px 8px;
    font-size: 0.75rem;
    border-radius: 12px;
    display: inline-block;
}

.status-badge.mini {
    padding: 2px 8px;
    font-size: 0.75rem;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.timestamp {
    font-size: 0.875rem;
    color: #6b7280;
}

.revenue-value {
    font-weight: 600;
    color: #1f2937;
}

.projections-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.projection-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
}

.projection-month {
    font-weight: 500;
    color: #374151;
}

.projection-amount {
    font-weight: 600;
    color: #1f2937;
}

.projection-growth {
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    font-size: 0.875rem;
}

.projection-growth.positive {
    color: #10b981;
}

.projection-growth.negative {
    color: #ef4444;
}

.utilization-stats {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.utilization-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.utilization-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.utilization-label span:first-child {
    color: #374151;
    font-weight: 500;
}

.utilization-label span:last-child {
    color: #1f2937;
    font-weight: 600;
}

.utilization-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.utilization-metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 16px;
}

.metric {
    text-align: center;
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
}

.metric-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}

.metric-label {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.usage-percentage {
    display: flex;
    align-items: center;
    gap: 12px;
}

.percentage-bar {
    flex: 1;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.percentage-fill {
    height: 100%;
    background: #10b981;
    border-radius: 3px;
}

.percentage-value {
    font-weight: 600;
    color: #374151;
    min-width: 40px;
    font-size: 0.875rem;
}

.anomalies-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.anomaly-item {
    padding: 16px;
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 6px;
}

.anomaly-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.anomaly-type {
    background: #f59e0b;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.anomaly-metrics {
    display: flex;
    gap: 16px;
    margin-bottom: 8px;
}

.anomaly-metrics .metric {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #92400e;
    font-weight: 500;
    font-size: 0.875rem;
}

.anomaly-recommendation {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #92400e;
    font-size: 0.875rem;
    font-style: italic;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-warning:hover {
    background: #fef3c7;
    color: #92400e;
}
</style>
