<template>
    <teleport to="body">
        <transition name="modal-fade">
            <div v-if="show" class="modal-backdrop" @click.self="handleBackdropClick">
                <div
                    class="modal-container"
                    :class="[size, variant, { 'no-footer': !showFooter }, { 'scrollable': scrollable }]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :aria-describedby="descriptionId"
                >
                    <div class="modal-header" :class="{ 'no-title': !title && !$slots.title }">
                        <div v-if="title || $slots.title" class="modal-title-section">
                            <slot name="title">
                                <h3 :id="titleId" class="modal-title">{{ title }}</h3>
                                <span v-if="subtitle" class="modal-subtitle">{{ subtitle }}</span>
                            </slot>
                        </div>

                        <div class="modal-header-actions">
                            <slot name="header-actions"></slot>

                            <button
                                v-if="closable"
                                class="modal-close-btn"
                                @click="close"
                                aria-label="Close modal"
                                type="button"
                            >
                                <svg class="close-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="modal-content" :id="descriptionId">
                        <slot></slot>
                    </div>

                    <div v-if="showFooter || $slots.footer" class="modal-footer">
                        <slot name="footer">
                            <div class="default-footer">
                                <button
                                    v-if="cancelText"
                                    class="btn"
                                    :class="cancelButtonClass"
                                    @click="handleCancel"
                                    :disabled="loading"
                                >
                                    {{ cancelText }}
                                </button>
                                <button
                                    v-if="confirmText"
                                    class="btn"
                                    :class="confirmButtonClass"
                                    @click="handleConfirm"
                                    :disabled="loading || !canConfirm"
                                >
                                    <span v-if="loading" class="loading-spinner-sm"></span>
                                    {{ confirmText }}
                                </button>
                            </div>
                        </slot>
                    </div>

                    <div v-if="loading" class="modal-loading-overlay">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script>
export default {
    name: 'Modal',
    props: {
        show: {
            type: Boolean,
            required: true
        },
        title: {
            type: String,
            default: ''
        },
        subtitle: {
            type: String,
            default: ''
        },
        size: {
            type: String,
            default: 'md',
            validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl', 'full'].includes(value)
        },
        variant: {
            type: String,
            default: 'default',
            validator: (value) => ['default', 'danger', 'warning', 'success', 'info'].includes(value)
        },
        closable: {
            type: Boolean,
            default: true
        },
        closeOnBackdrop: {
            type: Boolean,
            default: true
        },
        closeOnEsc: {
            type: Boolean,
            default: true
        },
        loading: {
            type: Boolean,
            default: false
        },
        confirmText: {
            type: String,
            default: ''
        },
        cancelText: {
            type: String,
            default: ''
        },
        confirmButtonVariant: {
            type: String,
            default: 'primary',
            validator: (value) => ['primary', 'secondary', 'danger', 'warning', 'success', 'info'].includes(value)
        },
        cancelButtonVariant: {
            type: String,
            default: 'secondary',
            validator: (value) => ['primary', 'secondary', 'danger', 'warning', 'success', 'info'].includes(value)
        },
        showFooter: {
            type: Boolean,
            default: false
        },
        preventClose: {
            type: Boolean,
            default: false
        },
        scrollable: {
            type: Boolean,
            default: true
        },
        canConfirm: {
            type: Boolean,
            default: true
        },
        persistent: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            titleId: `modal-title-${Math.random().toString(36).substr(2, 9)}`,
            descriptionId: `modal-description-${Math.random().toString(36).substr(2, 9)}`
        }
    },
    computed: {
        confirmButtonClass() {
            return `btn-${this.confirmButtonVariant}`
        },
        
        cancelButtonClass() {
            return `btn-${this.cancelButtonVariant}`
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.$emit('open')
                this.addEventListeners()
                this.lockBodyScroll()
            } else {
                this.removeEventListeners()
                this.unlockBodyScroll()
                this.$emit('close')
            }
        }
    },
    methods: {
        close() {
            if (!this.preventClose && !this.persistent) {
                this.$emit('update:show', false)
                this.$emit('closed')
            }
        },

        handleConfirm() {
            this.$emit('confirm')
        },

        handleCancel() {
            this.$emit('cancel')
            if (!this.persistent) {
                this.close()
            }
        },

        handleBackdropClick() {
            if (this.closeOnBackdrop && !this.preventClose && !this.persistent) {
                this.close()
            }
        },

        handleEsc(e) {
            if (e.key === 'Escape' && this.closeOnEsc && !this.preventClose && !this.persistent) {
                this.close()
            }
        },

        addEventListeners() {
            if (this.closeOnEsc) {
                document.addEventListener('keydown', this.handleEsc)
            }
        },

        removeEventListeners() {
            if (this.closeOnEsc) {
                document.removeEventListener('keydown', this.handleEsc)
            }
        },

        lockBodyScroll() {
            document.body.style.overflow = 'hidden'
            document.body.style.paddingRight = this.getScrollbarWidth() + 'px'
        },

        unlockBodyScroll() {
            document.body.style.overflow = ''
            document.body.style.paddingRight = ''
        },

        getScrollbarWidth() {
            const scrollDiv = document.createElement('div')
            scrollDiv.style.cssText = 'width: 100px; height: 100px; overflow: scroll; position: absolute; top: -9999px;'
            document.body.appendChild(scrollDiv)
            const scrollbarWidth = scrollDiv.offsetWidth - scrollDiv.clientWidth
            document.body.removeChild(scrollDiv)
            return scrollbarWidth
        }
    },
    mounted() {
        if (this.show) {
            this.addEventListeners()
            this.lockBodyScroll()
        }
    },
    beforeUnmount() {
        this.removeEventListeners()
        this.unlockBodyScroll()
    }
}
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    animation: fadeIn 0.2s ease;
}

