<template>
    <div class="notification-center" :class="{ 'has-notifications': notifications.length > 0 }">
        <!-- Bell Icon with Badge -->
        <button
            class="notification-trigger"
            @click="toggleNotifications"
            :aria-label="`${unreadCount} unread notifications`"
            :title="`${unreadCount} unread notifications`"
        >
            <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>

            <span v-if="unreadCount > 0" class="notification-badge">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
        </button>

        <!-- Notifications Panel -->
        <transition name="slide-fade">
            <div
                v-if="isOpen"
                class="notifications-panel"
                v-click-outside="closeNotifications"
                ref="panel"
            >
                <div class="panel-header">
                    <h3 class="panel-title">Notifications</h3>
                    <div class="panel-actions">
                        <button
                            v-if="unreadCount > 0"
                            class="action-btn mark-all-read"
                            @click="markAllAsRead"
                            title="Mark all as read"
                        >
                            Mark all read
                        </button>
                        <button
                            v-if="notifications.length > 0"
                            class="action-btn clear-all"
                            @click="clearAll"
                            title="Clear all notifications"
                        >
                            Clear all
                        </button>
                    </div>
                </div>

                <div class="notifications-list" ref="list">
                    <div v-if="notifications.length === 0" class="empty-state">
                        <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="empty-text">No notifications</p>
                    </div>

                    <div v-else>
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            class="notification-item"
                            :class="[
                notification.type,
                { unread: !notification.read, clickable: notification.clickable }
              ]"
                            @click="handleNotificationClick(notification)"
                        >
                            <div class="notification-icon">
                                <component
                                    :is="getIconComponent(notification.type)"
                                    :class="getIconClass(notification.type)"
                                />
                            </div>

                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4 class="notification-title">{{ notification.title }}</h4>
                                    <span class="notification-time">{{ formatTime(notification.timestamp) }}</span>
                                </div>

                                <p class="notification-message" v-html="notification.message"></p>

                                <div v-if="notification.meta" class="notification-meta">
                                    <template v-if="notification.meta.user">
                    <span class="meta-item">
                      <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                      {{ notification.meta.user }}
                    </span>
                                    </template>

                                    <template v-if="notification.meta.tenant">
                    <span class="meta-item">
                      <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                      {{ notification.meta.tenant }}
                    </span>
                                    </template>
                                </div>
                            </div>

                            <div class="notification-actions">
                                <button
                                    v-if="!notification.read"
                                    class="action-btn mark-read"
                                    @click.stop="markAsRead(notification.id)"
                                    title="Mark as read"
                                >
                                    <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>

                                <button
                                    class="action-btn dismiss"
                                    @click.stop="dismissNotification(notification.id)"
                                    title="Dismiss"
                                >
                                    <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-footer">
                    <router-link to="/notifications" class="view-all-link" @click.native="closeNotifications">
                        View all notifications
                        <svg class="link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>
            </div>
        </transition>

        <!-- Notification Toast (for new notifications) -->
        <transition-group name="toast-slide" tag="div" class="notification-toasts">
            <div
                v-for="toast in visibleToasts"
                :key="toast.id"
                class="notification-toast"
                :class="toast.type"
                @click="handleToastClick(toast)"
            >
                <div class="toast-icon">
                    <component
                        :is="getIconComponent(toast.type)"
                        :class="getIconClass(toast.type)"
                    />
                </div>

                <div class="toast-content">
                    <h4 class="toast-title">{{ toast.title }}</h4>
                    <p class="toast-message">{{ toast.message }}</p>
                </div>

                <button class="toast-close" @click.stop="removeToast(toast.id)">
                    <svg class="close-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="toast-progress" :style="{ animationDuration: `${toast.duration}ms` }"></div>
            </div>
        </transition-group>
    </div>
</template>

<script>
import { InformationCircleIcon, CheckCircleIcon, ExclamationTriangleIcon, ExclamationCircleIcon } from '@heroicons/vue/outline'

