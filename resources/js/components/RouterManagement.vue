<template>
  <div class="router-management">
    <div class="management-header">
      <h2>Router Management</h2>
      <div class="header-actions">
        <button 
          @click="refreshRouters" 
          :disabled="isLoading"
          class="btn btn-secondary"
        >
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoading }"></i>
          Refresh
        </button>
        <button 
          @click="openAddRouterModal"
          class="btn btn-primary"
        >
          <i class="fas fa-plus"></i>
          Add Router
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="card-icon">
          <i class="fas fa-router"></i>
        </div>
        <div class="card-content">
          <h3>{{ summary.total_routers }}</h3>
          <p>Total Routers</p>
        </div>
      </div>
      <div class="summary-card online">
        <div class="card-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ summary.online_routers }}</h3>
          <p>Online</p>
        </div>
      </div>
      <div class="summary-card offline">
        <div class="card-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ summary.offline_routers }}</h3>
          <p>Offline</p>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search routers by name, IP, or location..."
          class="search-input"
          @input="debouncedSearch"
        />
      </div>
      <div class="filter-controls">
        <select v-model="statusFilter" @change="applyFilters" class="filter-select">
          <option value="">All Status</option>
          <option value="online">Online</option>
          <option value="offline">Offline</option>
          <option value="error">Error</option>
        </select>
        <select v-model="sortBy" @change="applyFilters" class="filter-select">
          <option value="name">Sort by Name</option>
          <option value="ip_address">Sort by IP</option>
          <option value="status">Sort by Status</option>
          <option value="created_at">Sort by Created</option>
        </select>
        <select v-model="sortOrder" @change="applyFilters" class="filter-select">
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
      </div>
    </div>

    <!-- Routers Table -->
    <div class="routers-table-container">
      <table class="routers-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>IP Address</th>
            <th>Location</th>
            <th>Status</th>
            <th>Last Seen</th>
            <th>Uptime</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoading" class="loading-row">
            <td colspan="7" class="text-center">
              <div class="loading-spinner"></div>
              Loading routers...
            </td>
          </tr>
          <tr v-else-if="routers.length === 0" class="empty-row">
            <td colspan="7" class="text-center">
              <div class="empty-state">
                <i class="fas fa-router"></i>
                <p>No routers found</p>
                <button @click="openAddRouterModal" class="btn btn-primary">
                  Add Your First Router
                </button>
              </div>
            </td>
          </tr>
          <tr 
            v-else
            v-for="router in routers" 
            :key="router.id"
            class="router-row"
            :class="getRouterRowClass(router.status)"
          >
            <td>
              <div class="router-name">
                <strong>{{ router.name }}</strong>
                <small>Port: {{ router.api_port }}</small>
              </div>
            </td>
            <td>
              <code class="ip-address">{{ router.ip_address }}</code>
            </td>
            <td>
              <div class="location-info">
                <div>{{ router.location.region }}</div>
                <small>{{ router.location.district }}</small>
              </div>
            </td>
            <td>
              <div class="status-badge" :class="getStatusClass(router.status)">
                <div class="status-indicator" :style="{ backgroundColor: getStatusColor(router.status) }"></div>
                {{ router.status.toUpperCase() }}
              </div>
            </td>
            <td>
              <span class="last-seen">{{ formatLastSeen(router.last_seen) }}</span>
            </td>
            <td>
              <span class="uptime">{{ formatUptime(router.uptime_seconds) }}</span>
            </td>
            <td>
              <div class="action-buttons">
                <button 
                  @click="testRouterConnection(router)"
                  :disabled="testingConnection === router.id"
                  class="btn btn-sm btn-outline"
                  title="Test Connection"
                >
                  <i class="fas fa-plug" :class="{ 'fa-spin': testingConnection === router.id }"></i>
                </button>
                <button 
                  @click="editRouter(router)"
                  class="btn btn-sm btn-outline"
                  title="Edit Router"
                >
                  <i class="fas fa-edit"></i>
                </button>
                <button 
                  @click="deleteRouter(router)"
                  class="btn btn-sm btn-danger"
                  title="Delete Router"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="pagination-container">
      <div class="pagination-info">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} routers
      </div>
      <div class="pagination-controls">
        <button 
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="btn btn-sm btn-outline"
        >
          <i class="fas fa-chevron-left"></i>
        </button>
        <span class="page-info">
          Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <button 
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="btn btn-sm btn-outline"
        >
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Add/Edit Router Modal -->
    <Modal 
      :show="showRouterModal"
      @close="closeRouterModal"
      :title="isEditing ? 'Edit Router' : 'Add New Router'"
      size="lg"
      :prevent-close="isSubmitting"
    >
      <form @submit.prevent="submitRouter" class="router-form">
        <div class="form-grid">
          <div class="form-group">
            <label for="router-name">Router Name *</label>
            <input
              id="router-name"
              v-model="routerForm.name"
              type="text"
              class="form-input"
              :class="{ 'error': formErrors.name }"
              placeholder="Enter router name"
              required
            />
            <span v-if="formErrors.name" class="error-message">{{ formErrors.name[0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-ip">IP Address *</label>
            <input
              id="router-ip"
              v-model="routerForm.ip_address"
              type="text"
              class="form-input"
              :class="{ 'error': formErrors.ip_address }"
              placeholder="192.168.1.1"
              required
            />
            <span v-if="formErrors.ip_address" class="error-message">{{ formErrors.ip_address[0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-port">API Port *</label>
            <input
              id="router-port"
              v-model.number="routerForm.api_port"
              type="number"
              class="form-input"
              :class="{ 'error': formErrors.api_port }"
              placeholder="8728"
              min="1"
              max="65535"
              required
            />
            <span v-if="formErrors.api_port" class="error-message">{{ formErrors.api_port[0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-username">Username *</label>
            <input
              id="router-username"
              v-model="routerForm.username"
              type="text"
              class="form-input"
              :class="{ 'error': formErrors.username }"
              placeholder="admin"
              required
            />
            <span v-if="formErrors.username" class="error-message">{{ formErrors.username[0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-password">Password *</label>
            <div class="password-input-container">
              <input
                id="router-password"
                v-model="routerForm.password"
                :type="showPassword ? 'text' : 'password'"
                class="form-input"
                :class="{ 'error': formErrors.password }"
                placeholder="Enter password"
                required
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="password-toggle"
              >
                <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
              </button>
            </div>
            <span v-if="formErrors.password" class="error-message">{{ formErrors.password[0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-region">Region *</label>
            <input
              id="router-region"
              v-model="routerForm.location.region"
              type="text"
              class="form-input"
              :class="{ 'error': formErrors['location.region'] }"
              placeholder="Central Region"
              required
            />
            <span v-if="formErrors['location.region']" class="error-message">{{ formErrors['location.region'][0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-district">District *</label>
            <input
              id="router-district"
              v-model="routerForm.location.district"
              type="text"
              class="form-input"
              :class="{ 'error': formErrors['location.district'] }"
              placeholder="Kampala"
              required
            />
            <span v-if="formErrors['location.district']" class="error-message">{{ formErrors['location.district'][0] }}</span>
          </div>

          <div class="form-group">
            <label for="router-lat">Latitude (Optional)</label>
            <input
              id="router-lat"
              v-model.number="routerForm.location.coordinates.lat"
              type="number"
              step="any"
              class="form-input"
              placeholder="0.3476"
              min="-90"
              max="90"
            />
          </div>

          <div class="form-group">
            <label for="router-lng">Longitude (Optional)</label>
            <input
              id="router-lng"
              v-model.number="routerForm.location.coordinates.lng"
              type="number"
              step="any"
              class="form-input"
              placeholder="32.5825"
              min="-180"
              max="180"
            />
          </div>
        </div>

        <!-- Connection Test Section -->
        <div class="connection-test-section">
          <button
            type="button"
            @click="testConnection"
            :disabled="isTestingConnection || !canTestConnection"
            class="btn btn-outline"
          >
            <i class="fas fa-plug" :class="{ 'fa-spin': isTestingConnection }"></i>
            Test Connection
          </button>
          
          <div v-if="connectionTestResult" class="test-result" :class="connectionTestResult.success ? 'success' : 'error'">
            <i :class="connectionTestResult.success ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
            <span>{{ connectionTestResult.message }}</span>
            <div v-if="connectionTestResult.success && connectionTestResult.data" class="test-details">
              <small>Identity: {{ connectionTestResult.data.identity }}</small>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button
            type="button"
            @click="closeRouterModal"
            class="btn btn-secondary"
            :disabled="isSubmitting"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="isSubmitting || !isFormValid"
          >
            <i v-if="isSubmitting" class="fas fa-spinner fa-spin"></i>
            {{ isEditing ? 'Update Router' : 'Add Router' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal 
      :show="showDeleteModal"
      @close="closeDeleteModal"
      title="Delete Router"
      variant="danger"
      size="sm"
    >
      <div class="delete-confirmation">
        <div class="warning-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <p>Are you sure you want to delete the router <strong>{{ routerToDelete?.name }}</strong>?</p>
        <p class="warning-text">This action cannot be undone.</p>
      </div>

      <template #footer>
        <button
          @click="closeDeleteModal"
          class="btn btn-secondary"
          :disabled="isDeleting"
        >
          Cancel
        </button>
        <button
          @click="confirmDelete"
          class="btn btn-danger"
          :disabled="isDeleting"
        >
          <i v-if="isDeleting" class="fas fa-spinner fa-spin"></i>
          Delete Router
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, nextTick } from 'vue';
import { useAppStore } from '@/store/modules/app';
import Modal from '@/components/common/Modal.vue';
import type { MikroTikDevice } from '@/types';
import axios from 'axios';
import { debounce } from 'lodash-es';

// Store references
const appStore = useAppStore();

// Component state
const isLoading = ref(false);
const routers = ref<MikroTikDevice[]>([]);
const summary = ref({
  total_routers: 0,
  online_routers: 0,
  offline_routers: 0
});

// Pagination
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0
});

// Filters
const searchQuery = ref('');
const statusFilter = ref('');
const sortBy = ref('name');
const sortOrder = ref('asc');

// Modal states
const showRouterModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const isSubmitting = ref(false);
const isDeleting = ref(false);

// Form state
const routerForm = reactive({
  name: '',
  ip_address: '',
  api_port: 8728,
  username: '',
  password: '',
  location: {
    region: '',
    district: '',
    coordinates: {
      lat: null as number | null,
      lng: null as number | null
    }
  }
});

const formErrors = ref<Record<string, string[]>>({});
const showPassword = ref(false);

// Connection testing
const isTestingConnection = ref(false);
const testingConnection = ref<string | null>(null);
const connectionTestResult = ref<any>(null);

// Router to delete
const routerToDelete = ref<MikroTikDevice | null>(null);
const editingRouter = ref<MikroTikDevice | null>(null);

// Computed properties
const canTestConnection = computed(() => {
  return routerForm.ip_address && 
         routerForm.api_port && 
         routerForm.username && 
         routerForm.password;
});

const isFormValid = computed(() => {
  return routerForm.name &&
         routerForm.ip_address &&
         routerForm.api_port &&
         routerForm.username &&
         routerForm.password &&
         routerForm.location.region &&
         routerForm.location.district;
});

// Debounced search
const debouncedSearch = debounce(() => {
  applyFilters();
}, 300);

// Status styling methods
const getStatusClass = (status: MikroTikDevice['status']): string => {
  return `status-${status}`;
};

const getStatusColor = (status: MikroTikDevice['status']): string => {
  switch (status) {
    case 'online':
      return '#10b981';
    case 'offline':
      return '#ef4444';
    case 'error':
      return '#f59e0b';
    default:
      return '#6b7280';
  }
};

const getRouterRowClass = (status: MikroTikDevice['status']): string => {
  return `router-row--${status}`;
};

// Formatting methods
const formatLastSeen = (lastSeen: string | null | undefined): string => {
  if (!lastSeen) return 'Never';
  
  const date = new Date(lastSeen);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60000);
  
  if (diffMinutes < 1) return 'Just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
};

const formatUptime = (seconds: number): string => {
  if (seconds === 0) return 'N/A';
  
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  
  if (days > 0) {
    return `${days}d ${hours}h`;
  } else if (hours > 0) {
    return `${hours}h ${minutes}m`;
  } else {
    return `${minutes}m`;
  }
};

// API methods
const fetchRouters = async (): Promise<void> => {
  try {
    isLoading.value = true;
    
    const params = new URLSearchParams({
      page: pagination.value.current_page.toString(),
      per_page: pagination.value.per_page.toString(),
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    });

    if (searchQuery.value) {
      params.append('search', searchQuery.value);
    }

    if (statusFilter.value) {
      params.append('status', statusFilter.value);
    }

    const response = await axios.get(`/api/v1/router-management?${params}`);
    
    routers.value = response.data.data;
    pagination.value = response.data.meta;
    summary.value = response.data.summary;

  } catch (error) {
    console.error('Failed to fetch routers:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to fetch routers'
    });
  } finally {
    isLoading.value = false;
  }
};

const testConnection = async (): Promise<void> => {
  try {
    isTestingConnection.value = true;
    connectionTestResult.value = null;

    const response = await axios.post('/api/v1/router-management/test-connection', {
      ip_address: routerForm.ip_address,
      api_port: routerForm.api_port,
      username: routerForm.username,
      password: routerForm.password,
    });

    connectionTestResult.value = response.data;

    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        title: 'Connection Test',
        message: 'Router connection successful!'
      });
    }

  } catch (error: any) {
    console.error('Connection test failed:', error);
    
    const errorMessage = error.response?.data?.error || 'Connection test failed';
    connectionTestResult.value = {
      success: false,
      message: errorMessage
    };

    appStore.addNotification({
      type: 'error',
      title: 'Connection Test Failed',
      message: errorMessage
    });
  } finally {
    isTestingConnection.value = false;
  }
};

const testRouterConnection = async (router: MikroTikDevice): Promise<void> => {
  try {
    testingConnection.value = router.id;

    const response = await axios.post('/api/v1/router-management/test-connection', {
      ip_address: router.ip_address,
      api_port: router.api_port,
      username: router.username,
      password: router.password,
    });

    if (response.data.success) {
      appStore.addNotification({
        type: 'success',
        title: 'Connection Test',
        message: `Router "${router.name}" is reachable!`
      });
    }

  } catch (error: any) {
    console.error('Router connection test failed:', error);
    
    const errorMessage = error.response?.data?.error || 'Connection test failed';
    appStore.addNotification({
      type: 'error',
      title: 'Connection Test Failed',
      message: `Router "${router.name}": ${errorMessage}`
    });
  } finally {
    testingConnection.value = null;
  }
};

const submitRouter = async (): Promise<void> => {
  try {
    isSubmitting.value = true;
    formErrors.value = {};

    const data = {
      ...routerForm,
      location: {
        ...routerForm.location,
        coordinates: routerForm.location.coordinates.lat && routerForm.location.coordinates.lng
          ? routerForm.location.coordinates
          : undefined
      }
    };

    let response;
    if (isEditing.value && editingRouter.value) {
      response = await axios.put(`/api/v1/router-management/${editingRouter.value.id}`, data);
    } else {
      response = await axios.post('/api/v1/router-management', data);
    }

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: response.data.message
    });

    closeRouterModal();
    await fetchRouters();

  } catch (error: any) {
    console.error('Failed to save router:', error);

    if (error.response?.status === 422) {
      formErrors.value = error.response.data.errors || {};
    }

    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: error.response?.data?.message || 'Failed to save router'
    });
  } finally {
    isSubmitting.value = false;
  }
};

const confirmDelete = async (): Promise<void> => {
  if (!routerToDelete.value) return;

  try {
    isDeleting.value = true;

    await axios.delete(`/api/v1/router-management/${routerToDelete.value.id}`);

    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Router deleted successfully'
    });

    closeDeleteModal();
    await fetchRouters();

  } catch (error: any) {
    console.error('Failed to delete router:', error);

    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: error.response?.data?.message || 'Failed to delete router'
    });
  } finally {
    isDeleting.value = false;
  }
};

// Event handlers
const refreshRouters = async (): Promise<void> => {
  await fetchRouters();
};

const applyFilters = async (): Promise<void> => {
  pagination.value.current_page = 1;
  await fetchRouters();
};

const changePage = async (page: number): Promise<void> => {
  pagination.value.current_page = page;
  await fetchRouters();
};

const openAddRouterModal = (): void => {
  console.log('openAddRouterModal called');
  console.log('Current showRouterModal value:', showRouterModal.value);
  isEditing.value = false;
  editingRouter.value = null;
  resetForm();
  showRouterModal.value = true;
  console.log('showRouterModal set to:', showRouterModal.value);
  
  // Force reactivity update
  nextTick(() => {
    console.log('After nextTick, showRouterModal:', showRouterModal.value);
  });
};

const editRouter = (router: MikroTikDevice): void => {
  isEditing.value = true;
  editingRouter.value = router;
  
  // Populate form with router data
  routerForm.name = router.name;
  routerForm.ip_address = router.ip_address;
  routerForm.api_port = router.api_port;
  routerForm.username = router.username;
  routerForm.password = ''; // Don't populate password for security
  routerForm.location.region = router.location.region;
  routerForm.location.district = router.location.district;
  routerForm.location.coordinates.lat = router.location.coordinates?.lat || null;
  routerForm.location.coordinates.lng = router.location.coordinates?.lng || null;

  showRouterModal.value = true;
};

const deleteRouter = (router: MikroTikDevice): void => {
  routerToDelete.value = router;
  showDeleteModal.value = true;
};

const closeRouterModal = (): void => {
  showRouterModal.value = false;
  resetForm();
  connectionTestResult.value = null;
  formErrors.value = {};
};

const closeDeleteModal = (): void => {
  showDeleteModal.value = false;
  routerToDelete.value = null;
};

const resetForm = (): void => {
  routerForm.name = '';
  routerForm.ip_address = '';
  routerForm.api_port = 8728;
  routerForm.username = '';
  routerForm.password = '';
  routerForm.location.region = '';
  routerForm.location.district = '';
  routerForm.location.coordinates.lat = null;
  routerForm.location.coordinates.lng = null;
  showPassword.value = false;
};

// Lifecycle hooks
onMounted(async () => {
  await fetchRouters();
});
</script>

<style scoped>
.router-management {
  padding: 1.5rem;
}

.management-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.management-header h2 {
  margin: 0;
  color: var(--text-primary);
}

.header-actions {
  display: flex;
  gap: 1rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-primary {
  background-color: var(--primary-color, #3b82f6);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-color-dark, #2563eb);
}

.btn-secondary {
  background-color: var(--background-secondary, #1f2937);
  color: var(--text-primary, #f9fafb);
  border: 1px solid var(--border-color, #374151);
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--background-tertiary, #374151);
}

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--border-color, #374151);
  color: var(--text-primary, #f9fafb);
}

.btn-outline:hover:not(:disabled) {
  background-color: var(--background-secondary, #1f2937);
}

.btn-danger {
  background-color: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: #dc2626;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.summary-card {
  background: var(--background-secondary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.summary-card.online {
  border-left: 4px solid #10b981;
}

.summary-card.offline {
  border-left: 4px solid #ef4444;
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--background-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: var(--text-secondary);
}

.card-content h3 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
}

.card-content p {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
}

/* Filters */
.filters-section {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 300px;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--background-primary);
  color: var(--text-primary);
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-controls {
  display: flex;
  gap: 0.5rem;
}

.filter-select {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--background-primary);
  color: var(--text-primary);
  min-width: 120px;
}

/* Table */
.routers-table-container {
  background: var(--background-secondary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  overflow: hidden;
  margin-bottom: 1rem;
}

.routers-table {
  width: 100%;
  border-collapse: collapse;
}

.routers-table th {
  background: var(--background-tertiary);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
}

.routers-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  color: var(--text-primary);
}

.router-row:hover {
  background: var(--background-tertiary);
}

.router-row--online {
  border-left: 3px solid #10b981;
}

.router-row--offline {
  border-left: 3px solid #ef4444;
}

.router-row--error {
  border-left: 3px solid #f59e0b;
}

.router-name strong {
  display: block;
  margin-bottom: 0.25rem;
}

.router-name small {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.ip-address {
  font-family: monospace;
  background: var(--background-tertiary);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
}

.location-info div {
  margin-bottom: 0.25rem;
}

.location-info small {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.status-online {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.status-offline {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.status-badge.status-error {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.last-seen,
.uptime {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.loading-row td,
.empty-row td {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--text-secondary);
}

.loading-spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 50%;
  border-top-color: var(--primary-color);
  animation: spin 1s linear infinite;
  margin-right: 0.5rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.empty-state i {
  font-size: 3rem;
  color: var(--text-secondary);
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 0;
}

.pagination-info {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-info {
  color: var(--text-primary);
  font-size: 0.875rem;
}

/* Modal Form */
.router-form {
  max-height: 70vh;
  overflow-y: auto;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 500;
  color: var(--text-primary);
  font-size: 0.875rem;
}

.form-input {
  padding: 0.75rem;
  border: 1px solid var(--border-color, #374151);
  border-radius: 0.375rem;
  background: var(--background-primary, #111827);
  color: var(--text-primary, #f9fafb);
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color, #3b82f6);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error {
  border-color: var(--error-color, #ef4444);
}

.error-message {
  color: var(--error-color, #ef4444);
  font-size: 0.75rem;
}

.password-input-container {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.25rem;
}

.password-toggle:hover {
  color: var(--text-primary);
}

.connection-test-section {
  margin-bottom: 2rem;
  padding: 1rem;
  background: var(--background-tertiary);
  border-radius: 0.375rem;
}

.test-result {
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.test-result.success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.test-result.error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.test-details {
  margin-top: 0.5rem;
}

.test-details small {
  opacity: 0.8;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

/* Delete Modal */
.delete-confirmation {
  text-align: center;
  padding: 1rem 0;
}

.warning-icon {
  font-size: 3rem;
  color: #f59e0b;
  margin-bottom: 1rem;
}

.warning-text {
  color: var(--text-secondary);
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

/* Animations */
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .router-management {
    padding: 1rem;
  }

  .management-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: space-between;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }

  .filters-section {
    flex-direction: column;
  }

  .search-box {
    min-width: auto;
  }

  .filter-controls {
    flex-wrap: wrap;
  }

  .routers-table-container {
    overflow-x: auto;
  }

  .routers-table {
    min-width: 800px;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .pagination-container {
    flex-direction: column;
    gap: 1rem;
  }

  .form-actions {
    flex-direction: column;
  }
}

.text-center {
  text-align: center;
}
</style>