<template>
  <teleport to="body">
    <transition name="loading-overlay">
      <div v-if="active" class="loading-overlay" :class="{ 'with-progress': showProgress }">
        <div class="loading-spinner">
          <div class="spinner" :class="spinnerType"></div>
          <p v-if="message" class="loading-message">{{ message }}</p>
          
          <!-- Progress bar -->
          <div v-if="showProgress" class="progress-container">
            <div class="progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: `${progress}%` }"
              ></div>
            </div>
            <span class="progress-text">{{ progress }}%</span>
          </div>
          
          <!-- Cancel button -->
          <button 
            v-if="cancellable" 
            class="cancel-btn"
            @click="$emit('cancel')"
          >
            Cancel
          </button>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  active: boolean;
  message?: string;
  progress?: number;
  showProgress?: boolean;
  cancellable?: boolean;
  spinnerType?: 'default' | 'dots' | 'pulse';
}

const props = withDefaults(defineProps<Props>(), {
  active: false,
  message: 'Loading...',
  progress: 0,
  showProgress: false,
  cancellable: false,
  spinnerType: 'default',
});

const emit = defineEmits<{
  cancel: [];
}>();

// Ensure progress is between 0 and 100
const normalizedProgress = computed(() => {
  return Math.max(0, Math.min(100, props.progress));
});
</script>

<style scoped>
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(2px);
}

.loading-spinner {
  text-align: center;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  min-width: 200px;
}

.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.spinner.default {
  border: 4px solid rgba(59, 130, 246, 0.3);
  border-top: 4px solid #3b82f6;
}

.spinner.dots {
  position: relative;
  background: none;
  border: none;
}

.spinner.dots::before,
.spinner.dots::after {
  content: '';
  position: absolute;
  width: 8px;
  height: 8px;
  background: #3b82f6;
  border-radius: 50%;
  animation: dots 1.4s infinite ease-in-out both;
}

.spinner.dots::before {
  left: -12px;
  animation-delay: -0.32s;
}

.spinner.dots::after {
  right: -12px;
  animation-delay: 0.32s;
}

.spinner.pulse {
  background: #3b82f6;
  animation: pulse 1.5s ease-in-out infinite;
}

.loading-message {
  color: #374151;
  font-size: 0.875rem;
  margin: 0 0 1rem;
  font-weight: 500;
}

.progress-container {
  margin-top: 1rem;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #1d4ed8);
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.cancel-btn {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease;
}

.cancel-btn:hover {
  background: #dc2626;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@keyframes dots {
  0%, 80%, 100% {
    transform: scale(0);
  }
  40% {
    transform: scale(1);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.7;
  }
}

/* Transitions */
.loading-overlay-enter-active,
.loading-overlay-leave-active {
  transition: opacity 0.3s ease;
}

.loading-overlay-enter-from,
.loading-overlay-leave-to {
  opacity: 0;
}

.loading-spinner {
  transform: scale(0.9);
  transition: transform 0.3s ease;
}

.loading-overlay-enter-active .loading-spinner,
.loading-overlay-leave-active .loading-spinner {
  transition: transform 0.3s ease;
}

.loading-overlay-enter-from .loading-spinner,
.loading-overlay-leave-to .loading-spinner {
  transform: scale(0.8);
}
</style>