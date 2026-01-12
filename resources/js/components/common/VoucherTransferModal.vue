<template>
    <FormModal
        :show="show"
        @update:show="$emit('update:show', $event)"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        :loading="loading"
        :errors="errors"
        :can-submit="isFormValid"
        submit-text="Transfer Voucher"
        submit-button-variant="primary"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @closed="resetForm"
        size="lg"
    >
        <div class="voucher-transfer-form">
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
                            <label>Current Owner:</label>
                            <span>{{ voucher.customer?.name || 'Unassigned' }}</span>
                        </div>
                        <div class="info-item">
                            <label>Expires:</label>
                            <span>{{ formatDate(voucher.expires_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Details -->
            <div class="form-section">
                <h4 class="section-title">Transfer Details</h4>
                
                <div class="form-group">
                    <label for="transfer-type">Transfer Type *</label>
                    <select
                        id="transfer-type"
                        v-model="form.transfer_type"
                        class="form-select"
                        :class="{ 'error': errors.transfer_type }"
                        required
                        @change="handleTransferTypeChange"
                    >
                        <option value="">Select Transfer Type</option>
                        <option value="customer">Transfer to Customer</option>
                        <option value="unassign">Unassign from Customer</option>
                        <option value="admin">Administrative Transfer</option>
                    </select>
                    <span v-if="errors.transfer_type" class="error-message">{{ errors.transfer_type[0] }}</span>
                </div>

                <!-- Customer Selection (only for customer transfers) -->
                <div v-if="form.transfer_type === 'customer'" class="form-group">
                    <label for="customer-search">New Customer *</label>
                    <div class="customer-search">
                        <input
                            id="customer-search"
                            v-model="customerSearchQuery"
                            type="text"
                            class="form-input"
                            :class="{ 'error': errors.new_customer_id }"
                            placeholder="Search customer by name, email, or phone..."
                            @input="searchCustomers"
                        />
                        <div v-if="customerSearchResults.length > 0" class="search-results">
                            <div
                                v-for="customer in customerSearchResults"
                                :key="customer.id"
                                class="search-result-item"
                                @click="selectCustomer(customer)"
                            >
                                <div class="customer-info">
                                    <strong>{{ customer.name }}</strong>
                                    <span>{{ customer.email || customer.phone }}</span>
                                    <small>{{ customer.location?.region }}, {{ customer.location?.district }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="selectedCustomer" class="selected-customer">
                        <div class="customer-card">
                            <div class="customer-details">
                                <strong>{{ selectedCustomer.name }}</strong>
                                <span>{{ selectedCustomer.email || selectedCustomer.phone }}</span>
                                <small>{{ selectedCustomer.location?.region }}, {{ selectedCustomer.location?.district }}</small>
                            </div>
                            <button
                                type="button"
                                @click="clearCustomer"
                                class="btn btn-sm btn-outline"
                            >
                                Change
                            </button>
                        </div>
                    </div>
                    <span v-if="errors.new_customer_id" class="error-message">{{ errors.new_customer_id[0] }}</span>
                </div>

                <!-- Transfer Reason -->
                <div class="form-group">
                    <label for="transfer-reason">Reason for Transfer *</label>
                    <select
                        id="transfer-reason"
                        v-model="form.reason"
                        class="form-select"
                        :class="{ 'error': errors.reason }"
                        required
                    >
                        <option value="">Select Reason</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="account_change">Account Change</option>
                        <option value="error_correction">Error Correction</option>
                        <option value="administrative">Administrative</option>
                        <option value="refund_replacement">Refund/Replacement</option>
                        <option value="other">Other</option>
                    </select>
                    <span v-if="errors.reason" class="error-message">{{ errors.reason[0] }}</span>
                </div>

                <!-- Transfer Notes -->
                <div class="form-group">
                    <label for="transfer-notes">Notes</label>
                    <textarea
                        id="transfer-notes"
                        v-model="form.notes"
                        class="form-textarea"
                        :class="{ 'error': errors.notes }"
                        rows="3"
                        placeholder="Additional notes about this transfer..."
                    ></textarea>
                    <span v-if="errors.notes" class="error-message">{{ errors.notes[0] }}</span>
                </div>

                <!-- Notification Options -->
                <div class="form-group">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.notify_old_customer"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Notify current customer about transfer</span>
                        </label>
                        
                        <label v-if="form.transfer_type === 'customer'" class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.notify_new_customer"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Notify new customer about voucher</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Transfer Confirmation -->
            <div class="form-section">
                <h4 class="section-title">Transfer Confirmation</h4>
                
                <div class="transfer-summary">
                    <div class="summary-item">
                        <strong>Action:</strong>
                        <span>{{ getTransferSummary() }}</span>
                    </div>
                    
                    <div v-if="form.transfer_type === 'customer' && selectedCustomer" class="summary-item">
                        <strong>New Owner:</strong>
                        <span>{{ selectedCustomer.name }} ({{ selectedCustomer.email || selectedCustomer.phone }})</span>
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
                        <p>This action will create an audit trail and cannot be undone. The voucher's ownership will be permanently changed.</p>
                    </div>
                </div>
            </div>
        </div>
    </FormModal>
</template>

<script>
import FormModal from './FormModal.vue'
import { debounce } from 'lodash-es'

export default {
    name: 'VoucherTransferModal',
    components: { FormModal },
    emits: ['update:show', 'transferred'],
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
                transfer_type: '',
                new_customer_id: '',
                reason: '',
                notes: '',
                notify_old_customer: true,
                notify_new_customer: true
            },
            customerSearchQuery: '',
            customerSearchResults: [],
            selectedCustomer: null
        }
    },
    computed: {
        modalTitle() {
            return this.voucher ? `Transfer Voucher ${this.voucher.code}` : 'Transfer Voucher'
        },

        modalSubtitle() {
            return this.voucher ? `Value: ${this.formatCurrency(this.voucher.amount)} | Duration: ${this.voucher.duration_hours}h` : ''
        },

        isFormValid() {
            const baseValid = this.form.transfer_type && this.form.reason

            if (this.form.transfer_type === 'customer') {
                return baseValid && this.form.new_customer_id
            }

            return baseValid
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.resetForm()
            }
        }
    },
    methods: {
        resetForm() {
            this.form = {
                transfer_type: '',
                new_customer_id: '',
                reason: '',
                notes: '',
                notify_old_customer: true,
                notify_new_customer: true
            }
            this.errors = {}
            this.customerSearchQuery = ''
            this.customerSearchResults = []
            this.selectedCustomer = null
        },

        handleTransferTypeChange() {
            if (this.form.transfer_type !== 'customer') {
                this.clearCustomer()
            }
        },

        searchCustomers: debounce(async function() {
            if (this.customerSearchQuery.length < 2) {
                this.customerSearchResults = []
                return
            }

            try {
                const response = await this.$http.get('/api/customers/search', {
                    params: { q: this.customerSearchQuery }
                })
                this.customerSearchResults = response.data.data
            } catch (error) {
                console.error('Error searching customers:', error)
                this.customerSearchResults = []
            }
        }, 300),

        selectCustomer(customer) {
            this.selectedCustomer = customer
            this.form.new_customer_id = customer.id
            this.customerSearchQuery = customer.name
            this.customerSearchResults = []
        },

        clearCustomer() {
            this.selectedCustomer = null
            this.form.new_customer_id = ''
            this.customerSearchQuery = ''
            this.customerSearchResults = []
        },

        getTransferSummary() {
            switch (this.form.transfer_type) {
                case 'customer':
                    return 'Transfer voucher to another customer'
                case 'unassign':
                    return 'Remove customer assignment from voucher'
                case 'admin':
                    return 'Administrative transfer'
                default:
                    return 'Select transfer type'
            }
        },

        getReasonText() {
            const reasons = {
                customer_request: 'Customer Request',
                account_change: 'Account Change',
                error_correction: 'Error Correction',
                administrative: 'Administrative',
                refund_replacement: 'Refund/Replacement',
                other: 'Other'
            }
            return reasons[this.form.reason] || 'Select reason'
        },

        async handleSubmit() {
            this.loading = true
            this.errors = {}

            try {
                const response = await this.$http.post(`/api/vouchers/${this.voucher.id}/transfer`, this.form)
                
                this.$emit('transferred', response.data.data)
                this.$emit('update:show', false)
                
                this.$toast.success('Voucher transferred successfully')
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                } else {
                    this.$toast.error('Failed to transfer voucher')
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
.voucher-transfer-form {
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

.error-message {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
}

.customer-search {
    position: relative;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 10;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.search-result-item {
    padding: 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
}

.search-result-item:hover {
    background: #f9fafb;
}

.search-result-item:last-child {
    border-bottom: none;
}

.customer-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.customer-info strong {
    color: #374151;
}

.customer-info span {
    color: #6b7280;
    font-size: 0.875rem;
}

.customer-info small {
    color: #9ca3af;
    font-size: 0.8125rem;
}

.selected-customer {
    margin-top: 8px;
}

.customer-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.customer-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.customer-details strong {
    color: #374151;
}

.customer-details span {
    color: #6b7280;
    font-size: 0.875rem;
}

.customer-details small {
    color: #9ca3af;
    font-size: 0.8125rem;
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

.transfer-summary {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
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
    color: #1e40af;
    font-weight: 500;
}

.warning-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 16px;
}

.warning-notice i {
    color: #d97706;
    font-size: 1.25rem;
    margin-top: 2px;
}

.warning-notice strong {
    color: #92400e;
    font-weight: 600;
}

.warning-notice p {
    margin: 4px 0 0 0;
    color: #92400e;
    font-size: 0.95rem;
    line-height: 1.4;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8125rem;
}

.btn-outline {
    background: transparent;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.btn-outline:hover:not(:disabled) {
    background: #f9fafb;
    color: #374151;
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

    .voucher-info,
    .customer-card {
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

    .form-input,
    .form-select,
    .form-textarea {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
    }

    .search-results {
        background: #374151;
        border-color: #4b5563;
    }

    .search-result-item:hover {
        background: #4b5563;
    }

    .transfer-summary {
        background: #1e3a8a;
        border-color: #3b82f6;
    }

    .summary-item strong {
        color: #f9fafb;
    }

    .summary-item span {
        color: #93c5fd;
    }

    .warning-notice {
        background: #451a03;
        border-color: #92400e;
    }

    .warning-notice i {
        color: #fbbf24;
    }

    .warning-notice strong,
    .warning-notice p {
        color: #fbbf24;
    }
}
</style>