.modal-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 40px);
    animation: slideUp 0.3s ease;
    position: relative;
    overflow: hidden;
}

.modal-container.scrollable .modal-content {
    overflow-y: auto;
}

/* Size variants */
.modal-container.xs { width: 320px; }
.modal-container.sm { width: 400px; }
.modal-container.md { width: 500px; }
.modal-container.lg { width: 600px; }
.modal-container.xl { width: 800px; }
.modal-container.full { width: 95vw; max-width: 1200px; }

/* Variant styles */
.modal-container.danger {
    border-top: 4px solid #ef4444;
}

.modal-container.warning {
    border-top: 4px solid #f59e0b;
}

.modal-container.success {
    border-top: 4px solid #10b981;
}

.modal-container.info {
    border-top: 4px solid #3b82f6;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    min-height: 68px;
    box-sizing: border-box;
    flex-shrink: 0;
}

.modal-header.no-title {
    justify-content: flex-end;
    padding: 16px 24px;
    min-height: auto;
}

.modal-title-section {
    flex: 1;
    margin-right: 16px;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.4;
}

.modal-subtitle {
    display: block;
    margin-top: 4px;
    font-size: 0.875rem;
    color: #6b7280;
}

.modal-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.modal-close-btn:hover {
    background: #e5e7eb;
    color: #374151;
}

.modal-close-btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

.close-icon {
    width: 20px;
    height: 20px;
}

.modal-content {
    padding: 24px;
    flex: 1;
    min-height: 0;
}

.modal-container.no-footer .modal-content {
    padding-bottom: 32px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-shrink: 0;
}

.default-footer {
    display: flex;
    gap: 12px;
    width: 100%;
    justify-content: flex-end;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 80px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #2563eb;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #4b5563;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #dc2626;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover:not(:disabled) {
    background: #d97706;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #059669;
}

.btn-info {
    background: #3b82f6;
    color: white;
}

.btn-info:hover:not(:disabled) {
    background: #2563eb;
}

.loading-spinner-sm {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s linear infinite;
    margin-right: 6px;
}

.modal-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 12px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e5e7eb;
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin 1s linear infinite;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .modal-container {
        background: #1f2937;
        color: #f9fafb;
    }

    .modal-header,
    .modal-footer {
        background: #111827;
        border-color: #374151;
    }

    .modal-title {
        color: #f9fafb;
    }

    .modal-subtitle {
        color: #9ca3af;
    }

    .modal-close-btn {
        color: #9ca3af;
    }

    .modal-close-btn:hover {
        background: #374151;
        color: #f3f4f6;
    }
}

/* Responsive */
@media (max-width: 640px) {
    .modal-backdrop {
        padding: 10px;
    }

    .modal-container {
        width: 100% !important;
        max-height: calc(100vh - 20px);
        border-radius: 8px;
    }

    .modal-header,
    .modal-content,
    .modal-footer {
        padding: 16px;
    }

    .modal-header {
        min-height: 60px;
    }

    .modal-title {
        font-size: 1.125rem;
    }

    .btn {
        padding: 8px 16px;
        min-width: 70px;
    }

    .default-footer {
        flex-direction: column-reverse;
    }

    .default-footer .btn {
        width: 100%;
    }
}
</style>
