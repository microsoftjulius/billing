<template>
    <FormModal
        :show="show"
        @update:show="$emit('update:show', $event)"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        :loading="loading"
        :errors="errors"
        :can-submit="isFormValid"
        submit-text="Process Refund"
        submit-button-variant="warning"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @closed="resetForm"
        size="lg"
        variant="warning"
    >
        <div class="voucher-refund-form">
            <!-- Voucher Information -->
            <div class="form-section">
                <h4 class="section-title">Voucher Information</h4>
                
                <div v-if="voucher" class="voucher-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Voucher Code:</label>
                            <span class="voucher-code">{{ voucher.code }}</span>
                        </div>
                        <div class="info-item">
                            <label>Value:</label>
                            <span>{{ formatCurrency(voucher.amount) }}</span>
                        </div>
                        <div class="info-item">
                            <label>Duration:</label>
                            <span>{{ voucher.duration_hours }} hours</span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span class="status-badge" :class="voucher.status">{{ voucher.status }}</span>
                        </div>
                        <div class="info-item">
                            <label>Customer:</label>
                            <span>{{ voucher.customer?.name || 'Unassigned' }}</span>
                        </div>
                        <div class="info-item">
                            <label>Activated:</label>
                            <span>{{ formatDate(voucher.activated_at) }}</span>
                        </div>
                        <div class="info-item">
                            <label>Expires:</label>
                            <span>{{ formatDate(voucher.expires_at) }}</span>
                        </div>
                        <div class="info-item">
                            <label>Usage:</label>
                            <span>{{ getUsageInfo() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Refund Details -->
            <div class="form-section">
                <h4 class="section-title">Refund Details</h4>
                
                <div class="form-group">
                    <label for="refund-type">Refund Type *</label>
                    <select
                        id="refund-type"
                        v-model="form.refund_type"
                        class="form-select"
                        :class="{ 'error': errors.refund_type }"
                        required
                        @change="calculateRefundAmount"
                    >
                        <option value="">Select Refund Type</option>
                        <option value="full">Full Refund</option>
                        <option value="partial">Partial Refund</option>
                        <option value="prorated">Pro-rated Refund (Based on Usage)</option>
                    </select>
                    <span v-if="errors.refund_type" class="error-message">{{ errors.refund_type[0] }}</span>
                </div>

                <div class="form-group">
                    <label for="refund-amount">Refund Amount *</label>
                    <div class="input-group">
                        <span class="input-prefix">UGX</span>
                        <input
                            id="refund-amount"
                            v-model.number="form.refund_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            :max="voucher?.amount || 0"
                            class="form-input"
                            :class="{ 'error': errors.refund_amount }"
                            :readonly="form.refund_type === 'prorated'"
                            placeholder="0.00"
                            required
                        />
                    </div>
                    <div class="amount-info">
                        <small>Original Amount: {{ formatCurrency(voucher?.amount || 0) }}</small>
                        <small v-if="form.refund_type === 'prorated'">
                            Calculated based on {{ getUsagePercentage() }}% usage
                        </small>
                    </div>
                    <span v-if="errors.refund_amount" class="error-message">{{ errors.refund_amount[0] }}</span>
                </div>

                <div class="form-group">
                    <label for="refund-reason">Reason for Refund *</label>
                    <select
                        id="refund-reason"
                        v-model="form.reason"
                        class="form-select"
                        :class="{ 'error': errors.reason }"
                        required
                    >
                        <option value="">Select Reason</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="service_issue">Service Issue</option>
                        <option value="technical_problem">Technical Problem</option>
                        <option value="duplicate_purchase">Duplicate Purchase</option>
                        <option value="unused_voucher">Unused Voucher</option>
                        <option value="error_correction">Error Correction</option>
                        <option value="goodwill">Goodwill Gesture</option>
                        <option value="other">Other</option>
                    </select>
                    <span v-if="errors.reason" class="error-message">{{ errors.reason[0] }}</span>
                </div>

                <div class="form-group">
                    <label for="refund-notes">Notes</label>
                    <textarea
                        id="refund-notes"
                        v-model="form.notes"
                        class="form-textarea"
                        :class="{ 'error': errors.notes }"
                        rows="3"
                        placeholder="Additional notes about this refund..."
                    ></textarea>
                    <span v-if="errors.notes" class="error-message">{{ errors.notes[0] }}</span>
                </div>
            </div>

            <!-- Refund Method -->
            <div class="form-section">
                <h4 class="section-title">Refund Method</h4>
                
                <div class="form-group">
                    <label for="refund-method">Refund Method *</label>
                    <select
                        id="refund-method"
                        v-model="form.refund_method"
                        class="form-select"
                        :class="{ 'error': errors.refund_method }"
                        required
                    >
                        <option value="">Select Refund Method</option>
                        <option value="original_payment">Original Payment Method</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="credit">Account Credit</option>
                    </select>
                    <span v-if="errors.refund_method" class="error-message">{{ errors.refund_method[0] }}</span>
                </div>

                <!-- Payment Details (conditional) -->
                <div v-if="form.refund_method === 'mobile_money'" class="form-group">
                    <label for="mobile-number">Mobile Money Number *</label>
                    <input
                        id="mobile-number"
                        v-model="form.mobile_number"
                        type="tel"
                        class="form-input"
                        :class="{ 'error': errors.mobile_number }"
                        placeholder="+256 XXX XXX XXX"
                        required
                    />
                    <span v-if="errors.mobile_number" class="error-message">{{ errors.mobile_number[0] }}</span>
                </div>

                <div v-if="form.refund_method === 'bank_transfer'" class="form-group">
                    <label for="bank-details">Bank Account Details *</label>
                    <textarea
                        id="bank-details"
                        v-model="form.bank_details"
                        class="form-textarea"
                        :class="{ 'error': errors.bank_details }"
                        rows="3"
                        placeholder="Bank name, account number, account holder name..."
                        required
                    ></textarea>
                    <span v-if="errors.bank_details" class="error-message">{{ errors.bank_details[0] }}</span>
                </div>

                <!-- Notification Options -->
                <div class="form-group">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.notify_customer"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Notify customer about refund</span>
                        </label>
                        
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.deactivate_voucher"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Deactivate voucher after refund</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Refund Summary -->
            <div class="form-section">
                <h4 class="section-title">Refund Summary</h4>
                
                <div class="refund-summary">
                    <div class="summary-item">
                        <strong>Refund Type:</strong>
                        <span>{{ getRefundTypeText() }}</span>
                    </div>
                    
                    <div class="summary-item">
                        <strong>Refund Amount:</strong>
                        <span class="amount">{{ formatCurrency(form.refund_amount || 0) }}</span>
                    </div>
                    
                    <div class="summary-item">
                        <strong>Refund Method:</strong>
                        <span>{{ getRefundMethodText() }}</span>
                    </div>
                    
                    <div class="summary-item">
                        <strong>Reason:</strong>
                        <span>{{ getReasonText() }}</span>
                    </div>
                </div>

                <div class="warning-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Important:</strong>
                        <p>This refund will be processed immediately and cannot be undone. Please ensure all details are correct before proceeding.</p>
                    </div>
                </div>
            </div>
        </div>
    </FormModal>
</template>

<script>
import FormModal from './FormModal.vue'

export default {
    name: 'VoucherRefundModal',
    components: { FormModal },
    emits: ['update:show', 'refunded'],
    props: {
        show: {
            type: Boolean,
            required: true
        },
        voucher: {
            type: Object,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            errors: {},
            form: {
                refund_type: '',
                refund_amount: 0,
                reason: '',
                notes: '',
                refund_method: '',
                mobile_number: '',
                bank_details: '',
                notify_customer: true,
                deactivate_voucher: true
            }
        }
    },
    computed: {
        modalTitle() {
            return this.voucher ? `Refund Voucher ${this.voucher.code}` : 'Refund Voucher'
        },

        modalSubtitle() {
            return this.voucher ? `Value: ${this.formatCurrency(this.voucher.amount)} | Customer: ${this.voucher.customer?.name || 'Unassigned'}` : ''
        },

        isFormValid() {
            const baseValid = this.form.refund_type && 
                             this.form.refund_amount > 0 && 
                             this.form.reason && 
                             this.form.refund_method

            if (this.form.refund_method === 'mobile_money') {
                return baseValid && this.form.mobile_number
            }

            if (this.form.refund_method === 'bank_transfer') {
                return baseValid && this.form.bank_details
            }

            return baseValid
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.resetForm()
            }
        },

        voucher: {
            handler(newVoucher) {
                if (newVoucher) {
                    this.calculateRefundAmount()
                }
            },
            immediate: true
        }
    },
    methods: {
        resetForm() {
            this.form = {
                refund_type: '',
                refund_amount: 0,
                reason: '',
                notes: '',
                refund_method: '',
                mobile_number: '',
                bank_details: '',
                notify_customer: true,
                deactivate_voucher: true
            }
            this.errors = {}
        },

        calculateRefundAmount() {
            if (!this.voucher || !this.form.refund_type) return

            switch (this.form.refund_type) {
                case 'full':
                    this.form.refund_amount = this.voucher.amount
                    break
                case 'partial':
                    this.form.refund_amount = 0
                    break
                case 'prorated':
                    const usagePercentage = this.getUsagePercentage()
                    const remainingPercentage = 100 - usagePercentage
                    this.form.refund_amount = (this.voucher.amount * remainingPercentage) / 100
                    break
            }
        },

        getUsageInfo() {
            if (!this.voucher) return 'N/A'

            if (this.voucher.status === 'unused') {
                return 'Not used'
            }

            if (this.voucher.status === 'active' && this.voucher.activated_at) {
                const activatedAt = new Date(this.voucher.activated_at)
                const now = new Date()
                const expiresAt = new Date(this.voucher.expires_at)
                
                const totalDuration = expiresAt.getTime() - activatedAt.getTime()
                const usedDuration = now.getTime() - activatedAt.getTime()
                const usagePercentage = Math.min(100, Math.max(0, (usedDuration / totalDuration) * 100))
                
                return `${usagePercentage.toFixed(1)}% used`
            }

            if (this.voucher.status === 'expired') {
                return 'Fully used/expired'
            }

            return 'Unknown'
        },

        getUsagePercentage() {
            if (!this.voucher || this.voucher.status === 'unused') return 0

            if (this.voucher.status === 'expired') return 100

            if (this.voucher.status === 'active' && this.voucher.activated_at) {
                const activatedAt = new Date(this.voucher.activated_at)
                const now = new Date()
                const expiresAt = new Date(this.voucher.expires_at)
                
                const totalDuration = expiresAt.getTime() - activatedAt.getTime()
                const usedDuration = now.getTime() - activatedAt.getTime()
                
                return Math.min(100, Math.max(0, (usedDuration / totalDuration) * 100))
            }

            return 0
        },

        getRefundTypeText() {
            const types = {
                full: 'Full Refund',
                partial: 'Partial Refund',
                prorated: 'Pro-rated Refund'
            }
            return types[this.form.refund_type] || 'Select refund type'
        },

        getRefundMethodText() {
            const methods = {
                original_payment: 'Original Payment Method',
                mobile_money: 'Mobile Money',
                bank_transfer: 'Bank Transfer',
                cash: 'Cash',
                credit: 'Account Credit'
            }
            return methods[this.form.refund_method] || 'Select refund method'
        },

        getReasonText() {
            const reasons = {
                customer_request: 'Customer Request',
                service_issue: 'Service Issue',
                technical_problem: 'Technical Problem',
                duplicate_purchase: 'Duplicate Purchase',
                unused_voucher: 'Unused Voucher',
                error_correction: 'Error Correction',
                goodwill: 'Goodwill Gesture',
                other: 'Other'
            }
            return reasons[this.form.reason] || 'Select reason'
        },

        async handleSubmit() {
            this.loading = true
            this.errors = {}

            try {
                const response = await this.$http.post(`/api/vouchers/${this.voucher.id}/refund`, this.form)
                
                this.$emit('refunded', response.data.data)
                this.$emit('update:show', false)
                
                this.$toast.success('Voucher refund processed successfully')
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                } else {
                    this.$toast.error('Failed to process refund')
                }
            } finally {
                this.loading = false
            }
        },

        handleCancel() {
            this.$emit('update:show', false)
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-UG', {
                style: 'currency',
                currency: 'UGX'
            }).format(amount)
        },

        formatDate(date) {
            if (!date) return 'N/A'
            return new Date(date).toLocaleString()
        }
    }
}
</script>