export default {
    name: 'NotificationCenter',
    directives: {
        'click-outside': {
            bind(el, binding, vnode) {
                el.clickOutsideEvent = function(event) {
                    if (!(el === event.target || el.contains(event.target))) {
                        vnode.context[binding.expression](event)
                    }
                }
                document.body.addEventListener('click', el.clickOutsideEvent)
            },
            unbind(el) {
                document.body.removeEventListener('click', el.clickOutsideEvent)
            }
        }
    },
    props: {
        position: {
            type: String,
            default: 'top-right',
            validator: value => ['top-right', 'top-left', 'bottom-right', 'bottom-left'].includes(value)
        },
        maxToasts: {
            type: Number,
            default: 3
        },
        toastDuration: {
            type: Number,
            default: 5000
        },
        autoClose: {
            type: Boolean,
            default: true
        }
    },
    data() {
        return {
            isOpen: false,
            notifications: [],
            toasts: [],
            nextId: 1
        }
    },
    computed: {
        unreadCount() {
            return this.notifications.filter(n => !n.read).length
        },

        visibleToasts() {
            return this.toasts.slice(0, this.maxToasts)
        }
    },
    created() {
        this.loadNotifications()
        this.setupWebSocket()
    },
    mounted() {
        // Listen for new notifications from Vuex or other components
        this.$root.$on('new-notification', this.addNotification)

        // Listen for notification events from API
        window.Echo?.private('notifications')
            .listen('NotificationCreated', (event) => {
                this.handlePushNotification(event.notification)
            })
    },
    beforeDestroy() {
        this.$root.$off('new-notification', this.addNotification)
        this.toasts.forEach(toast => clearTimeout(toast.timeout))
    },
    methods: {
        // Icon helpers
        getIconComponent(type) {
            const icons = {
                info: InformationCircleIcon,
                success: CheckCircleIcon,
                warning: ExclamationTriangleIcon,
                error: ExclamationCircleIcon
            }
            return icons[type] || InformationCircleIcon
        },

        getIconClass(type) {
            const classes = {
                info: 'text-blue-500',
                success: 'text-green-500',
                warning: 'text-yellow-500',
                error: 'text-red-500'
            }
            return classes[type] || 'text-gray-500'
        },

        // Panel methods
        toggleNotifications() {
            this.isOpen = !this.isOpen
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.scrollToTop()
                })
            }
        },

        closeNotifications() {
            this.isOpen = false
        },

        scrollToTop() {
            if (this.$refs.list) {
                this.$refs.list.scrollTop = 0
            }
        },

        // Notification methods
        async loadNotifications() {
            try {
                // Load from API
                const response = await this.$api.get('/notifications')
                this.notifications = response.data.map(n => ({
                    ...n,
                    clickable: !!n.action_url || !!n.action_event
                }))
            } catch (error) {
                console.error('Failed to load notifications:', error)
            }
        },

        addNotification(notification) {
            const id = this.nextId++
            const newNotification = {
                id,
                type: notification.type || 'info',
                title: notification.title,
                message: notification.message,
                timestamp: new Date().toISOString(),
                read: false,
                clickable: !!notification.action,
                meta: notification.meta || {},
                action: notification.action,
                duration: notification.duration || this.toastDuration
            }

            // Add to notifications list
            this.notifications.unshift(newNotification)

            // Show toast if not in panel
            if (!this.isOpen) {
                this.showToast(newNotification)
            }

            // Emit event
            this.$emit('notification-added', newNotification)

            // Play sound for important notifications
            if (notification.type === 'error' || notification.priority === 'high') {
                this.playNotificationSound()
            }
        },

        handlePushNotification(pushData) {
            this.addNotification({
                type: pushData.type,
                title: pushData.title,
                message: pushData.body,
                action: pushData.action,
                meta: pushData.data,
                priority: pushData.priority
            })
        },

        // Toast methods
        showToast(notification) {
            const toastId = `toast-${notification.id}`
            const toast = {
                ...notification,
                id: toastId,
                duration: notification.duration || this.toastDuration
            }

            this.toasts.unshift(toast)

            if (this.autoClose) {
                toast.timeout = setTimeout(() => {
                    this.removeToast(toastId)
                }, toast.duration)
            }
        },

        removeToast(toastId) {
            const index = this.toasts.findIndex(t => t.id === toastId)
            if (index !== -1) {
                const toast = this.toasts[index]
                if (toast.timeout) {
                    clearTimeout(toast.timeout)
                }
                this.toasts.splice(index, 1)
            }
        },

        handleToastClick(toast) {
            this.handleNotificationClick(toast)
            this.removeToast(toast.id)
        },

        // Action methods
        handleNotificationClick(notification) {
            if (notification.clickable) {
                if (notification.action?.url) {
                    if (notification.action.url.startsWith('/')) {
                        this.$router.push(notification.action.url)
                    } else {
                        window.open(notification.action.url, '_blank')
                    }
                } else if (notification.action?.event) {
                    this.$emit(notification.action.event, notification)
                }

                if (notification.action?.closeOnClick !== false) {
                    this.isOpen = false
                }
            }

            if (!notification.read) {
                this.markAsRead(notification.id)
            }
        },

        async markAsRead(notificationId) {
            const notification = this.notifications.find(n => n.id === notificationId)
            if (notification && !notification.read) {
                notification.read = true

                try {
                    await this.$api.patch(`/notifications/${notificationId}/read`)
                } catch (error) {
                    console.error('Failed to mark notification as read:', error)
                }

                this.$emit('notification-read', notificationId)
            }
        },

        async markAllAsRead() {
            const unreadIds = this.notifications
                .filter(n => !n.read)
                .map(n => n.id)

            if (unreadIds.length === 0) return

            this.notifications.forEach(n => {
                n.read = true
            })

            try {
                await this.$api.post('/notifications/mark-all-read')
            } catch (error) {
                console.error('Failed to mark all notifications as read:', error)
            }

            this.$emit('all-notifications-read', unreadIds)
        },

        async dismissNotification(notificationId) {
            const index = this.notifications.findIndex(n => n.id === notificationId)
            if (index !== -1) {
                const [notification] = this.notifications.splice(index, 1)

                try {
                    await this.$api.delete(`/notifications/${notificationId}`)
                } catch (error) {
                    console.error('Failed to dismiss notification:', error)
                }

                this.$emit('notification-dismissed', notificationId)
            }
        },

        async clearAll() {
            if (this.notifications.length === 0) return

            const notificationIds = this.notifications.map(n => n.id)
            this.notifications = []

            try {
                await this.$api.delete('/notifications/clear')
            } catch (error) {
                console.error('Failed to clear all notifications:', error)
            }

            this.$emit('all-notifications-cleared', notificationIds)
        },

        // Utility methods
        formatTime(timestamp) {
            const date = new Date(timestamp)
            const now = new Date()
            const diffMs = now - date
            const diffMins = Math.floor(diffMs / 60000)
            const diffHours = Math.floor(diffMs / 3600000)
            const diffDays = Math.floor(diffMs / 86400000)

            if (diffMins < 1) return 'Just now'
            if (diffMins < 60) return `${diffMins}m ago`
            if (diffHours < 24) return `${diffHours}h ago`
            if (diffDays < 7) return `${diffDays}d ago`
            return date.toLocaleDateString()
        },

        playNotificationSound() {
            const audio = new Audio('/notification-sound.mp3')
            audio.volume = 0.3
            audio.play().catch(() => {
                // Silent fail if audio can't play
            })
        },

        setupWebSocket() {
            // Setup WebSocket connection for real-time notifications
            if (typeof window.Echo !== 'undefined') {
                window.Echo.private('notifications')
                    .listen('NotificationCreated', (event) => {
                        this.handlePushNotification(event.notification)
                    })
            }
        }
    }
}
</script>

