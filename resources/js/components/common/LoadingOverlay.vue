<template>
    <transition name="fade">
        <div v-if="active" class="loading-overlay" :class="[variant, size]" :style="overlayStyle">
            <div class="loading-content" :class="{ 'with-text': text || $slots.default }">
                <div class="spinner-container">
                    <div class="spinner" :style="spinnerStyle"></div>
                    <div v-if="percentage !== null" class="percentage">
                        {{ Math.round(percentage) }}%
                    </div>
                </div>

                <div v-if="text || $slots.default" class="loading-text">
                    <slot>{{ text }}</slot>
                </div>

                <div v-if="showCancel" class="loading-cancel">
                    <button class="cancel-btn" @click="handleCancel" :disabled="cancelling">
                        {{ cancelling ? 'Cancelling...' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <div v-if="progress !== null" class="loading-progress">
                <div class="progress-bar" :style="{ width: `${progress}%` }"></div>
            </div>
        </div>
    </transition>
</template>

<script>
export default {
    name: 'LoadingOverlay',
    props: {
        active: {
            type: Boolean,
            default: false
        },
        text: {
            type: String,
            default: ''
        },
        variant: {
            type: String,
            default: 'default',
            validator: value => ['default', 'dark', 'light', 'blur'].includes(value)
        },
        size: {
            type: String,
            default: 'md',
            validator: value => ['sm', 'md', 'lg'].includes(value)
        },
        spinnerColor: {
            type: String,
            default: ''
        },
        backgroundColor: {
            type: String,
            default: ''
        },
        zIndex: {
            type: Number,
            default: 9998
        },
        percentage: {
            type: Number,
            default: null
        },
        progress: {
            type: Number,
            default: null
        },
        showCancel: {
            type: Boolean,
            default: false
        },
        cancellable: {
            type: Boolean,
            default: true
        }
    },
    data() {
        return {
            cancelling: false
        }
    },
    computed: {
        overlayStyle() {
            const styles = {}

            if (this.backgroundColor) {
                styles.backgroundColor = this.backgroundColor
            }

            if (this.zIndex) {
                styles.zIndex = this.zIndex
            }

            return styles
        },

        spinnerStyle() {
            const styles = {}

            if (this.spinnerColor) {
                styles.borderTopColor = this.spinnerColor
            }

            return styles
        }
    },
    methods: {
        handleCancel() {
            if (!this.cancellable) return

            this.cancelling = true
            this.$emit('cancel')

            // Reset cancelling state after 2 seconds if not handled
            setTimeout(() => {
                this.cancelling = false
            }, 2000)
        }
    },
    watch: {
        active(newVal) {
            if (newVal) {
                // Prevent body scroll when loading overlay is active
                document.body.style.overflow = 'hidden'
                this.$emit('show')
            } else {
                // Restore body scroll
                document.body.style.overflow = ''
                this.cancelling = false
                this.$emit('hide')
            }
        }
    },
    mounted() {
        if (this.active) {
            document.body.style.overflow = 'hidden'
        }
    },
    beforeDestroy() {
        // Always restore body scroll when component is destroyed
        document.body.style.overflow = ''
    }
}
</script>

<style scoped>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9998;
}

/* Variants */
.loading-overlay.default {
    background: rgba(255, 255, 255, 0.9);
}

.loading-overlay.dark {
    background: rgba(0, 0, 0, 0.8);
}

.loading-overlay.light {
    background: rgba(255, 255, 255, 0.5);
}

.loading-overlay.blur {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
}

.loading-content {
    text-align: center;
    padding: 30px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    max-width: 400px;
}

.loading-overlay.dark .loading-content,
.loading-overlay.blur .loading-content {
    background: rgba(255, 255, 255, 0.95);
}

.loading-content.with-text {
    padding: 40px 30px;
}

/* Sizes */
.loading-overlay.sm .loading-content {
    padding: 20px;
    min-width: 150px;
}

.loading-overlay.lg .loading-content {
    padding: 50px 40px;
    min-width: 300px;
}

.spinner-container {
    position: relative;
    margin: 0 auto 20px;
    width: 60px;
    height: 60px;
}

.loading-content.with-text .spinner-container {
    margin-bottom: 24px;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 4px solid #e5e7eb;
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin 1s linear infinite;
    box-sizing: border-box;
}

.loading-overlay.sm .spinner {
    width: 40px;
    height: 40px;
    border-width: 3px;
}

.loading-overlay.lg .spinner {
    width: 80px;
    height: 80px;
    border-width: 5px;
}

.loading-overlay.dark .spinner {
    border-color: rgba(255, 255, 255, 0.2);
    border-top-color: white;
}

.percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.875rem;
    font-weight: 600;
    color: #3b82f6;
}

.loading-overlay.dark .percentage {
    color: white;
}

.loading-text {
    font-size: 1rem;
    color: #374151;
    line-height: 1.5;
    margin-bottom: 20px;
}

.loading-overlay.sm .loading-text {
    font-size: 0.95rem;
}

.loading-overlay.lg .loading-text {
    font-size: 1.125rem;
}

.loading-overlay.dark .loading-text {
    color: white;
}

.loading-cancel {
    margin-top: 16px;
}

.cancel-btn {
    padding: 8px 20px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #374151;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cancel-btn:hover:not(:disabled) {
    background: #e5e7eb;
}

.cancel-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.loading-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(0, 0, 0, 0.1);
}

.loading-overlay.dark .loading-progress {
    background: rgba(255, 255, 255, 0.1);
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    transition: width 0.3s ease;
    border-radius: 0 2px 2px 0;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter,
.fade-leave-to {
    opacity: 0;
}

/* For full page loading (covers entire screen) */
.loading-overlay.fullscreen {
    z-index: 9999;
}

/* For inline loading (within a container) */
.loading-overlay.inline {
    position: absolute;
}

/* For button loading states */
.loading-overlay.button {
    position: absolute;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 6px;
}

.loading-overlay.button .loading-content {
    padding: 10px;
    background: none;
    box-shadow: none;
}

.loading-overlay.button .spinner-container {
    margin: 0;
    width: 20px;
    height: 20px;
}

.loading-overlay.button .spinner {
    width: 20px;
    height: 20px;
    border-width: 2px;
}
</style>
