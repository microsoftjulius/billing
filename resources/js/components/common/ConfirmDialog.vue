<template>
    <Modal
        :show="visible"
        @update:show="handleVisibilityChange"
        :title="title"
        :subtitle="subtitle"
        :size="size"
        :variant="type"
        :closable="closable"
        :closeOnBackdrop="closeOnBackdrop"
        :closeOnEsc="closeOnEsc"
        :loading="loading"
        :confirm-text="confirmButtonText"
        :cancel-text="cancelButtonText"
        :show-footer="true"
        :prevent-close="loading"
        @confirm="handleConfirm"
        @cancel="handleCancel"
    >
        <div class="confirm-dialog-content">
            <div class="confirm-icon" :class="type">
                <component :is="iconComponent" :class="iconClass" />
            </div>

            <div class="confirm-message">
                <p v-if="message" class="message-text">{{ message }}</p>
                <div v-if="description" class="description-text">
                    {{ description }}
                </div>
                <slot name="content"></slot>
            </div>

            <div v-if="hasDetails" class="confirm-details">
                <div v-if="detailsTitle" class="details-title">{{ detailsTitle }}</div>
                <div v-if="detailsText" class="details-text">{{ detailsText }}</div>
                <slot name="details"></slot>
            </div>

            <div v-if="showInput" class="confirm-input">
                <label v-if="inputLabel" :for="inputId" class="input-label">{{ inputLabel }}</label>
                <input
                    :id="inputId"
                    v-model="inputValue"
                    :type="inputType"
                    :placeholder="inputPlaceholder"
                    :required="inputRequired"
                    class="form-input"
                    @keyup.enter="handleConfirm"
                />
                <div v-if="inputError" class="input-error">{{ inputError }}</div>
            </div>
        </div>

        <template v-if="$slots.footer" #footer>
            <slot name="footer"></slot>
        </template>
    </Modal>
</template>

<script>
import Modal from './Modal.vue'
import { CheckCircleIcon, ExclamationTriangleIcon, QuestionMarkCircleIcon, InformationCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'

export default {
    name: 'ConfirmDialog',
    components: { Modal },
    props: {
        visible: {
            type: Boolean,
            default: false
        },
        title: {
            type: String,
            default: 'Confirm Action'
        },
        subtitle: {
            type: String,
            default: ''
        },
        message: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        type: {
            type: String,
            default: 'warning',
            validator: value => ['info', 'success', 'warning', 'danger', 'question'].includes(value)
        },
        size: {
            type: String,
            default: 'md',
            validator: value => ['sm', 'md', 'lg', 'xl'].includes(value)
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
            default: 'Confirm'
        },
        cancelText: {
            type: String,
            default: 'Cancel'
        },
        confirmButtonVariant: {
            type: String,
            default: 'primary',
            validator: value => ['primary', 'secondary', 'danger', 'success', 'warning'].includes(value)
        },
        cancelButtonVariant: {
            type: String,
            default: 'secondary',
            validator: value => ['primary', 'secondary', 'danger', 'success', 'warning'].includes(value)
        },
        showCancelButton: {
            type: Boolean,
            default: true
        },
        showConfirmButton: {
            type: Boolean,
            default: true
        },
        detailsTitle: {
            type: String,
            default: ''
        },
        detailsText: {
            type: String,
            default: ''
        },
        showInput: {
            type: Boolean,
            default: false
        },
        inputLabel: {
            type: String,
            default: ''
        },
        inputPlaceholder: {
            type: String,
            default: ''
        },
        inputType: {
            type: String,
            default: 'text'
        },
        inputRequired: {
            type: Boolean,
            default: false
        },
        inputValueProp: {
            type: String,
            default: ''
        },
        validateInput: {
            type: Function,
            default: null
        }
    },
    data() {
        return {
            inputValue: this.inputValueProp,
            inputError: '',
            inputId: `confirm-input-${Math.random().toString(36).substr(2, 9)}`
        }
    },
    computed: {
        iconComponent() {
            const icons = {
                info: InformationCircleIcon,
                success: CheckCircleIcon,
                warning: ExclamationTriangleIcon,
                danger: ExclamationCircleIcon,
                question: QuestionMarkCircleIcon
            }
            return icons[this.type] || QuestionMarkCircleIcon
        },

        iconClass() {
            const classes = {
                info: 'text-blue-500',
                success: 'text-green-500',
                warning: 'text-yellow-500',
                danger: 'text-red-500',
                question: 'text-gray-500'
            }
            return classes[this.type] || 'text-gray-500'
        },

        confirmButtonText() {
            return this.showConfirmButton ? this.confirmText : null
        },

        cancelButtonText() {
            return this.showCancelButton ? this.cancelText : null
        },

        hasDetails() {
            return this.detailsTitle || this.detailsText || this.$slots.details
        }
    },
    watch: {
        inputValueProp(newVal) {
            this.inputValue = newVal
        },

        visible(newVal) {
            if (newVal) {
                this.resetInput()
                this.$emit('open')
            } else {
                this.$emit('close')
            }
        }
    },
    methods: {
        handleConfirm() {
            if (this.showInput && this.validateInput) {
                const validation = this.validateInput(this.inputValue)
                if (validation !== true) {
                    this.inputError = validation || 'Invalid input'
                    return
                }
            }

            this.$emit('confirm', this.inputValue)
        },

        handleCancel() {
            this.$emit('cancel')
            this.close()
        },

        handleVisibilityChange(value) {
            if (!value) {
                this.close()
            }
        },

        close() {
            this.$emit('update:visible', false)
            this.$emit('closed')
        },

        resetInput() {
            this.inputValue = this.inputValueProp
            this.inputError = ''
        }
    }
}
</script>

<style scoped>
.confirm-dialog-content {
    text-align: center;
    padding: 8px 0;
}

.confirm-icon {
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 50%;
}

.confirm-icon.info {
    background: #dbeafe;
}

.confirm-icon.success {
    background: #d1fae5;
}

.confirm-icon.warning {
    background: #fef3c7;
}

.confirm-icon.danger {
    background: #fee2e2;
}

.confirm-icon.question {
    background: #f3f4f6;
}

.confirm-icon svg {
    width: 32px;
    height: 32px;
}

.confirm-message {
    margin-bottom: 24px;
}

.message-text {
    font-size: 1.125rem;
    font-weight: 500;
    color: #111827;
    margin: 0 0 8px;
    line-height: 1.5;
}

.description-text {
    font-size: 0.95rem;
    color: #6b7280;
    line-height: 1.5;
}

.confirm-details {
    background: #f9fafb;
    border-radius: 8px;
    padding: 16px;
    margin: 20px 0;
    text-align: left;
    border: 1px solid #e5e7eb;
}

.details-title {
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.details-text {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.5;
}

.confirm-input {
    margin: 24px 0;
    text-align: left;
}

.input-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
    font-size: 0.95rem;
}

.form-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input::placeholder {
    color: #9ca3af;
}

.input-error {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
}

/* Heroicons styles (fallback if not using heroicons) */
.text-blue-500 { color: #3b82f6; }
.text-green-500 { color: #10b981; }
.text-yellow-500 { color: #f59e0b; }
.text-red-500 { color: #ef4444; }
.text-gray-500 { color: #6b7280; }
</style>
