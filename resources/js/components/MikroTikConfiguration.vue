<template>
  <div class="mikrotik-configuration">
    <div class="config-header">
      <h2>MikroTik Device Configuration</h2>
      <div class="header-actions">
        <button 
          @click="refreshDevices" 
          :disabled="isRefreshing"
          class="btn btn-primary"
        >
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': isRefreshing }"></i>
          Refresh
        </button>
        <div class="connection-status">
          <ConnectionStatus />
        </div>
      </div>
    </div>

    <!-- Device Selection -->
    <div class="device-selector">
      <label for="device-select">Select Device:</label>
      <select 
        id="device-select"
        v-model="selectedDeviceId" 
        @change="onDeviceChange"
        class="form-select"
      >
        <option value="">Select a device...</option>
        <option 
          v-for="device in onlineDevices" 
          :key="device.id" 
          :value="device.id"
        >
          {{ device.name }} ({{ device.ip_address }})
        </option>
      </select>
    </div>

    <!-- Configuration Tabs -->
    <div v-if="selectedDevice" class="config-tabs">
      <div class="tab-navigation">
        <button 
          v-for="tab in configTabs" 
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="['tab-button', { active: activeTab === tab.id }]"
        >
          <i :class="tab.icon"></i>
          {{ tab.label }}
        </button>
      </div>

      <div class="tab-content">
        <!-- Real-time Statistics Tab -->
        <div v-if="activeTab === 'statistics'" class="tab-panel">
          <div class="statistics-grid">
            <div class="stat-card">
              <div class="stat-header">
                <h4>System Resources</h4>
                <button @click="refreshStatistics" class="btn btn-sm btn-outline">
                  <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoadingStats }"></i>
                </button>
              </div>
              <div class="stat-content">
                <div class="stat-item">
                  <span class="label">CPU Usage:</span>
                  <div class="progress-bar">
                    <div 
                      class="progress-fill" 
                      :style="{ width: `${statistics.cpu_usage || 0}%` }"
                    ></div>
                    <span class="progress-text">{{ statistics.cpu_usage || 0 }}%</span>
                  </div>
                </div>
                <div class="stat-item">
                  <span class="label">Memory Usage:</span>
                  <div class="progress-bar">
                    <div 
                      class="progress-fill" 
                      :style="{ width: `${statistics.memory_usage || 0}%` }"
                    ></div>
                    <span class="progress-text">{{ statistics.memory_usage || 0 }}%</span>
                  </div>
                </div>
                <div class="stat-item">
                  <span class="label">Uptime:</span>
                  <span class="value">{{ formatUptime(statistics.uptime) }}</span>
                </div>
                <div class="stat-item">
                  <span class="label">Version:</span>
                  <span class="value">{{ statistics.version || 'Unknown' }}</span>
                </div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-header">
                <h4>Network Interfaces</h4>
              </div>
              <div class="stat-content">
                <div 
                  v-for="interfaceData in statistics.interfaces" 
                  :key="interfaceData.name"
                  class="interface-item"
                >
                  <div class="interface-header">
                    <span class="interface-name">{{ interfaceData.name }}</span>
                    <span 
                      class="interface-status" 
                      :class="interfaceData.running ? 'status-online' : 'status-offline'"
                    >
                      {{ interfaceData.running ? 'UP' : 'DOWN' }}
                    </span>
                  </div>
                  <div class="interface-details">
                    <div class="detail-row">
                      <span>RX: {{ formatBytes(interfaceData.rx_bytes) }}</span>
                      <span>TX: {{ formatBytes(interfaceData.tx_bytes) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-header">
                <h4>Active Connections</h4>
              </div>
              <div class="stat-content">
                <div class="connection-stats">
                  <div class="connection-item">
                    <span class="label">Total Users:</span>
                    <span class="value">{{ statistics.active_users || 0 }}</span>
                  </div>
                  <div class="connection-item">
                    <span class="label">Active Sessions:</span>
                    <span class="value">{{ statistics.active_sessions || 0 }}</span>
                  </div>
                  <div class="connection-item">
                    <span class="label">Bandwidth Usage:</span>
                    <span class="value">{{ formatBandwidth(statistics.bandwidth_usage) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Interface Configuration Tab -->
        <div v-if="activeTab === 'interfaces'" class="tab-panel">
          <div class="interface-config">
            <div class="config-header">
              <h4>Network Interfaces</h4>
              <button @click="refreshInterfaces" class="btn btn-sm btn-primary">
                <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoadingInterfaces }"></i>
                Refresh
              </button>
            </div>
            
            <div class="interfaces-table">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>MAC Address</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="interfaceData in interfaces" 
                    :key="interfaceData.id"
                    :class="{ 'interface-disabled': !interfaceData.disabled }"
                  >
                    <td>{{ interfaceData.name }}</td>
                    <td>{{ interfaceData.type }}</td>
                    <td>
                      <span 
                        class="status-badge" 
                        :class="interfaceData.running ? 'status-online' : 'status-offline'"
                      >
                        {{ interfaceData.running ? 'UP' : 'DOWN' }}
                      </span>
                    </td>
                    <td>{{ interfaceData.address || 'N/A' }}</td>
                    <td class="mac-address">{{ interfaceData.mac_address || 'N/A' }}</td>
                    <td>
                      <div class="action-buttons">
                        <button 
                          @click="editInterface(interfaceData)"
                          class="btn btn-xs btn-outline"
                          title="Edit Interface"
                        >
                          <i class="fas fa-edit"></i>
                        </button>
                        <button 
                          @click="toggleInterface(interfaceData)"
                          :class="['btn', 'btn-xs', interfaceData.disabled ? 'btn-success' : 'btn-warning']"
                          :title="interfaceData.disabled ? 'Enable Interface' : 'Disable Interface'"
                        >
                          <i :class="interfaceData.disabled ? 'fas fa-play' : 'fas fa-pause'"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- User Management Tab -->
        <div v-if="activeTab === 'users'" class="tab-panel">
          <div class="user-management">
            <div class="config-header">
              <h4>User Management</h4>
              <div class="header-actions">
                <button @click="showAddUserModal = true" class="btn btn-sm btn-primary">
                  <i class="fas fa-plus"></i>
                  Add User
                </button>
                <button @click="refreshUsers" class="btn btn-sm btn-outline">
                  <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoadingUsers }"></i>
                  Refresh
                </button>
              </div>
            </div>

            <div class="users-table">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Profile</th>
                    <th>Status</th>
                    <th>Voucher</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in users" :key="user.id">
                    <td>{{ user.username }}</td>
                    <td>{{ user.profile }}</td>
                    <td>
                      <span 
                        class="status-badge" 
                        :class="user.is_active ? 'status-online' : 'status-offline'"
                      >
                        {{ user.is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </td>
                    <td>
                      <span v-if="user.voucher_code" class="voucher-code">
                        {{ user.voucher_code }}
                      </span>
                      <span v-else class="text-muted">No voucher</span>
                    </td>
                    <td>{{ formatDate(user.created_at) }}</td>
                    <td>
                      <div class="action-buttons">
                        <button 
                          @click="editUser(user)"
                          class="btn btn-xs btn-outline"
                          title="Edit User"
                        >
                          <i class="fas fa-edit"></i>
                        </button>
                        <button 
                          @click="toggleUser(user)"
                          :class="['btn', 'btn-xs', user.is_active ? 'btn-warning' : 'btn-success']"
                          :title="user.is_active ? 'Disable User' : 'Enable User'"
                        >
                          <i :class="user.is_active ? 'fas fa-pause' : 'fas fa-play'"></i>
                        </button>
                        <button 
                          @click="deleteUser(user)"
                          class="btn btn-xs btn-danger"
                          title="Delete User"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- System Logs Tab -->
        <div v-if="activeTab === 'logs'" class="tab-panel">
          <div class="system-logs">
            <div class="config-header">
              <h4>System Logs</h4>
              <div class="header-actions">
                <select v-model="logLevel" class="form-select">
                  <option value="">All Levels</option>
                  <option value="info">Info</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="critical">Critical</option>
                </select>
                <button @click="refreshLogs" class="btn btn-sm btn-outline">
                  <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoadingLogs }"></i>
                  Refresh
                </button>
              </div>
            </div>

            <div class="logs-container">
              <div 
                v-for="log in filteredLogs" 
                :key="log.id"
                class="log-entry"
                :class="getLogLevelClass(log.level)"
              >
                <div class="log-timestamp">
                  {{ formatDate(log.timestamp) }}
                </div>
                <div class="log-level">
                  <span class="level-badge" :class="getLogLevelClass(log.level)">
                    {{ log.level.toUpperCase() }}
                  </span>
                </div>
                <div class="log-message">
                  {{ log.message }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Backup & Restore Tab -->
        <div v-if="activeTab === 'backup'" class="tab-panel">
          <div class="backup-restore">
            <div class="backup-section">
              <h4>Configuration Backup</h4>
              <p>Create a backup of the current router configuration.</p>
              <button 
                @click="createBackup" 
                :disabled="isCreatingBackup"
                class="btn btn-primary"
              >
                <i class="fas fa-download" :class="{ 'fa-spin': isCreatingBackup }"></i>
                Create Backup
              </button>
            </div>

            <div class="restore-section">
              <h4>Configuration Restore</h4>
              <p>Restore router configuration from a previous backup.</p>
              
              <div class="backup-list">
                <h5>Available Backups</h5>
                <div 
                  v-for="backup in backups" 
                  :key="backup.id"
                  class="backup-item"
                >
                  <div class="backup-info">
                    <div class="backup-name">{{ backup.name }}</div>
                    <div class="backup-date">{{ formatDate(backup.created_at) }}</div>
                  </div>
                  <div class="backup-actions">
                    <button 
                      @click="downloadBackup(backup)"
                      class="btn btn-sm btn-outline"
                      title="Download Backup"
                    >
                      <i class="fas fa-download"></i>
                    </button>
                    <button 
                      @click="restoreBackup(backup)"
                      class="btn btn-sm btn-warning"
                      title="Restore Backup"
                    >
                      <i class="fas fa-upload"></i>
                      Restore
                    </button>
                    <button 
                      @click="deleteBackup(backup)"
                      class="btn btn-sm btn-danger"
                      title="Delete Backup"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add User Modal -->
    <Modal 
      :show="showAddUserModal"
      @close="closeAddUserModal"
      title="Add New User"
      size="medium"
    >
      <form @submit.prevent="addUser" class="user-form">
        <div class="form-group">
          <label for="username">Username:</label>
          <input 
            id="username"
            v-model="newUser.username" 
            type="text" 
            class="form-input"
            required
          />
        </div>
        
        <div class="form-group">
          <label for="password">Password:</label>
          <input 
            id="password"
            v-model="newUser.password" 
            type="password" 
            class="form-input"
            required
          />
        </div>
        
        <div class="form-group">
          <label for="profile">Profile:</label>
          <select id="profile" v-model="newUser.profile" class="form-select" required>
            <option value="">Select Profile</option>
            <option v-for="profile in userProfiles" :key="profile" :value="profile">
              {{ profile }}
            </option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="voucher">Link to Voucher (Optional):</label>
          <select id="voucher" v-model="newUser.voucher_id" class="form-select">
            <option value="">No voucher</option>
            <option v-for="voucher in availableVouchers" :key="voucher.id" :value="voucher.id">
              {{ voucher.code }} - {{ voucher.duration_hours }}h
            </option>
          </select>
        </div>
        
        <div class="form-actions">
          <button type="button" @click="closeAddUserModal" class="btn btn-outline">
            Cancel
          </button>
          <button type="submit" :disabled="isAddingUser" class="btn btn-primary">
            <i class="fas fa-plus" :class="{ 'fa-spin': isAddingUser }"></i>
            Add User
          </button>
        </div>
      </form>
    </Modal>

    <!-- Interface Edit Modal -->
    <Modal 
      :show="showEditInterfaceModal"
      @close="closeEditInterfaceModal"
      title="Edit Interface"
      size="medium"
    >
      <form @submit.prevent="updateInterface" class="interface-form">
        <div class="form-group">
          <label for="interface-name">Name:</label>
          <input 
            id="interface-name"
            v-model="editingInterface.name" 
            type="text" 
            class="form-input"
            readonly
          />
        </div>
        
        <div class="form-group">
          <label for="interface-address">IP Address:</label>
          <input 
            id="interface-address"
            v-model="editingInterface.address" 
            type="text" 
            class="form-input"
            placeholder="192.168.1.1/24"
          />
        </div>
        
        <div class="form-group">
          <label>
            <input 
              v-model="editingInterface.disabled" 
              type="checkbox"
            />
            Disabled
          </label>
        </div>
        
        <div class="form-actions">
          <button type="button" @click="closeEditInterfaceModal" class="btn btn-outline">
            Cancel
          </button>
          <button type="submit" :disabled="isUpdatingInterface" class="btn btn-primary">
            <i class="fas fa-save" :class="{ 'fa-spin': isUpdatingInterface }"></i>
            Update Interface
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import ConnectionStatus from '@/components/common/ConnectionStatus.vue';
import Modal from '@/components/common/Modal.vue';
import type { MikroTikDevice } from '@/types';
import axios from 'axios';

// Store references
const realtimeStore = useRealtimeStore();
const appStore = useAppStore();
const route = useRoute();

// Component state
const isRefreshing = ref(false);
const selectedDeviceId = ref('');
const selectedDevice = ref<MikroTikDevice | null>(null);
const activeTab = ref('statistics');

// Statistics state
const isLoadingStats = ref(false);
const statistics = ref<any>({
  cpu_usage: 0,
  memory_usage: 0,
  uptime: 0,
  version: '',
  interfaces: [],
  active_users: 0,
  active_sessions: 0,
  bandwidth_usage: 0
});

// Interfaces state
const isLoadingInterfaces = ref(false);
const interfaces = ref<any[]>([]);
const showEditInterfaceModal = ref(false);
const editingInterface = ref<any>({});
const isUpdatingInterface = ref(false);

// Users state
const isLoadingUsers = ref(false);
const users = ref<any[]>([]);
const showAddUserModal = ref(false);
const newUser = ref({
  username: '',
  password: '',
  profile: '',
  voucher_id: ''
});
const isAddingUser = ref(false);
const userProfiles = ref(['default', 'hotspot', 'pppoe', 'wireless']);
const availableVouchers = ref<any[]>([]);

// Logs state
const isLoadingLogs = ref(false);
const logs = ref<any[]>([]);
const logLevel = ref('');

// Backup state
const isCreatingBackup = ref(false);
const backups = ref<any[]>([]);

// Polling intervals
let statisticsInterval: NodeJS.Timeout | null = null;

// Configuration tabs
const configTabs = [
  { id: 'statistics', label: 'Statistics', icon: 'fas fa-chart-line' },
  { id: 'interfaces', label: 'Interfaces', icon: 'fas fa-network-wired' },
  { id: 'users', label: 'Users', icon: 'fas fa-users' },
  { id: 'logs', label: 'System Logs', icon: 'fas fa-file-alt' },
  { id: 'backup', label: 'Backup & Restore', icon: 'fas fa-save' }
];

// Computed properties
const devices = computed(() => realtimeStore.mikrotikDevices);
const onlineDevices = computed(() => devices.value.filter(d => d.status === 'online'));

const filteredLogs = computed(() => {
  if (!logLevel.value) return logs.value;
  return logs.value.filter(log => log.level === logLevel.value);
});

// Watch for device selection changes
watch(selectedDeviceId, (newDeviceId) => {
  if (newDeviceId) {
    selectedDevice.value = devices.value.find(d => d.id === newDeviceId) || null;
    if (selectedDevice.value) {
      loadDeviceData();
    }
  } else {
    selectedDevice.value = null;
    stopPolling();
  }
});

// Methods
const refreshDevices = async (): Promise<void> => {
  try {
    isRefreshing.value = true;
    const response = await axios.get('/api/v1/router');
    realtimeStore.updateMikroTikDevices(response.data.data);
  } catch (error) {
    console.error('Failed to refresh devices:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to refresh devices'
    });
  } finally {
    isRefreshing.value = false;
  }
};

const onDeviceChange = (): void => {
  // Device change is handled by the watcher
};

const loadDeviceData = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  await Promise.all([
    refreshStatistics(),
    refreshInterfaces(),
    refreshUsers(),
    refreshLogs(),
    loadBackups()
  ]);
  
  startPolling();
};

const refreshStatistics = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isLoadingStats.value = true;
    const response = await axios.get(`/api/v1/router/${selectedDevice.value.id}/statistics`);
    statistics.value = response.data.data;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  } finally {
    isLoadingStats.value = false;
  }
};