<style scoped>
.notification-center {
    position: relative;
    display: inline-block;
}

.notification-trigger {
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    color: #6b7280;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-trigger:hover {
    background: #f3f4f6;
    color: #374151;
}

.bell-icon {
    width: 24px;
    height: 24px;
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #ef4444;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid white;
}

/* Notifications Panel */
.notifications-panel {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 400px;
    max-width: 90vw;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    max-height: 600px;
}

.panel-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f9fafb;
    border-radius: 12px 12px 0 0;
}

.panel-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
}

.panel-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 4px 12px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #f3f4f6;
    color: #374151;
}

.action-btn.mark-all-read {
    color: #3b82f6;
    border-color: #93c5fd;
}

.action-btn.clear-all {
    color: #ef4444;
    border-color: #fca5a5;
}

.notifications-list {
    flex: 1;
    overflow-y: auto;
    max-height: 400px;
    padding: 4px 0;
}

.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #9ca3af;
}

.empty-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 16px;
    color: #d1d5db;
}

.empty-text {
    margin: 0;
    font-size: 0.95rem;
}

/* Notification Item */
.notification-item {
    display: flex;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.2s ease;
    position: relative;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item.unread {
    background: #f8fafc;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #3b82f6;
    border-radius: 0 2px 2px 0;
}

.notification-item.clickable {
    cursor: pointer;
}

.notification-item.clickable:hover {
    background: #f9fafb;
}

.notification-item.error.unread::before {
    background: #ef4444;
}

.notification-item.warning.unread::before {
    background: #f59e0b;
}

.notification-item.success.unread::before {
    background: #10b981;
}

.notification-icon {
    margin-right: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: flex-start;
    padding-top: 2px;
}

.notification-icon svg {
    width: 20px;
    height: 20px;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 4px;
}

.notification-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 500;
    color: #111827;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
    white-space: nowrap;
    margin-left: 8px;
}

