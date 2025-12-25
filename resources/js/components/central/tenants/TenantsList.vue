<template>
    <div class="tenants-list">
        <div class="page-header">
            <h1 class="page-title">Tenants Management</h1>
            <div class="page-actions">
                <router-link to="/tenants/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Tenant
                </router-link>
                <button class="btn btn-secondary" @click="exportData">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <div class="filters-section">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Search</label>
                    <input
                        type="text"
                        v-model="filters.search"
                        placeholder="Search by name, email, domain..."
                        class="form-control"
                        @input="debouncedFilter"
                    >
                </div>

                <div class="filter-group">
                    <label>Plan</label>
                    <select v-model="filters.plan" @change="applyFilters" class="form-control">
                        <option value="">All Plans</option>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select v-model="filters.status" @change="applyFilters" class="form-control">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sort By</label>
                    <select v-model="filters.sort_by" @change="applyFilters" class="form-control">
                        <option value="created_at">Created Date</option>
                        <option value="name">Name</option>
                        <option value="plan">Plan</option>
                        <option value="next_billing_date">Billing Date</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Domain</th>
                    <th>Plan</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th>Billing Date</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody v-if="loading">
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="loading-spinner"></div>
                    </td>
                </tr>
                </tbody>
                <tbody v-else-if="tenants.length === 0">
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        No tenants found
                    </td>
                </tr>
                </tbody>
                <tbody v-else>
                <tr v-for="tenant in tenants" :key="tenant.id">
                    <td>
                        <div class="tenant-info">
                            <div class="tenant-avatar" :style="{ backgroundColor: getAvatarColor(tenant.name) }">
                                {{ tenant.name?.charAt(0) }}
                            </div>
                            <div>
                                <strong>{{ tenant.name }}</strong>
                                <small>{{ tenant.email }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="domain-badge">{{ tenant.primary_domain || tenant.slug }}.{{ baseDomain }}</span>
                    </td>
                    <td>
              <span class="plan-badge" :class="tenant.plan">
                {{ tenant.plan }}
              </span>
                    </td>
                    <td>
                        <div class="users-count">
                            <i class="fas fa-users"></i>
                            {{ tenant.user_count || 0 }}/{{ tenant.max_users }}
                        </div>
                    </td>
                    <td>
              <span class="status-badge" :class="tenant.is_active ? 'active' : 'suspended'">
                {{ tenant.is_active ? 'Active' : 'Suspended' }}
              </span>
                    </td>
                    <td>
              <span v-if="tenant.next_billing_date">
                {{ formatDate(tenant.next_billing_date) }}
              </span>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                        {{ formatDate(tenant.created_at) }}
                    </td>
                    <td>
                        <div class="action-buttons">
                            <router-link
                                :to="`/tenants/${tenant.id}`"
                                class="btn-action btn-view"
                                title="View Details"
                            >
                                <i class="fas fa-eye"></i>
                            </router-link>

                            <button
                                v-if="tenant.is_active"
                                @click="suspendTenant(tenant)"
                                class="btn-action btn-suspend"
                                title="Suspend Tenant"
                            >
                                <i class="fas fa-pause"></i>
                            </button>

                            <button
                                v-else
                                @click="activateTenant(tenant)"
                                class="btn-action btn-activate"
                                title="Activate Tenant"
                            >
                                <i class="fas fa-play"></i>
                            </button>

                            <button
                                @click="showUsageStats(tenant)"
                                class="btn-action btn-stats"
                                title="Usage Statistics"
                            >
                                <i class="fas fa-chart-bar"></i>
                            </button>

                            <button
                                @click="editTenant(tenant)"
                                class="btn-action btn-edit"
                                title="Edit Tenant"
                            >
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination-section" v-if="pagination.total > 0">
            <div class="pagination-info">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} tenants
            </div>
            <div class="pagination-controls">
                <button
                    :disabled="pagination.current_page === 1"
                    @click="changePage(pagination.current_page - 1)"
                    class="btn-pagination"
                >
                    <i class="fas fa-chevron-left"></i>
                </button>

                <span class="page-numbers">
          <button
              v-for="page in pagination.last_page"
              :key="page"
              @click="changePage(page)"
              :class="{ active: page === pagination.current_page }"
              class="btn-page"
          >
            {{ page }}
          </button>
        </span>

                <button
                    :disabled="pagination.current_page === pagination.last_page"
                    @click="changePage(pagination.current_page + 1)"
                    class="btn-pagination"
                >
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Tenant Actions Modal -->
        <Modal
            v-if="selectedTenant"
            :show="showActionModal"
            @close="closeModal"
            :title="actionModalTitle"
        >
            <template #body>
                <div v-if="actionType === 'suspend'">
                    <p>Are you sure you want to suspend <strong>{{ selectedTenant.name }}</strong>?</p>
                    <div class="form-group">
                        <label for="suspensionReason">Reason (optional)</label>
                        <textarea
                            id="suspensionReason"
                            v-model="suspensionReason"
                            class="form-control"
                            placeholder="Enter reason for suspension..."
                            rows="3"
                        ></textarea>
                    </div>
                    <div class="form-group">
                        <label for="suspensionDuration">Duration (days, optional)</label>
                        <input
                            type="number"
                            id="suspensionDuration"
                            v-model="suspensionDuration"
                            class="form-control"
                            placeholder="Leave empty for indefinite"
                            min="1"
                            max="365"
                        >
                    </div>
                </div>

                <div v-else-if="actionType === 'activate'">
                    <p>Are you sure you want to activate <strong>{{ selectedTenant.name }}</strong>?</p>
                </div>

                <div v-else-if="actionType === 'usage'">
                    <div class="usage-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3>{{ usageStats?.users?.total || 0 }}</h3>
                                <p>Total Users</p>
                                <div class="stat-progress">
                                    <div
                                        class="progress-bar"
                                        :style="{ width: getUsagePercentage(usageStats?.users) + '%' }"
                                    ></div>
                                </div>
                                <small>{{ usageStats?.users?.remaining || 0 }} remaining of {{ usageStats?.users?.limit || 0 }}</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3>{{ usageStats?.vouchers_today?.total || 0 }}</h3>
                                <p>Vouchers Today</p>
                                <div class="stat-progress">
                                    <div
                                        class="progress-bar"
                                        :style="{ width: getUsagePercentage(usageStats?.vouchers_today) + '%' }"
                                    ></div>
                                </div>
                                <small>{{ usageStats?.vouchers_today?.remaining || 0 }} remaining of {{ usageStats?.vouchers_today?.limit || 0 }}</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-content">
                                <h3>${{ usageStats?.payments_month?.revenue || 0 }}</h3>
                                <p>Monthly Revenue</p>
                                <div class="stat-info">
                                    <span>{{ usageStats?.payments_month?.total || 0 }} payments</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer v-if="actionType !== 'usage'">
                <button class="btn btn-secondary" @click="closeModal">
                    Cancel
                </button>
                <button
                    class="btn"
                    :class="actionType === 'suspend' ? 'btn-danger' : 'btn-primary'"
                    @click="confirmAction"
                    :disabled="actionLoading"
                >
                    <span v-if="actionLoading">Processing...</span>
                    <span v-else>
            {{ actionType === 'suspend' ? 'Suspend' : 'Activate' }}
          </span>
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import Modal from '@/components/common/Modal.vue'
import { debounce } from 'lodash'