const refreshInterfaces = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isLoadingInterfaces.value = true;
    const response = await axios.get(`/api/v1/router/${selectedDevice.value.id}/interfaces`);
    interfaces.value = response.data.data;
  } catch (error) {
    console.error('Failed to load interfaces:', error);
  } finally {
    isLoadingInterfaces.value = false;
  }
};

const refreshUsers = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isLoadingUsers.value = true;
    const response = await axios.get(`/api/v1/router/${selectedDevice.value.id}/users`);
    users.value = response.data.data;
    
    // Load available vouchers for user creation
    const vouchersResponse = await axios.get('/api/v1/vouchers?status=unused');
    availableVouchers.value = vouchersResponse.data.data;
  } catch (error) {
    console.error('Failed to load users:', error);
  } finally {
    isLoadingUsers.value = false;
  }
};

const refreshLogs = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isLoadingLogs.value = true;
    const response = await axios.get(`/api/v1/router/${selectedDevice.value.id}/logs`);
    logs.value = response.data.data;
  } catch (error) {
    console.error('Failed to load logs:', error);
  } finally {
    isLoadingLogs.value = false;
  }
};

const loadBackups = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    const response = await axios.get(`/api/v1/router/${selectedDevice.value.id}/backups`);
    backups.value = response.data.data;
  } catch (error) {
    console.error('Failed to load backups:', error);
  }
};

