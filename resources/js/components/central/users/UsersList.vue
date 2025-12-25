<template>
    <div class="users-list">
        <div class="page-header">
            <h1 class="page-title">Users Management</h1>
            <div class="page-actions">
                <router-link to="/users/create" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> New User
                </router-link>
                <button class="btn btn-secondary" @click="exportUsers">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ stats.total_users }}</h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ stats.active_users }}</h3>
                    <p>Active Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon admins">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ stats.admins }}</h3>
                    <p>Administrators</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon suspended">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ stats.suspended }}</h3>
                    <p>Suspended</p>
                </div>
            </div>
        </div>

        <div class="filters-section">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Search</label>
                    <input
                        type="text"
                        v-model="filters.search"
                        placeholder="Search by name, email, phone..."
                        class="form-control"
                        @input="debouncedFilter"
                    >
                </div>

                <div class="filter-group">
                    <label>Role</label>
                    <select v-model="filters.role" @change="applyFilters" class="form-control">
                        <option value="">All Roles</option>
                        <option value="admin">Administrator</option>
                        <option value="staff">Staff</option>
                        <option value="user">User</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select v-model="filters.status" @change="applyFilters" class="form-control">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Tenant</label>
                    <select v-model="filters.tenant_id" @change="applyFilters" class="form-control">
                        <option value="">All Tenants</option>
                        <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                            {{ tenant.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Tenant</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody v-if="loading">
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="loading-spinner"></div>
                    </td>
                </tr>
                </tbody>
                <tbody v-else-if="users.length === 0">
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        No users found
                    </td>
                </tr>
                </tbody>
                <tbody v-else>
                <tr v-for="user in users" :key="user.uuid">
                    <td>
                        <div class="user-info">
                            <div class="user-avatar" :style="{ backgroundColor: getAvatarColor(user.name) }">
                                {{ user.name?.charAt(0) }}
                            </div>
                            <div>
                                <strong>{{ user.name }}</strong>
                                <small>{{ user.email }}</small>
                                <div class="user-meta">
                    <span class="phone" v-if="user.phone">
                      <i class="fas fa-phone"></i> {{ user.phone }}
                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
              <span class="role-badge" :class="user.role">
                <i :class="getRoleIcon(user.role)"></i>
                {{ user.role_name }}
              </span>
                    </td>
                    <td>
              <span v-if="user.tenant" class="tenant-badge">
                {{ user.tenant.name }}
              </span>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>
              <span class="status-badge" :class="user.is_active ? 'active' : 'inactive'">
                <i :class="user.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                {{ user.is_active ? 'Active' : 'Inactive' }}
              </span>
                    </td>
                    <td>
              <span v-if="user.last_login_at" class="timestamp">
                {{ formatTimeAgo(user.last_login_at) }}
              </span>
                        <span v-else class="text-muted">Never</span>
                    </td>
                    <td>
                        {{ formatDate(user.created_at) }}
                    </td>
                    <td>
                        <div class="action-buttons">
                            <router-link
                                :to="`/users/${user.uuid}`"
                                class="btn-action btn-view"
                                title="View Details"
                            >
                                <i class="fas fa-eye"></i>
                            </router-link>

                            <button
                                v-if="user.is_active"
                                @click="suspendUser(user)"
                                class="btn-action btn-suspend"
                                title="Suspend User"
                                :disabled="user.uuid === currentUserUuid"
                            >
                                <i class="fas fa-pause"></i>
                            </button>

                            <button
                                v-else
                                @click="activateUser(user)"
                                class="btn-action btn-activate"
                                title="Activate User"
                            >
                                <i class="fas fa-play"></i>
                            </button>

                            <button
                                @click="editUser(user)"
                                class="btn-action btn-edit"
                                title="Edit User"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <button
                                @click="deleteUser(user)"
                                class="btn-action btn-delete"
                                title="Delete User"
                                :disabled="user.uuid === currentUserUuid"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination-section" v-if="pagination.total > 0">
            <div class="pagination-info">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} users
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

        <!-- Action Modals -->
        <Modal
            v-if="selectedUser && (actionType === 'suspend' || actionType === 'activate')"
            :show="showActionModal"
            @close="closeModal"
            :title="actionModalTitle"
        >
            <template #body>
                <div v-if="actionType === 'suspend'">
                    <p>Are you sure you want to suspend <strong>{{ selectedUser.name }}</strong>?</p>
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
                    <div class="alert alert-warning" v-if="selectedUser.uuid === currentUserUuid">
                        <i class="fas fa-exclamation-triangle"></i>
                        You cannot suspend your own account
                    </div>
                </div>

                <div v-else-if="actionType === 'activate'">
                    <p>Are you sure you want to activate <strong>{{ selectedUser.name }}</strong>?</p>
                </div>
            </template>

            <template #footer>
                <button class="btn btn-secondary" @click="closeModal">
                    Cancel
                </button>
                <button
                    class="btn"
                    :class="actionType === 'suspend' ? 'btn-danger' : 'btn-primary'"
                    @click="confirmAction"
                    :disabled="actionLoading || (actionType === 'suspend' && selectedUser.uuid === currentUserUuid)"
                >
                    <span v-if="actionLoading">Processing...</span>
                    <span v-else>
            {{ actionType === 'suspend' ? 'Suspend' : 'Activate' }}
          </span>
                </button>
            </template>
        </Modal>

        <Modal
            v-if="selectedUser && actionType === 'delete'"
            :show="showActionModal"
            @close="closeModal"
            title="Delete User"
            variant="danger"
        >
            <template #body>
                <div class="delete-warning">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="warning-content">
                        <h4>Are you sure you want to delete this user?</h4>
                        <p>This action cannot be undone. All data associated with <strong>{{ selectedUser.name }}</strong> will be permanently deleted.</p>
                        <div class="alert alert-danger" v-if="selectedUser.role === 'admin' && adminCount <= 1">
                            <i class="fas fa-exclamation-circle"></i>
                            Cannot delete the last administrator
                        </div>
                        <div class="alert alert-warning" v-if="selectedUser.uuid === currentUserUuid">
                            <i class="fas fa-exclamation-triangle"></i>
                            You cannot delete your own account
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <button class="btn btn-secondary" @click="closeModal">
                    Cancel
                </button>
                <button
                    class="btn btn-danger"
                    @click="confirmDelete"
                    :disabled="actionLoading ||
            (selectedUser.role === 'admin' && adminCount <= 1) ||
            selectedUser.uuid === currentUserUuid"
                >
                    <span v-if="actionLoading">Deleting...</span>
                    <span v-else>Delete Permanently</span>
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
    name: 'UsersList',
    components: { Modal },

    data() {
        return {
            filters: {
                search: '',
                role: '',
                status: '',
                tenant_id: '',
                sort_by: 'created_at',
                sort_order: 'desc',
                per_page: 20
            },
            selectedUser: null,
            showActionModal: false,
            actionType: '',
            suspensionReason: '',
            suspensionDuration: null,
            actionLoading: false,
            stats: {
                total_users: 0,
                active_users: 0,
                admins: 0,
                suspended: 0
            }
        }
    },

    computed: {
        ...mapState({
            users: state => state.users.items,
            pagination: state => state.users.pagination,
            loading: state => state.users.loading,
            tenants: state => state.tenants.items,
            currentUserUuid: state => state.user?.uuid,
            adminCount: state => state.users.stats?.admins || 0
        }),

        actionModalTitle() {
            if (this.actionType === 'suspend') return 'Suspend User'
            if (this.actionType === 'activate') return 'Activate User'
            if (this.actionType === 'delete') return 'Delete User'
            return ''
        }
    },

    created() {
        this.fetchUsers()
        this.fetchTenants()
        this.debouncedFilter = debounce(this.applyFilters, 500)
    },

    methods: {
        ...mapActions(['fetchUsers', 'fetchTenants', 'suspendUserAction', 'activateUserAction', 'deleteUserAction']),

        applyFilters() {
            this.fetchUsers(this.filters)
        },

        changePage(page) {
            this.filters.page = page
            this.fetchUsers(this.filters)
        },

        suspendUser(user) {
            if (user.uuid === this.currentUserUuid) {
                this.$toast.error('You cannot suspend your own account')
                return
            }
            this.selectedUser = user
            this.actionType = 'suspend'
            this.showActionModal = true
        },

        activateUser(user) {
            this.selectedUser = user
            this.actionType = 'activate'
            this.showActionModal = true
        },

        editUser(user) {
            this.$router.push(`/users/${user.uuid}/edit`)
        },

        deleteUser(user) {
            if (user.uuid === this.currentUserUuid) {
                this.$toast.error('You cannot delete your own account')
                return
            }
            this.selectedUser = user
            this.actionType = 'delete'
            this.showActionModal = true
        },

        async confirmAction() {
            this.actionLoading = true
            try {
                if (this.actionType === 'suspend') {
                    await this.suspendUserAction({
                        uuid: this.selectedUser.uuid,
                        reason: this.suspensionReason,
                        duration_days: this.suspensionDuration
                    })
                    this.$toast.success('User suspended successfully')
                } else if (this.actionType === 'activate') {
                    await this.activateUserAction(this.selectedUser.uuid)
                    this.$toast.success('User activated successfully')
                }
                this.closeModal()
                this.fetchUsers(this.filters)
            } catch (error) {
                this.$toast.error(error.response?.data?.message || 'Action failed')
            } finally {
                this.actionLoading = false
            }
        },

        async confirmDelete() {
            this.actionLoading = true
            try {
                await this.deleteUserAction(this.selectedUser.uuid)
                this.$toast.success('User deleted successfully')
                this.closeModal()
                this.fetchUsers(this.filters)
            } catch (error) {
                this.$toast.error(error.response?.data?.message || 'Deletion failed')
            } finally {
                this.actionLoading = false
            }
        },

        closeModal() {
            this.showActionModal = false
            this.selectedUser = null
            this.actionType = ''
            this.suspensionReason = ''
            this.suspensionDuration = null
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString()
        },

        formatTimeAgo(date) {
            const now = new Date()
            const past = new Date(date)
            const diffMs = now - past
            const diffMins = Math.floor(diffMs / 60000)
            const diffHours = Math.floor(diffMs / 3600000)
            const diffDays = Math.floor(diffMs / 86400000)

            if (diffMins < 1) return 'Just now'
            if (diffMins < 60) return `${diffMins}m ago`
            if (diffHours < 24) return `${diffHours}h ago`
            if (diffDays < 7) return `${diffDays}d ago`
            return this.formatDate(date)
        },

        getAvatarColor(name) {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            const index = name?.charCodeAt(0) % colors.length
            return colors[index]
        },

        getRoleIcon(role) {
            const icons = {
                admin: 'fas fa-user-shield',
                staff: 'fas fa-user-tie',
                user: 'fas fa-user'
            }
            return icons[role] || 'fas fa-user'
        },

        exportUsers() {
            // Implement export functionality
            this.$toast.info('Export feature coming soon')
        }
    }
}
</script>

