<template>
  <div class="mikrotik-monitor">
    <div class="monitor-header">
      <h2>MikroTik Device Monitoring</h2>
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

    <div class="devices-grid">
      <div 
        v-for="device in devices" 
        :key="device.id"
        class="device-card"
        :class="getDeviceCardClass(device.status)"
      >
        <div class="device-header">
          <div class="device-info">
            <h3>{{ device.name }}</h3>
            <p class="device-ip">{{ device.ip_address }}:{{ device.api_port }}</p>
          </div>
          <div class="device-status">
            <div 
              class="status-indicator"
              :class="getStatusIndicatorClass(device.status)"
              :style="{ backgroundColor: getStatusIndicatorColor(device.status) }"
              :title="getAccessibleStatusText(device.status)"
            ></div>
            <span class="status-text">{{ device.status.toUpperCase() }}</span>
          </div>
        </div>

        <div class="device-details">
          <div class="detail-row">
            <span class="label">Location:</span>
            <span class="value">{{ device.location.region }}, {{ device.location.district }}</span>
          </div>
          
          <div class="detail-row" v-if="device.location.coordinates">
            <span class="label">Coordinates:</span>
            <span class="value">
              {{ device.location.coordinates?.lat.toFixed(6) }}, 
              {{ device.location.coordinates?.lng.toFixed(6) }}
            </span>
          </div>

          <div class="detail-row">
            <span class="label">Uptime:</span>
            <span class="value">{{ formatUptime(device.uptime_seconds) }}</span>
          </div>

          <div class="detail-row">
            <span class="label">Last Seen:</span>
            <span class="value">{{ formatLastSeen(device.last_seen) }}</span>
          </div>
        </div>

        <div class="device-actions">
          <button 
            @click="viewDeviceDetails(device)"
            class="btn btn-sm btn-outline"
          >
            <i class="fas fa-info-circle"></i>
            Details
          </button>
          <button 
            @click="configureDevice(device)"
            class="btn btn-sm btn-primary"
          >
            <i class="fas fa-cog"></i>
            Configure
          </button>
          <button 
            @click="viewDeviceLogs(device)"
            class="btn btn-sm btn-outline"
          >
            <i class="fas fa-history"></i>
            Logs
          </button>
        </div>
      </div>
    </div>

    <!-- Device Details Modal -->
    <Modal 
      v-if="selectedDevice"
      :show="showDetailsModal"
      @close="closeDetailsModal"
      title="Device Details"
      size="large"
    >
      <div class="device-details-modal">
        <div class="details-section">
          <h4>Device Information</h4>
          <div class="details-grid">
            <div class="detail-item">
              <label>Name:</label>
              <span>{{ selectedDevice.name }}</span>
            </div>
            <div class="detail-item">
              <label>IP Address:</label>
              <span>{{ selectedDevice.ip_address }}</span>
            </div>
            <div class="detail-item">
              <label>API Port:</label>
              <span>{{ selectedDevice.api_port }}</span>
            </div>
            <div class="detail-item">
              <label>Username:</label>
              <span>{{ selectedDevice.username }}</span>
            </div>
            <div class="detail-item">
              <label>Status:</label>
              <span class="status-badge" :class="getStatusIndicatorClass(selectedDevice.status)">
                {{ selectedDevice.status.toUpperCase() }}
              </span>
            </div>
          </div>
        </div>

        <div class="details-section">
          <h4>Location Information</h4>
          <div class="details-grid">
            <div class="detail-item">
              <label>Region:</label>
              <span>{{ selectedDevice.location.region }}</span>
            </div>
            <div class="detail-item">
              <label>District:</label>
              <span>{{ selectedDevice.location.district }}</span>
            </div>
            <div class="detail-item" v-if="selectedDevice.location.coordinates">
              <label>Coordinates:</label>
              <span>
                {{ selectedDevice.location.coordinates.lat.toFixed(6) }}, 
                {{ selectedDevice.location.coordinates.lng.toFixed(6) }}
              </span>
            </div>
          </div>
        </div>

        <div class="details-section">
          <h4>Status Information</h4>
          <div class="details-grid">
            <div class="detail-item">
              <label>Current Status:</label>
              <span class="status-badge" :class="getStatusIndicatorClass(selectedDevice.status)">
                {{ selectedDevice.status.toUpperCase() }}
              </span>
            </div>
            <div class="detail-item">
              <label>Uptime:</label>
              <span>{{ formatUptime(selectedDevice.uptime_seconds) }}</span>
            </div>
            <div class="detail-item">
              <label>Last Seen:</label>
              <span>{{ formatLastSeen(selectedDevice.last_seen) }}</span>
            </div>
            <div class="detail-item">
              <label>Created:</label>
              <span>{{ formatDate(selectedDevice.created_at) }}</span>
            </div>
            <div class="detail-item">
              <label>Updated:</label>
              <span>{{ formatDate(selectedDevice.updated_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Device Logs Modal -->
    <Modal 
      v-if="selectedDevice"
      :show="showLogsModal"
      @close="closeLogsModal"
      title="Device Status Logs"
      size="large"
    >
      <div class="device-logs-modal">
        <div class="logs-header">
          <h4>Status Change History for {{ selectedDevice.name }}</h4>
          <div class="logs-filters">
            <select v-model="logFilter" class="form-select">
              <option value="all">All Status Changes</option>
              <option value="online">Online Events</option>
              <option value="offline">Offline Events</option>
              <option value="error">Error Events</option>
            </select>
          </div>
        </div>

        <div class="logs-list">
          <div 
            v-for="log in filteredLogs" 
            :key="log.id"
            class="log-entry"
            :class="getLogEntryClass(log.newStatus)"
          >
            <div class="log-timestamp">
              {{ formatDate(log.timestamp) }}
            </div>
            <div class="log-content">
              <div class="log-status-change">
                <span class="status-from" :class="getStatusIndicatorClass(log.previousStatus)">
                  {{ log.previousStatus.toUpperCase() }}
                </span>
                <i class="fas fa-arrow-right"></i>
                <span class="status-to" :class="getStatusIndicatorClass(log.newStatus)">
                  {{ log.newStatus.toUpperCase() }}
                </span>
              </div>
              <div class="log-details">
                Device: {{ log.deviceName }} ({{ log.ipAddress }})
              </div>
            </div>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import { useRouter } from 'vue-router';
import ConnectionStatus from '@/components/common/ConnectionStatus.vue';
import Modal from '@/components/common/Modal.vue';
import type { MikroTikDevice } from '@/types';
import axios from 'axios';

// Store references
const realtimeStore = useRealtimeStore();
const appStore = useAppStore();
const router = useRouter();

// Component state
const isRefreshing = ref(false);
const selectedDevice = ref<MikroTikDevice | null>(null);
const showDetailsModal = ref(false);
const showLogsModal = ref(false);
const logFilter = ref('all');
const deviceLogs = ref<Array<{
  id: string;
  deviceId: string;
  deviceName: string;
  previousStatus: MikroTikDevice['status'];
  newStatus: MikroTikDevice['status'];
  timestamp: string;
  ipAddress: string;
}>>([]);

// Polling interval reference
let pollingInterval: NodeJS.Timeout | null = null;

// Computed properties
const devices = computed(() => realtimeStore.mikrotikDevices);

const filteredLogs = computed(() => {
  if (logFilter.value === 'all') {
    return deviceLogs.value;
  }
  return deviceLogs.value.filter(log => 
    log.newStatus === logFilter.value || log.previousStatus === logFilter.value
  );
});

// Status indicator methods
const getStatusIndicatorClass = (status: MikroTikDevice['status']): string => {
  switch (status) {
    case 'online':
      return 'status-online';
    case 'offline':
      return 'status-offline';
    case 'error':
      return 'status-error';
    default:
      return 'status-unknown';
  }
};

const getStatusIndicatorColor = (status: MikroTikDevice['status']): string => {
  switch (status) {
    case 'online':
      return 'green';
    case 'offline':
      return 'red';
    case 'error':
      return 'orange';
    default:
      return 'gray';
  }
};

const getAccessibleStatusText = (status: MikroTikDevice['status']): string => {
  switch (status) {
    case 'online':
      return 'Device is online and operational';
    case 'offline':
      return 'Device is offline or unreachable';
    case 'error':
      return 'Device has encountered an error';
    default:
      return 'Device status is unknown';
  }
};

const getDeviceCardClass = (status: MikroTikDevice['status']): string => {
  return `device-card--${status}`;
};

const getLogEntryClass = (status: MikroTikDevice['status']): string => {
  return `log-entry--${status}`;
};

// Formatting methods
const formatUptime = (seconds: number): string => {
  if (seconds === 0) return 'N/A';
  
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

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString();
};

// API methods
const fetchDevices = async (): Promise<void> => {
  try {
    isRefreshing.value = true;
    const response = await axios.get('/api/v1/router/status');
    realtimeStore.updateMikroTikDevices(response.data.data);
  } catch (error) {
    console.error('Failed to fetch MikroTik devices:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to fetch MikroTik devices'
    });
  } finally {
    isRefreshing.value = false;
  }
};

const fetchDeviceStatus = async (): Promise<void> => {
  try {
    const response = await axios.get('/api/v1/router/status');
    const statusUpdates = response.data.data;
    
    statusUpdates.forEach((update: any) => {
      realtimeStore.updateMikroTikDevice(update.device_id, {
        status: update.status,
        last_seen: update.last_seen,
        uptime_seconds: update.uptime_seconds,
        updated_at: new Date().toISOString()
      });
    });
  } catch (error) {
    console.error('Failed to fetch device status:', error);
  }
};

const fetchDeviceLogs = async (deviceId: string): Promise<void> => {
  try {
    const response = await axios.get(`/api/v1/router/interfaces`);
    deviceLogs.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch device logs:', error);
    appStore.addNotification({
      type: 'error',
      title: 'Error',
      message: 'Failed to fetch device logs'
    });
  }
};

// Event handlers
const refreshDevices = async (): Promise<void> => {
  await fetchDevices();
  await fetchDeviceStatus();
};

const viewDeviceDetails = (device: MikroTikDevice): void => {
  selectedDevice.value = device;
  showDetailsModal.value = true;
};

const configureDevice = (device: MikroTikDevice): void => {
  // Navigate to the configuration page with the device ID
  router.push({
    name: 'mikrotik-config',
    query: { deviceId: device.id }
  });
};

const viewDeviceLogs = async (device: MikroTikDevice): Promise<void> => {
  selectedDevice.value = device;
  await fetchDeviceLogs(device.id);
  showLogsModal.value = true;
};

const closeDetailsModal = (): void => {
  showDetailsModal.value = false;
  selectedDevice.value = null;
};

const closeLogsModal = (): void => {
  showLogsModal.value = false;
  selectedDevice.value = null;
  deviceLogs.value = [];
  logFilter.value = 'all';
};

// Polling setup
const startPolling = (): void => {
  // Poll device status every 30 seconds
  pollingInterval = setInterval(async () => {
    await fetchDeviceStatus();
  }, 30000);
};

const stopPolling = (): void => {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
};

// Lifecycle hooks
onMounted(async () => {
  await fetchDevices();
  await fetchDeviceStatus();
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});
</script>

<style scoped>
.mikrotik-monitor {
  padding: 1.5rem;
}

.monitor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.monitor-header h2 {
  margin: 0;
  color: var(--text-primary);
}

.header-actions {
  display: flex;
  align-items: center;
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
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-color-dark);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-outline {
  background-color: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-primary);
}

.btn-outline:hover {
  background-color: var(--background-secondary);
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.devices-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.device-card {
  background: var(--background-secondary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
  transition: all 0.2s;
}

.device-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.device-card--online {
  border-left: 4px solid green;
}

.device-card--offline {
  border-left: 4px solid red;
}

.device-card--error {
  border-left: 4px solid orange;
}

.device-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.device-info h3 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1.125rem;
}

.device-ip {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.875rem;
  font-family: monospace;
}

.device-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
}

.status-text {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.device-details {
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.detail-row .label {
  color: var(--text-secondary);
  font-weight: 500;
}

.detail-row .value {
  color: var(--text-primary);
  text-align: right;
}

.device-actions {
  display: flex;
  gap: 0.5rem;
}

/* Modal Styles */
.device-details-modal,
.device-logs-modal {
  max-height: 70vh;
  overflow-y: auto;
}

.details-section {
  margin-bottom: 2rem;
}

.details-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.5rem;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
}

.detail-item span {
  color: var(--text-primary);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.status-online {
  background-color: rgba(34, 197, 94, 0.1);
  color: rgb(34, 197, 94);
}

.status-badge.status-offline {
  background-color: rgba(239, 68, 68, 0.1);
  color: rgb(239, 68, 68);
}

.status-badge.status-error {
  background-color: rgba(249, 115, 22, 0.1);
  color: rgb(249, 115, 22);
}

/* Logs Modal Styles */
.logs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.logs-header h4 {
  margin: 0;
  color: var(--text-primary);
}

.form-select {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background-color: var(--background-primary);
  color: var(--text-primary);
}

.logs-list {
  max-height: 400px;
  overflow-y: auto;
}

.log-entry {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.2s;
}

.log-entry:hover {
  background-color: var(--background-secondary);
}

.log-entry--online {
  border-left: 3px solid green;
}

.log-entry--offline {
  border-left: 3px solid red;
}

.log-entry--error {
  border-left: 3px solid orange;
}

.log-timestamp {
  flex-shrink: 0;
  width: 150px;
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-family: monospace;
}

.log-content {
  flex: 1;
}

.log-status-change {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.status-from,
.status-to {
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-from.status-online,
.status-to.status-online {
  background-color: rgba(34, 197, 94, 0.1);
  color: rgb(34, 197, 94);
}

.status-from.status-offline,
.status-to.status-offline {
  background-color: rgba(239, 68, 68, 0.1);
  color: rgb(239, 68, 68);
}

.status-from.status-error,
.status-to.status-error {
  background-color: rgba(249, 115, 22, 0.1);
  color: rgb(249, 115, 22);
}

.log-details {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

/* Responsive Design */
@media (max-width: 768px) {
  .mikrotik-monitor {
    padding: 1rem;
  }

  .monitor-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: space-between;
  }

  .devices-grid {
    grid-template-columns: 1fr;
  }

  .device-header {
    flex-direction: column;
    gap: 0.5rem;
  }

  .logs-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .log-entry {
    flex-direction: column;
    gap: 0.5rem;
  }

  .log-timestamp {
    width: auto;
  }
}
</style>