// Interface management
const editInterface = (interfaceData: any): void => {
  editingInterface.value = { ...interfaceData };
  showEditInterfaceModal.value = true;
};

const updateInterface = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isUpdatingInterface.value = true;
    await axios.put(
      `/api/v1/router/${selectedDevice.value.id}/interfaces/${editingInterface.value.id}`,
      editingInterface.value
    );
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Interface updated successfully'
    });
    
    closeEditInterfaceModal();
    await refreshInterfaces();
  } catch (error) {
    console.error('Failed to update interface:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to update interface'
    });
  } finally {
    isUpdatingInterface.value = false;
  }
};

const toggleInterface = async (interfaceData: any): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    await axios.put(
      `/api/v1/router/${selectedDevice.value.id}/interfaces/${interfaceData.id}/toggle`
    );
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: `Interface ${interfaceData.disabled ? 'enabled' : 'disabled'} successfully`
    });
    
    await refreshInterfaces();
  } catch (error) {
    console.error('Failed to toggle interface:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to toggle interface'
    });
  }
};

const closeEditInterfaceModal = (): void => {
  showEditInterfaceModal.value = false;
  editingInterface.value = {};
};

// User management
const addUser = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isAddingUser.value = true;
    await axios.post(`/api/v1/router/${selectedDevice.value.id}/users`, newUser.value);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'User added successfully'
    });
    
    closeAddUserModal();
    await refreshUsers();
  } catch (error) {
    console.error('Failed to add user:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to add user'
    });
  } finally {
    isAddingUser.value = false;
  }
};