export default {
    name: 'TenantsList',
    components: { Modal },

    data() {
        return {
            filters: {
                search: '',
                plan: '',
                status: '',
                sort_by: 'created_at',
                sort_order: 'desc',
                per_page: 20
            },
            selectedTenant: null,
            showActionModal: false,
            actionType: '',
            suspensionReason: '',
            suspensionDuration: null,
            actionLoading: false,
            usageStats: null
        }
    },

    computed: {
        ...mapState({
            tenants: state => state.tenants.items,
            pagination: state => state.tenants.pagination,
            loading: state => state.tenants.loading,
            baseDomain: state => state.settings.baseDomain
        }),

        actionModalTitle() {
            if (this.actionType === 'suspend') return 'Suspend Tenant'
            if (this.actionType === 'activate') return 'Activate Tenant'
            if (this.actionType === 'usage') return 'Usage Statistics'
            return ''
        }
    },

    created() {
        this.fetchTenants()
        this.debouncedFilter = debounce(this.applyFilters, 500)
    },

    methods: {
        ...mapActions(['fetchTenants', 'suspendTenantAction', 'activateTenantAction', 'fetchTenantUsage']),

        applyFilters() {
            this.fetchTenants(this.filters)
        },

        changePage(page) {
            this.filters.page = page
            this.fetchTenants(this.filters)
        },

        suspendTenant(tenant) {
            this.selectedTenant = tenant
            this.actionType = 'suspend'
            this.showActionModal = true
        },

        activateTenant(tenant) {
            this.selectedTenant = tenant
            this.actionType = 'activate'
            this.showActionModal = true
        },

        showUsageStats(tenant) {
            this.selectedTenant = tenant
            this.actionType = 'usage'
            this.fetchUsageStats(tenant.id)
            this.showActionModal = true
        },

        editTenant(tenant) {
            this.$router.push(`/tenants/${tenant.id}/edit`)
        },

        async fetchUsageStats(tenantId) {
            try {
                const response = await this.fetchTenantUsage(tenantId)
                this.usageStats = response.data.usage
            } catch (error) {
                this.$toast.error('Failed to fetch usage statistics')
            }
        },

        async confirmAction() {
            this.actionLoading = true
            try {
                if (this.actionType === 'suspend') {
                    await this.suspendTenantAction({
                        id: this.selectedTenant.id,
                        reason: this.suspensionReason,
                        duration_days: this.suspensionDuration
                    })
                    this.$toast.success('Tenant suspended successfully')
                } else if (this.actionType === 'activate') {
                    await this.activateTenantAction(this.selectedTenant.id)
                    this.$toast.success('Tenant activated successfully')
                }
                this.closeModal()
                this.fetchTenants(this.filters)
            } catch (error) {
                this.$toast.error(error.response?.data?.message || 'Action failed')
            } finally {
                this.actionLoading = false
            }
        },

        closeModal() {
            this.showActionModal = false
            this.selectedTenant = null
            this.actionType = ''
            this.suspensionReason = ''
            this.suspensionDuration = null
            this.usageStats = null
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString()
        },

        getAvatarColor(name) {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            const index = name?.charCodeAt(0) % colors.length
            return colors[index]
        },

        getUsagePercentage(stat) {
            if (!stat || !stat.limit) return 0
            return Math.min((stat.total / stat.limit) * 100, 100)
        },

        exportData() {
            // Implement export functionality
            this.$toast.info('Export feature coming soon')
        }
    }
}
</script>