<style scoped>
.users-list {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.total {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.stat-icon.active {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-icon.admins {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.stat-icon.suspended {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.stat-content h3 {
    margin: 0 0 4px;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-content p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.125rem;
}

.user-info strong {
    display: block;
    color: #1e293b;
    font-size: 0.95rem;
}

.user-info small {
    display: block;
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 4px;
}

.user-meta {
    display: flex;
    gap: 12px;
    font-size: 0.8rem;
}

.user-meta .phone {
    color: #6b7280;
}

.user-meta .phone i {
    margin-right: 4px;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.role-badge.admin {
    background: #e0e7ff;
    color: #3730a3;
}

.role-badge.staff {
    background: #fef3c7;
    color: #92400e;
}

.role-badge.user {
    background: #f3f4f6;
    color: #4b5563;
}

.tenant-badge {
    background: #ecfdf5;
    color: #065f46;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.875rem;
    border: 1px solid #a7f3d0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge.active {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.inactive {
    background: #fee2e2;
    color: #991b1b;
}

.timestamp {
    color: #6b7280;
    font-size: 0.875rem;
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

.btn-action:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-view:hover:not(:disabled) {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.btn-suspend:hover:not(:disabled) {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.btn-activate:hover:not(:disabled) {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.btn-edit:hover:not(:disabled) {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.btn-delete:hover:not(:disabled) {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

.alert {
    padding: 12px;
    border-radius: 6px;
    margin: 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
}

.delete-warning {
    text-align: center;
    padding: 20px;
}

.warning-icon {
    width: 80px;
    height: 80px;
    background: #fee2e2;
    color: #ef4444;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 20px;
}

.warning-content h4 {
    margin: 0 0 12px;
    color: #1f2937;
}

.warning-content p {
    color: #6b7280;
    margin-bottom: 20px;
}
</style>