const editUser = (user: any): void => {
  // Implementation for editing user
  console.log('Edit user:', user);
};

const toggleUser = async (user: any): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    await axios.put(`/api/v1/router/${selectedDevice.value.id}/users/${user.id}/toggle`);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: `User ${user.is_active ? 'disabled' : 'enabled'} successfully`
    });
    
    await refreshUsers();
  } catch (error) {
    console.error('Failed to toggle user:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to toggle user'
    });
  }
};

const deleteUser = async (user: any): Promise<void> => {
  if (!selectedDevice.value) return;
  
  if (!confirm(`Are you sure you want to delete user "${user.username}"?`)) {
    return;
  }
  
  try {
    await axios.delete(`/api/v1/router/${selectedDevice.value.id}/users/${user.id}`);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'User deleted successfully'
    });
    
    await refreshUsers();
  } catch (error) {
    console.error('Failed to delete user:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to delete user'
    });
  }
};

const closeAddUserModal = (): void => {
  showAddUserModal.value = false;
  newUser.value = {
    username: '',
    password: '',
    profile: '',
    voucher_id: ''
  };
};

// Backup management
const createBackup = async (): Promise<void> => {
  if (!selectedDevice.value) return;
  
  try {
    isCreatingBackup.value = true;
    await axios.post(`/api/v1/router/${selectedDevice.value.id}/backup`);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Backup created successfully'
    });
    
    await loadBackups();
  } catch (error) {
    console.error('Failed to create backup:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to create backup'
    });
  } finally {
    isCreatingBackup.value = false;
  }
};

