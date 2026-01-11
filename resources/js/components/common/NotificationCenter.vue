<template>
  <teleport to="body">
    <div class="notification-center">
      <transition-group name="notification" tag="div">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          :class="[
            'notification',
            `notification--${notification.type}`,
            { 'notification--persistent': notification.duration === 0 }
          ]"
          :role="notification.type === 'error' ? 'alert' : 'status'"
          :aria-live="notification.type === 'error' ? 'assertive' : 'polite'"
        >
          <div class="notification__icon">
            <!-- Success Icon -->
            <svg v-if="notification.type === 'success'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            
            <!-- Error Icon -->
            <svg v-else-if="notification.type === 'error'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            
            <!-- Warning Icon -->
            <svg v-else-if="notification.type === 'warning'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            
            <!-- Info Icon (default) -->
            <svg v-else width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          
          <div class="notification__content">
            <h4 v-if="notification.title" class="notification__title">
              {{ notification.title }}
            </h4>
            <p class="notification__message">
              {{ notification.message }}
            </p>
            
            <div v-if="notification.actions && notification.actions.length > 0" class="notification__actions">
              <button
                v-for="action in notification.actions"
                :key="action.label"
                @click="handleActionClick(action, notification.id)"
                :class="[
                  'notification__action',
                  { 'notification__action--primary': action.primary }
                ]"
              >
                {{ action.label }}
              </button>
            </div>
          </div>
          
          <button
            @click="removeNotification(notification.id)"
            class="notification__close"
            aria-label="Close notification"
            :title="`Close ${notification.title || 'notification'}`"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path d="M4.646 4.646a.5.5 0 01.708 0L8 7.293l2.646-2.647a.5.5 0 01.708.708L8.707 8l2.647 2.646a.5.5 0 01-.708.708L8 8.707l-2.646 2.647a.5.5 0 01-.708-.708L7.293 8 4.646 5.354a.5.5 0 010-.708z"/>
            </svg>
          </button>
          
          <!-- Progress bar for timed notifications -->
          <div 
            v-if="notification.duration && notification.duration > 0"
            class="notification__progress"
            :style="{ animationDuration: `${notification.duration}ms` }"
          ></div>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/store/modules/app'

const appStore = useAppStore()
const { notifications } = storeToRefs(appStore)
const { removeNotification } = appStore

const handleActionClick = (action: any, notificationId: string) => {
  try {
    action.action()
  } catch (error) {
    console.error('Error executing notification action:', error)
    appStore.addErrorNotification(
      'Failed to execute action. Please try again.',
      'Action Failed'
    )
  } finally {
    // Remove notification after action is executed (unless it's a persistent one)
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification && notification.duration !== 0) {
      removeNotification(notificationId)
    }
  }
}
</script>

<style scoped>
.notification-center {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 1000;
  max-width: 420px;
  pointer-events: none;
}

.notification {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  padding: 1rem;
  margin-bottom: 0.75rem;
  position: relative;
  min-width: 320px;
  max-width: 420px;
  pointer-events: auto;
  overflow: hidden;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.notification--persistent {
  border-width: 2px;
}

.notification--success {
  border-left: 4px solid var(--success-color);
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(16, 185, 129, 0.05) 100%);
}

.notification--success .notification__icon {
  color: var(--success-color);
}

.notification--error {
  border-left: 4px solid var(--error-color);
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(239, 68, 68, 0.05) 100%);
}

.notification--error .notification__icon {
  color: var(--error-color);
}

.notification--warning {
  border-left: 4px solid var(--warning-color);
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(245, 158, 11, 0.05) 100%);
}

.notification--warning .notification__icon {
  color: var(--warning-color);
}

.notification--info {
  border-left: 4px solid var(--info-color);
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(6, 182, 212, 0.05) 100%);
}

.notification--info .notification__icon {
  color: var(--info-color);
}

.notification__icon {
  flex-shrink: 0;
  margin-top: 0.125rem;
  opacity: 0.9;
}

.notification__content {
  flex: 1;
  min-width: 0;
}

.notification__title {
  margin: 0 0 0.375rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.3;
}

.notification__message {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
  line-height: 1.4;
  word-wrap: break-word;
}

.notification__actions {
  margin-top: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.notification__action {
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-normal);
  white-space: nowrap;
}

.notification__action:hover {
  background: var(--hover-bg);
  border-color: var(--primary-color);
  transform: translateY(-1px);
}

.notification__action--primary {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.notification__action--primary:hover {
  background: var(--primary-hover);
  border-color: var(--primary-hover);
}

.notification__close {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  background: none;
  border: none;
  color: var(--text-tertiary);
  cursor: pointer;
  width: 1.5rem;
  height: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  transition: all var(--transition-normal);
  flex-shrink: 0;
}

.notification__close:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.notification__progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  animation: notificationProgress linear forwards;
  transform-origin: left;
}

@keyframes notificationProgress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}

/* Transitions */
.notification-enter-active {
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.notification-leave-active {
  transition: all 0.3s ease-in;
}

.notification-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.notification-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}

.notification-move {
  transition: transform 0.3s ease;
}

/* Responsive design */
@media (max-width: 640px) {
  .notification-center {
    top: 0.5rem;
    right: 0.5rem;
    left: 0.5rem;
    max-width: none;
  }
  
  .notification {
    min-width: auto;
    max-width: none;
    margin-bottom: 0.5rem;
  }
  
  .notification__actions {
    flex-direction: column;
  }
  
  .notification__action {
    width: 100%;
    justify-content: center;
  }
}

/* High contrast mode */
@media (prefers-contrast: high) {
  .notification {
    border-width: 2px;
  }
  
  .notification--success {
    border-color: var(--success-color);
  }
  
  .notification--error {
    border-color: var(--error-color);
  }
  
  .notification--warning {
    border-color: var(--warning-color);
  }
  
  .notification--info {
    border-color: var(--info-color);
  }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  .notification-enter-active,
  .notification-leave-active {
    transition-duration: 0.1s;
  }
  
  .notification__progress {
    animation: none;
    width: 0;
  }
  
  .notification__action:hover {
    transform: none;
  }
}

/* Focus management */
.notification:focus-within .notification__close {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.notification__action:focus-visible,
.notification__close:focus-visible {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Animation for persistent notifications */
.notification--persistent {
  animation: persistentPulse 2s ease-in-out infinite;
}

@keyframes persistentPulse {
  0%, 100% {
    box-shadow: var(--shadow-lg);
  }
  50% {
    box-shadow: var(--shadow-lg), 0 0 0 2px rgba(59, 130, 246, 0.2);
  }
}

/* Dark theme adjustments */
[data-theme="dark"] .notification {
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(255, 255, 255, 0.02) 100%);
}

[data-theme="dark"] .notification--success {
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(16, 185, 129, 0.08) 100%);
}

[data-theme="dark"] .notification--error {
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(239, 68, 68, 0.08) 100%);
}

[data-theme="dark"] .notification--warning {
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(245, 158, 11, 0.08) 100%);
}

[data-theme="dark"] .notification--info {
  background: linear-gradient(135deg, var(--card-bg) 0%, rgba(6, 182, 212, 0.08) 100%);
}
</style>