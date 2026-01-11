<template>
  <div class="connection-status" :class="statusClass">
    <div class="status-indicator">
      <div class="status-dot" :class="dotClass"></div>
      <span class="status-text">{{ statusText }}</span>
    </div>
    
    <button 
      v-if="connectionStatus === 'disconnected'" 
      @click="reconnect"
      class="reconnect-btn"
      :disabled="isReconnecting"
    >
      {{ isReconnecting ? 'Reconnecting...' : 'Reconnect' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { webSocketService } from '@/services/websocket';

const realtimeStore = useRealtimeStore();
const isReconnecting = ref(false);

const connectionStatus = computed(() => realtimeStore.connectionStatus);

const statusClass = computed(() => ({
  'status-connected': connectionStatus.value === 'connected',
  'status-disconnected': connectionStatus.value === 'disconnected',
  'status-reconnecting': connectionStatus.value === 'reconnecting',
}));

const dotClass = computed(() => ({
  'dot-connected': connectionStatus.value === 'connected',
  'dot-disconnected': connectionStatus.value === 'disconnected',
  'dot-reconnecting': connectionStatus.value === 'reconnecting',
}));

const statusText = computed(() => {
  switch (connectionStatus.value) {
    case 'connected':
      return 'Connected';
    case 'reconnecting':
      return 'Reconnecting...';
    case 'disconnected':
    default:
      return 'Disconnected';
  }
});

const reconnect = async () => {
  isReconnecting.value = true;
  try {
    webSocketService.reconnect();
    // Wait a bit to see if connection is established
    setTimeout(() => {
      isReconnecting.value = false;
    }, 2000);
  } catch (error) {
    console.error('Reconnection failed:', error);
    isReconnecting.value = false;
  }
};
</script>

<style scoped>
.connection-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  transition: all 0.2s ease;
}

.status-connected {
  background-color: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.status-disconnected {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.status-reconnecting {
  background-color: #fefce8;
  color: #ca8a04;
  border: 1px solid #fde047;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.dot-connected {
  background-color: #22c55e;
  animation: pulse-green 2s infinite;
}

.dot-disconnected {
  background-color: #ef4444;
}

.dot-reconnecting {
  background-color: #eab308;
  animation: pulse-yellow 1s infinite;
}

.status-text {
  font-weight: 500;
}

.reconnect-btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  background-color: white;
  border: 1px solid currentColor;
  border-radius: 0.25rem;
  transition: all 0.2s ease;
  cursor: pointer;
}

.reconnect-btn:hover:not(:disabled) {
  background-color: #f9fafb;
}

.reconnect-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@keyframes pulse-green {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@keyframes pulse-yellow {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}

/* Dark mode styles */
[data-theme="dark"] .status-connected {
  background-color: rgba(34, 197, 94, 0.2);
  color: #86efac;
  border-color: #166534;
}

[data-theme="dark"] .status-disconnected {
  background-color: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
  border-color: #991b1b;
}

[data-theme="dark"] .status-reconnecting {
  background-color: rgba(234, 179, 8, 0.2);
  color: #fde047;
  border-color: #a16207;
}

[data-theme="dark"] .reconnect-btn {
  background-color: #374151;
  border-color: #6b7280;
}

[data-theme="dark"] .reconnect-btn:hover:not(:disabled) {
  background-color: #4b5563;
}
</style>