const downloadBackup = async (backup: any): Promise<void> => {
  try {
    const response = await axios.get(
      `/api/v1/router/${selectedDevice.value?.id}/backups/${backup.id}/download`,
      { responseType: 'blob' }
    );
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${backup.name}.backup`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Failed to download backup:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to download backup'
    });
  }
};

const restoreBackup = async (backup: any): Promise<void> => {
  if (!selectedDevice.value) return;
  
  if (!confirm(`Are you sure you want to restore backup "${backup.name}"? This will overwrite the current configuration.`)) {
    return;
  }
  
  try {
    await axios.post(`/api/v1/router/${selectedDevice.value.id}/backups/${backup.id}/restore`);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Backup restored successfully'
    });
    
    // Refresh all data after restore
    await loadDeviceData();
  } catch (error) {
    console.error('Failed to restore backup:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to restore backup'
    });
  }
};

const deleteBackup = async (backup: any): Promise<void> => {
  if (!confirm(`Are you sure you want to delete backup "${backup.name}"?`)) {
    return;
  }
  
  try {
    await axios.delete(`/api/v1/router/${selectedDevice.value?.id}/backups/${backup.id}`);
    
    appStore.addNotification({
      type: 'success',
      title: 'Success',
      message: 'Backup deleted successfully'
    });
    
    await loadBackups();
  } catch (error) {
    console.error('Failed to delete backup:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to delete backup'
    });
  }
};

// Utility methods
const formatUptime = (seconds: number): string => {
  if (!seconds) return 'N/A';
  
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  
  if (days > 0) {
    return `${days}d ${hours}h ${minutes}m`;
  } else if (hours > 0) {
    return `${hours}h ${minutes}m`;
  } else {
    return `${minutes}m`;
  }
};

const formatBytes = (bytes: number): string => {
  if (!bytes) return '0 B';
  
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatBandwidth = (bps: number): string => {
  if (!bps) return '0 bps';
  
  const k = 1000;
  const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
  const i = Math.floor(Math.log(bps) / Math.log(k));
  
  return parseFloat((bps / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString();
};

const getLogLevelClass = (level: string): string => {
  switch (level) {
    case 'error':
    case 'critical':
      return 'log-error';
    case 'warning':
      return 'log-warning';
    case 'info':
      return 'log-info';
    default:
      return 'log-default';
  }
};

// Polling
const startPolling = (): void => {
  // Poll statistics every 10 seconds
  statisticsInterval = setInterval(async () => {
    if (activeTab.value === 'statistics') {
      await refreshStatistics();
    }
  }, 10000);
};

const stopPolling = (): void => {
  if (statisticsInterval) {
    clearInterval(statisticsInterval);
    statisticsInterval = null;
  }
};

// Lifecycle hooks
onMounted(async () => {
  await refreshDevices();
  
  // Check if device ID is provided in query parameters
  const deviceId = route.query.deviceId as string;
  if (deviceId) {
    selectedDeviceId.value = deviceId;
  }
});

onUnmounted(() => {
  stopPolling();
});
</script>

<style scoped>
.mikrotik-configuration {
  padding: 1.5rem;
}

.config-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.config-header h2 {
  margin: 0;
  color: var(--text-primary);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.device-selector {
  margin-bottom: 2rem;
}

.device-selector label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-select {
  width: 100%;
  max-width: 400px;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background-color: var(--background-primary);
  color: var(--text-primary);
}

.config-tabs {
  background: var(--background-secondary);
  border-radius: 0.5rem;
  overflow: hidden;
}

.tab-navigation {
  display: flex;
  background: var(--background-primary);
  border-bottom: 1px solid var(--border-color);
}

.tab-button {
  flex: 1;
  padding: 1rem;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.tab-button:hover {
  background: var(--background-secondary);
  color: var(--text-primary);
}

.tab-button.active {
  background: var(--primary-color);
  color: white;
}

.tab-content {
  padding: 2rem;
}

.tab-panel {
  min-height: 400px;
}

/* Statistics Styles */
.statistics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: var(--background-primary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.stat-header h4 {
  margin: 0;
  color: var(--text-primary);
}

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-item .label {
  font-weight: 500;
  color: var(--text-secondary);
}

.stat-item .value {
  color: var(--text-primary);
  font-weight: 600;
}

.progress-bar {
  position: relative;
  width: 120px;
  height: 20px;
  background: var(--background-secondary);
  border-radius: 10px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.3s ease;
}

.progress-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-primary);
}

.interface-item {
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  margin-bottom: 0.5rem;
}

.interface-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.interface-name {
  font-weight: 600;
  color: var(--text-primary);
}

.interface-status {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.interface-details {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.detail-row {
  display: flex;
  justify-content: space-between;
}

.connection-stats {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.connection-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  background: var(--background-secondary);
  border-radius: 0.375rem;
}

/* Table Styles */
.data-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--background-primary);
  border-radius: 0.5rem;
  overflow: hidden;
}

.data-table th,
.data-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.data-table th {
  background: var(--background-secondary);
  font-weight: 600;
  color: var(--text-primary);
}

.data-table td {
  color: var(--text-primary);
}

.mac-address {
  font-family: monospace;
  font-size: 0.875rem;
}

.status-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-online {
  background-color: rgba(34, 197, 94, 0.1);
  color: rgb(34, 197, 94);
}

.status-offline {
  background-color: rgba(239, 68, 68, 0.1);
  color: rgb(239, 68, 68);
}

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

/* Button Styles */
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
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-color-dark);
}

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-primary);
}

.btn-outline:hover {
  background-color: var(--background-secondary);
}

.btn-success {
  background-color: rgb(34, 197, 94);
  color: white;
}

.btn-warning {
  background-color: rgb(249, 115, 22);
  color: white;
}

.btn-danger {
  background-color: rgb(239, 68, 68);
  color: white;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.btn-xs {
  padding: 0.125rem 0.375rem;
  font-size: 0.75rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Form Styles */
.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background-color: var(--background-primary);
  color: var(--text-primary);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

/* Logs Styles */
.logs-container {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
}

.log-entry {
  display: flex;
  gap: 1rem;
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.log-entry:last-child {
  border-bottom: none;
}

.log-timestamp {
  flex-shrink: 0;
  width: 150px;
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-family: monospace;
}

.log-level {
  flex-shrink: 0;
  width: 80px;
}

.level-badge {
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.log-error .level-badge {
  background-color: rgba(239, 68, 68, 0.1);
  color: rgb(239, 68, 68);
}

.log-warning .level-badge {
  background-color: rgba(249, 115, 22, 0.1);
  color: rgb(249, 115, 22);
}

.log-info .level-badge {
  background-color: rgba(59, 130, 246, 0.1);
  color: rgb(59, 130, 246);
}

.log-default .level-badge {
  background-color: rgba(107, 114, 128, 0.1);
  color: rgb(107, 114, 128);
}

.log-message {
  flex: 1;
  color: var(--text-primary);
}

/* Backup Styles */
.backup-restore {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.backup-section,
.restore-section {
  padding: 1.5rem;
  background: var(--background-primary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
}

.backup-section h4,
.restore-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.backup-section p,
.restore-section p {
  margin-bottom: 1.5rem;
  color: var(--text-secondary);
}

.backup-list h5 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.backup-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--background-secondary);
  border-radius: 0.375rem;
  margin-bottom: 0.5rem;
}

.backup-info {
  flex: 1;
}

.backup-name {
  font-weight: 600;
  color: var(--text-primary);
}

.backup-date {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.backup-actions {
  display: flex;
  gap: 0.5rem;
}

.voucher-code {
  font-family: monospace;
  background: var(--background-secondary);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
}

.text-muted {
  color: var(--text-secondary);
  font-style: italic;
}

/* Responsive Design */
@media (max-width: 768px) {
  .mikrotik-configuration {
    padding: 1rem;
  }

  .config-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: space-between;
  }

  .tab-navigation {
    flex-wrap: wrap;
  }

  .tab-button {
    flex: 1 1 auto;
    min-width: 120px;
  }

  .statistics-grid {
    grid-template-columns: 1fr;
  }

  .backup-restore {
    grid-template-columns: 1fr;
  }

  .data-table {
    font-size: 0.875rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.5rem;
  }

  .backup-item {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .backup-actions {
    justify-content: center;
  }
}
</style>