<style scoped>
.tenants-list {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.page-actions {
    display: flex;
    gap: 12px;
}

.filters-section {
    background: #f8fafc;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 24px;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.filter-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #475569;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f1f5f9;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.tenant-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tenant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.125rem;
}

.tenant-info strong {
    display: block;
    color: #1e293b;
    font-size: 0.95rem;
}

.tenant-info small {
    color: #64748b;
    font-size: 0.85rem;
}

.domain-badge {
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.875rem;
}

.plan-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.plan-badge.basic {
    background: #dbeafe;
    color: #1e40af;
}

.plan-badge.premium {
    background: #fef3c7;
    color: #92400e;
}

.plan-badge.enterprise {
    background: #f3e8ff;
    color: #6b21a8;
}

.users-count {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #475569;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge.active {
    background: #dcfce7;
    color: #166534;
}

.status-badge.suspended {
    background: #fee2e2;
    color: #991b1b;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-view:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.btn-suspend:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.btn-activate:hover {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.btn-stats:hover {
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
}

.btn-edit:hover {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.pagination-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-pagination {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-pagination:hover:not(:disabled) {
    background: #f1f5f9;
}

.btn-pagination:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-numbers {
    display: flex;
    gap: 4px;
}

.btn-page {
    width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-page:hover {
    background: #f1f5f9;
}

.btn-page.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.usage-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.stat-card {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: #3b82f6;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-content h3 {
    margin: 0 0 4px;
    font-size: 1.5rem;
    color: #1e293b;
}

.stat-content p {
    margin: 0 0 8px;
    color: #64748b;
    font-size: 0.875rem;
}

.stat-progress {
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin-bottom: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: #10b981;
    transition: width 0.3s ease;
}

.stat-content small {
    color: #94a3b8;
    font-size: 0.75rem;
}

.stat-info {
    margin-top: 8px;
}

.stat-info span {
    display: block;
    color: #64748b;
    font-size: 0.875rem;
}
</style>
