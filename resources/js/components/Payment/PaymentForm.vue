<template>
    <div class="payment-form">
        <div v-if="loading" class="loading-overlay">
            <div class="spinner"></div>
            <p>Processing payment...</p>
        </div>

        <div v-else-if="paymentInitiated" class="payment-initiated">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>Payment Initiated!</h3>
            <p>Please check your phone to confirm the payment</p>
            <div class="payment-details">
                <p><strong>Amount:</strong> UGX {{ formatAmount(amount) }}</p>
                <p><strong>Transaction ID:</strong> {{ transactionId }}</p>
                <p><strong>Phone:</strong> {{ phone }}</p>
            </div>
            <button @click="checkPaymentStatus" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Check Payment Status
            </button>
        </div>

        <form v-else @submit.prevent="initiatePayment">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">+256</span>
                    </div>
                    <input
                        v-model="phone"
                        type="tel"
                        id="phone"
                        required
                        class="form-control"
                        placeholder="7XXXXXXXX"
                        pattern="[0-9]{9}"
                        maxlength="9"
                    >
                </div>
                <small class="form-text text-muted">Enter your MTN or Airtel number without 0</small>
            </div>

            <div class="form-group">
                <label for="package">Select Package</label>
                <select v-model="selectedPackage" @change="updateAmount" class="form-control" required>
                    <option v-for="pkg in packages" :key="pkg.id" :value="pkg">
                        {{ pkg.name }} - {{ pkg.validity }} - UGX {{ formatAmount(pkg.price) }}
                    </option>
                </select>
            </div>

            <div class="amount-display">
                <h4>Total: UGX {{ formatAmount(amount) }}</h4>
            </div>

            <button type="submit" :disabled="processing" class="btn btn-success btn-lg btn-block">
                <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                {{ processing ? 'Processing...' : `Pay UGX ${formatAmount(amount)}` }}
            </button>

            <div v-if="error" class="alert alert-danger mt-3">
                <i class="fas fa-exclamation-triangle"></i> {{ error }}
            </div>
        </form>

        <!-- Payment Status Modal -->
        <div v-if="showStatusModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Status</h5>
                    <button @click="closeModal" class="close">&times;</button>
                </div>
                <div class="modal-body">
                    <div v-if="paymentStatus === 'completed'" class="text-success">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h4>Payment Successful!</h4>
                        <p>Your voucher has been sent to your phone via SMS.</p>
                    </div>
                    <div v-else-if="paymentStatus === 'pending'" class="text-warning">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <h4>Payment Pending</h4>
                        <p>Please confirm the payment on your phone.</p>
                        <button @click="checkPaymentStatus" class="btn btn-outline-warning">
                            <i class="fas fa-sync-alt"></i> Check Again
                        </button>
                    </div>
                    <div v-else class="text-danger">
                        <i class="fas fa-times-circle fa-3x mb-3"></i>
                        <h4>Payment Failed</h4>
                        <p>Please try again or contact support.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const phone = ref('')
const selectedPackage = ref(null)
const amount = ref(0)
const processing = ref(false)
const paymentInitiated = ref(false)
const transactionId = ref('')
const showStatusModal = ref(false)
const paymentStatus = ref('')
const error = ref('')
const checkInterval = ref(null)

const packages = ref([
    { id: 1, name: 'Daily', validity: '24 Hours', price: 1000, validity_hours: 24 },
    { id: 2, name: 'Weekly', validity: '7 Days', price: 6000, validity_hours: 168 },
    { id: 3, name: 'Monthly', validity: '30 Days', price: 20000, validity_hours: 720 },
    { id: 4, name: '3 Months', validity: '90 Days', price: 50000, validity_hours: 2160 }
])

onMounted(() => {
    selectedPackage.value = packages.value[0]
    updateAmount()
})

const updateAmount = () => {
    if (selectedPackage.value) {
        amount.value = selectedPackage.value.price
    }
}

const formatAmount = (value) => {
    return new Intl.NumberFormat().format(value)
}

const initiatePayment = async () => {
    processing.value = true
    error.value = ''

    try {
        const response = await axios.post('/api/payments/initiate', {
            phone: '256' + phone.value,
            amount: amount.value,
            package: selectedPackage.value.name.toLowerCase(),
            validity_hours: selectedPackage.value.validity_hours,
            description: `${selectedPackage.value.name} Internet Package`
        })

        if (response.data.success) {
            transactionId.value = response.data.data.transaction_id
            paymentInitiated.value = true

            // Start polling for payment status
            startPaymentPolling()
        } else {
            throw new Error(response.data.message)
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.message || 'Payment failed. Please try again.'
        console.error('Payment error:', err)
    } finally {
        processing.value = false
    }
}

const startPaymentPolling = () => {
    // Clear any existing interval
    if (checkInterval.value) {
        clearInterval(checkInterval.value)
    }

    // Check immediately
    checkPaymentStatus()

    // Then check every 10 seconds for 5 minutes
    checkInterval.value = setInterval(() => {
        checkPaymentStatus()
    }, 10000)

    // Auto-clear after 5 minutes
    setTimeout(() => {
        if (checkInterval.value) {
            clearInterval(checkInterval.value)
            checkInterval.value = null
        }
    }, 300000)
}

const checkPaymentStatus = async () => {
    try {
        const response = await axios.get(`/api/payments/verify/${transactionId.value}`)

        if (response.data.success) {
            paymentStatus.value = response.data.status
            showStatusModal.value = true

            if (response.data.status === 'completed') {
                // Stop polling if payment completed
                if (checkInterval.value) {
                    clearInterval(checkInterval.value)
                    checkInterval.value = null
                }
            }
        }
    } catch (err) {
        console.error('Status check error:', err)
    }
}

const closeModal = () => {
    showStatusModal.value = false
    if (paymentStatus.value === 'completed') {
        // Reset form for new payment
        paymentInitiated.value = false
        transactionId.value = ''
        phone.value = ''
    }
}

// Cleanup on unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
    if (checkInterval.value) {
        clearInterval(checkInterval.value)
    }
})
</script>

<style scoped>
.payment-form {
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
}

.loading-overlay {
    text-align: center;
    padding: 40px;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.payment-initiated {
    text-align: center;
    padding: 30px;
}

.success-icon {
    color: #28a745;
    font-size: 60px;
    margin-bottom: 20px;
}

.payment-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
    text-align: left;
}

.amount-display {
    text-align: center;
    margin: 20px 0;
    padding: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 5px;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 400px;
    width: 90%;
}

.btn-success {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-success:hover {
    opacity: 0.9;
}
</style>