<style scoped>
.voucher-refund-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-section {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}

.section-title {
    margin: 0 0 16px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 8px;
}

.voucher-info {
    background: #f9fafb;
    border-radius: 6px;
    padding: 16px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.info-item span {
    color: #374151;
    font-weight: 500;
}

.voucher-code {
    font-family: 'Courier New', monospace;
    background: #e5e7eb;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.875rem;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-badge.active {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.unused {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.expired {
    background: #fee2e2;
    color: #991b1b;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.95rem;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-prefix {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-right: none;
    border-radius: 6px 0 0 6px;
    padding: 10px 12px;
    font-size: 0.95rem;
    color: #6b7280;
    font-weight: 500;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.input-group .form-input {
    border-radius: 0 6px 6px 0;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error,
.form-select.error,
.form-textarea.error {
    border-color: #ef4444;
}

.form-input:read-only {
    background: #f9fafb;
    color: #6b7280;
}

.amount-info {
    display: flex;
    justify-content: space-between;
    margin-top: 4px;
}

.amount-info small {
    color: #6b7280;
    font-size: 0.8125rem;
}

.error-message {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-input {
    width: 16px;
    height: 16px;
    accent-color: #3b82f6;
}

.checkbox-text {
    font-size: 0.95rem;
    color: #374151;
}

.refund-summary {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.summary-item:last-child {
    margin-bottom: 0;
}

.summary-item strong {
    color: #374151;
    font-weight: 500;
}

.summary-item span {
    color: #92400e;
    font-weight: 500;
}

.summary-item .amount {
    font-size: 1.1rem;
    font-weight: 600;
}

.warning-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 16px;
}

.warning-notice i {
    color: #dc2626;
    font-size: 1.25rem;
    margin-top: 2px;
}

.warning-notice strong {
    color: #991b1b;
    font-weight: 600;
}

.warning-notice p {
    margin: 4px 0 0 0;
    color: #991b1b;
    font-size: 0.95rem;
    line-height: 1.4;
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .form-section {
        border-color: #374151;
        background: #1f2937;
    }

    .section-title {
        color: #f9fafb;
        border-color: #374151;
    }

    .voucher-info {
        background: #374151;
        border-color: #4b5563;
    }

    .voucher-code {
        background: #4b5563;
        color: #f9fafb;
    }

    .form-group label,
    .checkbox-text {
        color: #f3f4f6;
    }

    .input-prefix {
        background: #374151;
        border-color: #4b5563;
        color: #d1d5db;
    }

    .form-input,
    .form-select,
    .form-textarea {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
    }

    .form-input:read-only {
        background: #4b5563;
        color: #9ca3af;
    }

    .amount-info small {
        color: #9ca3af;
    }

    .refund-summary {
        background: #451a03;
        border-color: #92400e;
    }

    .summary-item strong {
        color: #f9fafb;
    }

    .summary-item span {
        color: #fbbf24;
    }

    .warning-notice {
        background: #1f1f1f;
        border-color: #374151;
    }

    .warning-notice i {
        color: #f87171;
    }

    .warning-notice strong,
    .warning-notice p {
        color: #f87171;
    }
}
</style>