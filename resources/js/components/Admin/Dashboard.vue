<template>
    <div class="dashboard">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3>UGX {{ formatNumber(todayRevenue) }}</h3>
                    <p>Today's Revenue</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sms"></i>
                </div>
                <div class="stat-content">
                    <h3>UGX {{ formatNumber(smsBalance) }}</h3>
                    <p>SMS Balance</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ activeConnections }}</h3>
                    <p>Active Connections</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ vouchersGenerated }}</h3>
                    <p>Vouchers Today</p>
                </div>
            </div>
        </div>

        <!-- Real-time payment monitoring -->
        <div class="realtime-section">
            <h4>Recent Payments</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Voucher</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="payment in recentPayments" :key="payment.id">
                        <td>{{ formatTime(payment.created_at) }}</td>
                        <td>{{ payment.customer.phone }}</td>
                        <td>UGX {{ formatNumber(payment.amount) }}</td>
                        <td>
                <span :class="`badge badge-${payment.status === 'completed' ? 'success' : 'warning'}`">
                  {{ payment.status }}
                </span>
                        </td>
                        <td>
                <span v-if="payment.voucher" class="badge badge-info">
                  {{ payment.voucher.code }}
                </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Health -->
        <div class="health-section">
            <h4>System Health</h4>
            <div class="health-grid">
                <div class="health-item" :class="{ 'healthy': mikrotikStatus }">
                    <i class="fas fa-router"></i>
                    <span>MikroTik</span>
                </div>
                <div class="health-item" :class="{ 'healthy': collectugStatus }">
                    <i class="fas fa-credit-card"></i>
                    <span>CollectUg</span>
                </div>
                <div class="health-item" :class="{ 'healthy': smsGatewayStatus }">
                    <i class="fas fa-sms"></i>
                    <span>UGSMS</span>
                </div>
                <div class="health-item" :class="{ 'healthy': queueStatus }">
                    <i class="fas fa-tasks"></i>
                    <span>Queues</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const todayRevenue = ref(0)
const smsBalance = ref(0)
const activeConnections = ref(0)
const vouchersGenerated = ref(0)
const recentPayments = ref([])
const mikrotikStatus = ref(false)
const collectugStatus = ref(false)
const smsGatewayStatus = ref(false)
const queueStatus = ref(false)

const loadDashboardData = async () => {
    try {
        const response = await axios.get('/api/admin/dashboard')
        const data = response.data

        todayRevenue.value = data.today_revenue
        smsBalance.value = data.sms_balance
        activeConnections.value = data.active_connections
        vouchersGenerated.value = data.vouchers_generated
        recentPayments.value = data.recent_payments
        mikrotikStatus.value = data.mikrotik_status
        collectugStatus.value = data.collectug_status
        smsGatewayStatus.value = data.sms_gateway_status
        queueStatus.value = data.queue_status
    } catch (error) {
        console.error('Failed to load dashboard data:', error)
    }
}

const formatNumber = (num) => {
    return new Intl.NumberFormat().format(num)
}

const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString()
}

// Auto-refresh every 30 seconds
let refreshInterval

onMounted(() => {
    loadDashboardData()
    refreshInterval = setInterval(loadDashboardData, 30000)
})

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval)
    }
})
</script>

<style scoped>
.dashboard {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 24px;
}

.stat-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

.stat-content p {
    margin: 5px 0 0;
    color: #666;
    font-size: 14px;
}

.health-section {
    margin-top: 30px;
}

.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.health-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.health-item.healthy {
    border-color: #28a745;
    background: rgba(40, 167, 69, 0.1);
}

.health-item i {
    font-size: 24px;
    margin-bottom: 10px;
    display: block;
}

.health-item span {
    font-weight: 500;
}

.realtime-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin: 30px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table th {
    border-top: none;
}
</style>
