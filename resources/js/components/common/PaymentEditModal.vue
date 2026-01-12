<template>
    <FormModal
        :show="show"
        @update:show="$emit('update:show', $event)"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        :loading="loading"
        :errors="errors"
        :can-submit="isFormValid"
        submit-text="Update Payment"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @closed="resetForm"
        size="lg"
    >
        <div class="payment-edit-form">
            <!-- Payment Information -->
            <div class="form-section">
                <h4 class="section-title">Payment Information</h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="payment-amount">Amount *</label>
                        <div class="input-group">
                            <span class="input-prefix">UGX</span>
                            <input
                                id="payment-amount"
                                v-model.number="form.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                :class="{ 'error': errors.amount }"
                                placeholder="0.00"
                                required
                            />
                        </div>
                        <span v-if="errors.amount" class="error-message">{{ errors.amount[0] }}</span>
                    </div>

                    <div class="form-group">
                        <label for="payment-status">Status *</label>
                        <select
                            id="payment-status"
                            v-model="form.status"
                            class="form-select"
                            :class="{ 'error': errors.status }"
                            required
                        >
                            <option value="">Select Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                        <span v-if="errors.status" class="error-message">{{ errors.status[0] }}</span>
                    </div>

                    <div class="form-group">
                        <label for="payment-gateway">Payment Gateway</label>
                        <select
                            id="payment-gateway"
                            v-model="form.gateway_id"
                            class="form-select"
                            :class="{ 'error': errors.gateway_id }"
                        >
                            <option value="">Select Gateway</option>
                            <option 
                                v-for="gateway in availableGateways" 
                                :key="gateway.id" 
                                :value="gateway.id"
                            >
                                {{ gateway.name }}
                            </option>
                        </select>
                        <span v-if="errors.gateway_id" class="error-message">{{ errors.gateway_id[0] }}</span>
                    </div>

                    <div class="form-group">
                        <label for="gateway-transaction-id">Gateway Transaction ID</label>
                        <input
                            id="gateway-transaction-id"
                            v-model="form.gateway_transaction_id"
                            type="text"
                            class="form-input"
                            :class="{ 'error': errors.gateway_transaction_id }"
                            placeholder="External transaction ID"
                        />
                        <span v-if="errors.gateway_transaction_id" class="error-message">{{ errors.gateway_transaction_id[0] }}</span>
                    </div>

                    <div class="form-group">
                        <label for="gateway-reference">Gateway Reference</label>
                        <input
                            id="gateway-reference"
                            v-model="form.gateway_reference"
                            type="text"
                            class="form-input"
                            :class="{ 'error': errors.gateway_reference }"
                            placeholder="Gateway reference number"
                        />
                        <span v-if="errors.gateway_reference" class="error-message">{{ errors.gateway_reference[0] }}</span>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="form-section">
                <h4 class="section-title">Customer Information</h4>
                
                <div class="form-group">
                    <label for="customer-search">Customer</label>
                    <div class="customer-search">
                        <input
                            id="customer-search"
                            v-model="customerSearchQuery"
                            type="text"
                            class="form-input"
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
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="selectedCustomer" class="selected-customer">
                        <div class="customer-card">
                            <div class="customer-details">
                                <strong>{{ selectedCustomer.name }}</strong>
                                <span>{{ selectedCustomer.email || selectedCustomer.phone }}</span>
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
                </div>
            </div>

            <!-- Audit Information -->
            <div class="form-section">
                <h4 class="section-title">Audit Information</h4>
                
                <div class="form-group">
                    <label for="edit-reason">Reason for Edit *</label>
                    <select
                        id="edit-reason"
                        v-model="form.edit_reason"
                        class="form-select"
                        :class="{ 'error': errors.edit_reason }"
                        required
                    >
                        <option value="">Select Reason</option>
                        <option value="correction">Data Correction</option>
                        <option value="status_update">Status Update</option>
                        <option value="gateway_sync">Gateway Synchronization</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="administrative">Administrative</option>
                        <option value="other">Other</option>
                    </select>
                    <span v-if="errors.edit_reason" class="error-message">{{ errors.edit_reason[0] }}</span>
                </div>

                <div class="form-group">
                    <label for="edit-notes">Notes</label>
                    <textarea
                        id="edit-notes"
                        v-model="form.edit_notes"
                        class="form-textarea"
                        :class="{ 'error': errors.edit_notes }"
                        rows="3"
                        placeholder="Additional notes about this edit..."
                    ></textarea>
                    <span v-if="errors.edit_notes" class="error-message">{{ errors.edit_notes[0] }}</span>
                </div>
            </div>

            <!-- Original Payment Info (Read-only) -->
            <div v-if="payment" class="form-section">
                <h4 class="section-title">Original Payment Details</h4>
                
                <div class="readonly-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Original Amount:</label>
                            <span>{{ formatCurrency(payment.amount) }}</span>
                        </div>
                        <div class="info-item">
                            <label>Original Status:</label>
                            <span class="status-badge" :class="payment.status">{{ payment.status }}</span>
                        </div>
                        <div class="info-item">
                            <label>Created:</label>
                            <span>{{ formatDate(payment.created_at) }}</span>
                        </div>
                        <div class="info-item">
                            <label>Last Updated:</label>
                            <span>{{ formatDate(payment.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #additional-actions>
            <button
                v-if="payment && canRefund"
                type="button"
                @click="showRefundConfirmation = true"
                class="btn btn-warning"
                :disabled="loading"
            >
                <i class="fas fa-undo"></i>
                Refund
            </button>
        </template>
    </FormModal>

    <!-- Refund Confirmation -->
    <ConfirmDialog
        :visible="showRefundConfirmation"
        @update:visible="showRefundConfirmation = $event"
        title="Confirm Refund"
        message="Are you sure you want to refund this payment?"
        :description="`This will refund ${formatCurrency(payment?.amount)} to the customer.`"
        type="warning"
        confirm-text="Refund Payment"
        @confirm="handleRefund"
    />
</template>

<script>
import FormModal from './FormModal.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import { debounce } from 'lodash-es'

export default {
    name: 'PaymentEditModal',
    components: { FormModal, ConfirmDialog },
    emits: ['update:show', 'updated', 'refunded'],
    props: {
        show: {
            type: Boolean,
            required: true
        },
        payment: {
            type: Object,
            default: null
        },
        availableGateways: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            loading: false,
            errors: {},
            form: {
                amount: 0,
                status: '',
                gateway_id: '',
                gateway_transaction_id: '',
                gateway_reference: '',
                customer_id: '',
                edit_reason: '',
                edit_notes: ''
            },
            customerSearchQuery: '',
            customerSearchResults: [],
            selectedCustomer: null,
            showRefundConfirmation: false
        }
    },
    computed: {
        modalTitle() {
            return this.payment ? `Edit Payment #${this.payment.id}` : 'Edit Payment'
        },

        modalSubtitle() {
            return this.payment ? `Transaction ID: ${this.payment.gateway_transaction_id || 'N/A'}` : ''
        },

        isFormValid() {
            return this.form.amount > 0 && 
                   this.form.status && 
                   this.form.edit_reason &&
                   this.form.customer_id
        },

        canRefund() {
            return this.payment && 
                   ['completed', 'processing'].includes(this.payment.status) &&
                   this.payment.amount > 0
        }
    },
    watch: {
        payment: {
            handler(newPayment) {
                if (newPayment) {
                    this.populateForm(newPayment)
                }
            },
            immediate: true
        },

        show(newVal) {
            if (newVal && this.payment) {
                this.populateForm(this.payment)
            }
        }
    },
    methods: {
        populateForm(payment) {
            this.form = {
                amount: payment.amount,
                status: payment.status,
                gateway_id: payment.gateway_id,
                gateway_transaction_id: payment.gateway_transaction_id || '',
                gateway_reference: payment.gateway_reference || '',
                customer_id: payment.customer_id,
                edit_reason: '',
                edit_notes: ''
            }

            if (payment.customer) {
                this.selectedCustomer = payment.customer
                this.customerSearchQuery = payment.customer.name
            }
        },

        resetForm() {
            this.form = {
                amount: 0,
                status: '',
                gateway_id: '',
                gateway_transaction_id: '',
                gateway_reference: '',
                customer_id: '',
                edit_reason: '',
                edit_notes: ''
            }
            this.errors = {}
            this.customerSearchQuery = ''
            this.customerSearchResults = []
            this.selectedCustomer = null
            this.showRefundConfirmation = false
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
            this.form.customer_id = customer.id
            this.customerSearchQuery = customer.name
            this.customerSearchResults = []
        },

        clearCustomer() {
            this.selectedCustomer = null
            this.form.customer_id = ''
            this.customerSearchQuery = ''
            this.customerSearchResults = []
        },

        async handleSubmit() {
            this.loading = true
            this.errors = {}

            try {
                const response = await this.$http.put(`/api/payments/${this.payment.id}`, this.form)
                
                this.$emit('updated', response.data.data)
                this.$emit('update:show', false)
                
                this.$toast.success('Payment updated successfully')
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                } else {
                    this.$toast.error('Failed to update payment')
                }
            } finally {
                this.loading = false
            }
        },

        async handleRefund() {
            this.loading = true

            try {
                const response = await this.$http.post(`/api/payments/${this.payment.id}/refund`, {
                    reason: 'Manual refund via admin panel',
                    notes: this.form.edit_notes
                })
                
                this.$emit('refunded', response.data.data)
                this.$emit('update:show', false)
                
                this.$toast.success('Payment refunded successfully')
            } catch (error) {
                this.$toast.error('Failed to refund payment')
            } finally {
                this.loading = false
                this.showRefundConfirmation = false
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
            return new Date(date).toLocaleString()
        }
    }
}
</script>

<style scoped>
.payment-edit-form {
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

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
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

.readonly-info {
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

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.processing {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.completed {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.refunded {
    background: #f3f4f6;
    color: #374151;
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

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover:not(:disabled) {
    background: #d97706;
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

    .form-group label {
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

    .search-results {
        background: #374151;
        border-color: #4b5563;
    }

    .search-result-item:hover {
        background: #4b5563;
    }

    .customer-card,
    .readonly-info {
        background: #374151;
        border-color: #4b5563;
    }

    .customer-details strong,
    .info-item span {
        color: #f9fafb;
    }

    .customer-details span,
    .customer-info span,
    .info-item label {
        color: #d1d5db;
    }
}
</style>