.notification-message {
    margin: 0 0 8px;
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.5;
}

.notification-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: #9ca3af;
}

.meta-icon {
    width: 12px;
    height: 12px;
}

.notification-actions {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-left: 8px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.notification-item:hover .notification-actions {
    opacity: 1;
}

.notification-actions .action-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    border-radius: 4px;
}

.notification-actions .action-btn:hover {
    background: #f3f4f6;
}

.action-icon {
    width: 16px;
    height: 16px;
}

.panel-footer {
    padding: 12px 20px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 0 0 12px 12px;
}

.view-all-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #3b82f6;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.2s ease;
}

.view-all-link:hover {
    color: #2563eb;
    text-decoration: underline;
}

.link-icon {
    width: 16px;
    height: 16px;
}

/* Notification Toasts */
.notification-toasts {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 350px;
    max-width: 90vw;
}

.notification-toasts.top-right {
    top: 20px;
    right: 20px;
    align-items: flex-end;
}

.notification-toasts.top-left {
    top: 20px;
    left: 20px;
    align-items: flex-start;
}

.notification-toasts.bottom-right {
    bottom: 20px;
    right: 20px;
    align-items: flex-end;
}

.notification-toasts.bottom-left {
    bottom: 20px;
    left: 20px;
    align-items: flex-start;
}

.notification-toast {
    position: relative;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    pointer-events: auto;
    animation: toastIn 0.3s ease;
    border-left: 4px solid #3b82f6;
    max-width: 100%;
    transition: all 0.3s ease;
}

.notification-toast.error {
    border-left-color: #ef4444;
}

.notification-toast.warning {
    border-left-color: #f59e0b;
}

.notification-toast.success {
    border-left-color: #10b981;
}

.notification-toast.info {
    border-left-color: #3b82f6;
}

.toast-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    padding-top: 2px;
}

.toast-icon svg {
    width: 20px;
    height: 20px;
}

.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-title {
    margin: 0 0 4px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #111827;
}

.toast-message {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.4;
}

.toast-close {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: none;
    background: none;
    color: #9ca3af;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
    margin-left: 4px;
}

.toast-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.close-icon {
    width: 16px;
    height: 16px;
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: rgba(59, 130, 246, 0.2);
    border-radius: 0 0 8px 8px;
    transform-origin: left;
    animation: progressBar linear forwards;
}

.notification-toast.error .toast-progress {
    background: rgba(239, 68, 68, 0.2);
}

.notification-toast.warning .toast-progress {
    background: rgba(245, 158, 11, 0.2);
}

.notification-toast.success .toast-progress {
    background: rgba(16, 185, 129, 0.2);
}

/* Animations */
@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateX(100px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes progressBar {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}

.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s ease;
}

.toast-slide-enter {
    opacity: 0;
    transform: translateX(100px) scale(0.9);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100px) scale(0.9);
}

.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}

.slide-fade-enter,
.slide-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Heroicons fallback classes */
.text-blue-500 { color: #3b82f6; }
.text-green-500 { color: #10b981; }
.text-yellow-500 { color: #f59e0b; }
.text-red-500 { color: #ef4444; }

/* Responsive */
@media (max-width: 640px) {
    .notifications-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        margin: 0;
        width: 100%;
        height: 100%;
        max-width: none;
        max-height: none;
        border-radius: 0;
        border: none;
    }

    .notification-toasts {
        width: 90vw;
    }

    .notification-toast {
        width: 100%;
    }
}
</style>
