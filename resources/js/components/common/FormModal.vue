<template>
    <Modal
        :show="show"
        @update:show="$emit('update:show', $event)"
        :title="title"
        :subtitle="subtitle"
        :size="size"
        :variant="variant"
        :loading="loading"
        :prevent-close="loading"
        :persistent="persistent"
        :scrollable="scrollable"
        show-footer
        :confirm-text="submitText"
        :cancel-text="cancelText"
        :confirm-button-variant="submitButtonVariant"
        :can-confirm="canSubmit"
        @confirm="handleSubmit"
        @cancel="handleCancel"
        @open="$emit('open')"
        @close="$emit('close')"
        @closed="$emit('closed')"
    >
        <form @submit.prevent="handleSubmit" class="form-modal-content">
            <div v-if="description" class="form-description">
                {{ description }}
            </div>

            <div v-if="hasErrors" class="form-errors">
                <div class="error-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Please correct the following errors:</span>
                </div>
                <ul class="error-list">
                    <li v-for="error in flattenedErrors" :key="error">{{ error }}</li>
                </ul>
            </div>

            <slot></slot>
        </form>

        <template #footer>
            <div class="form-modal-footer">
                <slot name="footer-prepend"></slot>
                
                <div class="form-actions">
                    <button
                        type="button"
                        @click="handleCancel"
                        class="btn btn-secondary"
                        :disabled="loading"
                    >
                        {{ cancelText }}
                    </button>
                    
                    <slot name="additional-actions"></slot>
                    
                    <button
                        type="button"
                        @click="handleSubmit"
                        class="btn"
                        :class="submitButtonClass"
                        :disabled="loading || !canSubmit"
                    >
                        <span v-if="loading" class="loading-spinner-sm"></span>
                        {{ submitText }}
                    </button>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from './Modal.vue'

export default {
    name: 'FormModal',
    components: { Modal },
    emits: ['update:show', 'submit', 'cancel', 'open', 'close', 'closed'],
    props: {
        show: {
            type: Boolean,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        subtitle: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        size: {
            type: String,
            default: 'lg',
            validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
        },
        variant: {
            type: String,
            default: 'default',
            validator: (value) => ['default', 'danger', 'warning', 'success', 'info'].includes(value)
        },
        loading: {
            type: Boolean,
            default: false
        },
        submitText: {
            type: String,
            default: 'Save'
        },
        cancelText: {
            type: String,
            default: 'Cancel'
        },
        submitButtonVariant: {
            type: String,
            default: 'primary',
            validator: (value) => ['primary', 'secondary', 'danger', 'warning', 'success', 'info'].includes(value)
        },
        canSubmit: {
            type: Boolean,
            default: true
        },
        errors: {
            type: Object,
            default: () => ({})
        },
        persistent: {
            type: Boolean,
            default: false
        },
        scrollable: {
            type: Boolean,
            default: true
        }
    },
    computed: {
        submitButtonClass() {
            return `btn-${this.submitButtonVariant}`
        },

        hasErrors() {
            return Object.keys(this.errors).length > 0
        },

        flattenedErrors() {
            const errors = []
            for (const field in this.errors) {
                if (Array.isArray(this.errors[field])) {
                    errors.push(...this.errors[field])
                } else {
                    errors.push(this.errors[field])
                }
            }
            return errors
        }
    },
    methods: {
        handleSubmit() {
            this.$emit('submit')
        },

        handleCancel() {
            this.$emit('cancel')
        }
    }
}
</script>

<style scoped>
.form-modal-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-description {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 8px;
}

.form-errors {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 8px;
}

.error-header {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #dc2626;
    font-weight: 500;
    margin-bottom: 8px;
}

.error-header i {
    font-size: 1rem;
}

.error-list {
    margin: 0;
    padding-left: 20px;
    color: #dc2626;
}

.error-list li {
    margin-bottom: 4px;
    font-size: 0.875rem;
}

.form-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-left: auto;
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
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .form-description {
        color: #9ca3af;
    }

    .form-errors {
        background: #1f1f1f;
        border-color: #374151;
    }

    .error-header {
        color: #f87171;
    }

    .error-list {
        color: #f87171;
    }
}
</style>