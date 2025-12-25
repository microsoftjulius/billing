<template>
    <div class="tenant-form">
        <div class="page-header">
            <h1 class="page-title">
                {{ isEditMode ? 'Edit Tenant' : 'Create New Tenant' }}
            </h1>
            <div class="page-actions">
                <router-link to="/tenants" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </router-link>
            </div>
        </div>

        <div class="form-container">
            <div class="form-steps" v-if="!isEditMode">
                <div class="step" :class="{ active: currentStep === 1, completed: currentStep > 1 }">
                    <div class="step-number">1</div>
                    <div class="step-label">Basic Info</div>
                </div>
                <div class="step" :class="{ active: currentStep === 2, completed: currentStep > 2 }">
                    <div class="step-number">2</div>
                    <div class="step-label">Plan & Limits</div>
                </div>
                <div class="step" :class="{ active: currentStep === 3, completed: currentStep > 3 }">
                    <div class="step-number">3</div>
                    <div class="step-label">Billing & Settings</div>
                </div>
                <div class="step" :class="{ active: currentStep === 4, completed: currentStep > 4 }">
                    <div class="step-number">4</div>
                    <div class="step-label">Review & Create</div>
                </div>
            </div>

            <form @submit.prevent="submitForm">
                <!-- Step 1: Basic Information -->
                <div v-if="currentStep === 1" class="form-step">
                    <div class="form-section">
                        <h3 class="section-title">Basic Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Tenant Name *</label>
                                <input
                                    type="text"
                                    id="name"
                                    v-model="formData.name"
                                    :class="{ 'is-invalid': errors.name }"
                                    class="form-control"
                                    placeholder="Enter tenant name"
                                    required
                                >
                                <div v-if="errors.name" class="invalid-feedback">
                                    {{ errors.name[0] }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="slug">Slug *</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        id="slug"
                                        v-model="formData.slug"
                                        :class="{ 'is-invalid': errors.slug }"
                                        class="form-control"
                                        placeholder="tenant-name"
                                        required
                                    >
                                    <span class="input-group-text">.{{ baseDomain }}</span>
                                </div>
                                <div v-if="errors.slug" class="invalid-feedback">
                                    {{ errors.slug[0] }}
                                </div>
                                <small class="form-text text-muted">
                                    This will be used for the tenant's subdomain
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input
                                    type="email"
                                    id="email"
                                    v-model="formData.email"
                                    :class="{ 'is-invalid': errors.email }"
                                    class="form-control"
                                    placeholder="admin@tenant.com"
                                    required
                                >
                                <div v-if="errors.email" class="invalid-feedback">
                                    {{ errors.email[0] }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    v-model="formData.phone"
                                    :class="{ 'is-invalid': errors.phone }"
                                    class="form-control"
                                    placeholder="+1234567890"
                                >
                                <div v-if="errors.phone" class="invalid-feedback">
                                    {{ errors.phone[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea
                                id="address"
                                v-model="formData.address"
                                :class="{ 'is-invalid': errors.address }"
                                class="form-control"
                                rows="3"
                                placeholder="Enter complete address"
                            ></textarea>
                            <div v-if="errors.address" class="invalid-feedback">
                                {{ errors.address[0] }}
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="logo">Logo</label>
                            <div class="file-upload">
                                <input
                                    type="file"
                                    id="logo"
                                    @change="handleLogoUpload"
                                    accept="image/*"
                                    class="file-input"
                                >
                                <label for="logo" class="file-label">
                                    <i class="fas fa-upload"></i>
                                    <span v-if="!logoPreview">Choose logo file</span>
                                    <span v-else>Change logo</span>
                                </label>
                                <div v-if="logoPreview" class="logo-preview">
                                    <img :src="logoPreview" alt="Logo preview">
                                    <button type="button" @click="removeLogo" class="btn-remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    Recommended size: 200x200px, Max: 2MB
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Domains</h3>
                        <div class="domains-list">
                            <div v-for="(domain, index) in formData.domains" :key="index" class="domain-item">
                                <input
                                    type="text"
                                    v-model="formData.domains[index]"
                                    :class="{ 'is-invalid': errors.domains && errors.domains[index] }"
                                    class="form-control"
                                    placeholder="custom-domain.com"
                                >
                                <button
                                    type="button"
                                    @click="removeDomain(index)"
                                    class="btn-remove-domain"
                                    v-if="index > 0"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addDomain"
                            class="btn btn-outline"
                        >
                            <i class="fas fa-plus"></i> Add Domain
                        </button>
                    </div>
                </div>

                <!-- Step 2: Plan & Limits -->
                <div v-if="currentStep === 2" class="form-step">
                    <div class="form-section">
                        <h3 class="section-title">Select Plan</h3>
                        <div class="plans-grid">
                            <div
                                v-for="plan in plans"
                                :key="plan.id"
                                class="plan-card"
                                :class="{ selected: formData.plan === plan.id }"
                                @click="formData.plan = plan.id"
                            >
                                <div class="plan-header">
                                    <h4 class="plan-name">{{ plan.name }}</h4>
                                    <div class="plan-price">
                                        <span class="price">${{ plan.price }}</span>
                                        <span class="period">/month</span>
                                    </div>
                                </div>
                                <div class="plan-features">
                                    <ul>
                                        <li v-for="feature in plan.features" :key="feature">
                                            <i class="fas fa-check"></i> {{ feature }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Resource Limits</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="max_users">Maximum Users</label>
                                <input
                                    type="number"
                                    id="max_users"
                                    v-model="formData.max_users"
                                    :class="{ 'is-invalid': errors.max_users }"
                                    class="form-control"
                                    min="1"
                                    max="1000"
                                >
                                <div v-if="errors.max_users" class="invalid-feedback">
                                    {{ errors.max_users[0] }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="max_vouchers_per_day">Daily Vouchers</label>
                                <input
                                    type="number"
                                    id="max_vouchers_per_day"
                                    v-model="formData.max_vouchers_per_day"
                                    :class="{ 'is-invalid': errors.max_vouchers_per_day }"
                                    class="form-control"
                                    min="1"
                                    max="10000"
                                >
                                <div v-if="errors.max_vouchers_per_day" class="invalid-feedback">
                                    {{ errors.max_vouchers_per_day[0] }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="data_retention_days">Data Retention (days)</label>
                                <input
                                    type="number"
                                    id="data_retention_days"
                                    v-model="formData.data_retention_days"
                                    :class="{ 'is-invalid': errors.data_retention_days }"
                                    class="form-control"
                                    min="30"
                                    max="3650"
                                >
                                <div v-if="errors.data_retention_days" class="invalid-feedback">
                                    {{ errors.data_retention_days[0] }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Billing & Settings -->
                <div v-if="currentStep === 3" class="form-step">
                    <div class="form-section">
                        <h3 class="section-title">Billing Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="billing_cycle">Billing Cycle</label>
                                <select
                                    id="billing_cycle"
                                    v-model="formData.billing_cycle"
                                    :class="{ 'is-invalid': errors.billing_cycle }"
                                    class="form-control"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                <div v-if="errors.billing_cycle" class="invalid-feedback">
                                    {{ errors.billing_cycle[0] }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="next_billing_date">Next Billing Date</label>
                                <input
                                    type="date"
                                    id="next_billing_date"
                                    v-model="formData.next_billing_date"
                                    :class="{ 'is-invalid': errors.next_billing_date }"
                                    class="form-control"
                                >
                                <div v-if="errors.next_billing_date" class="invalid-feedback">
                                    {{ errors.next_billing_date[0] }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Additional Settings</h3>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input
                                    type="checkbox"
                                    v-model="formData.is_active"
                                    class="checkbox-input"
                                >
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Activate tenant immediately</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="metadata">Custom Metadata (JSON)</label>
                            <textarea
                                id="metadata"
                                v-model="formData.metadata_json"
                                :class="{ 'is-invalid': errors.metadata }"
                                class="form-control"
                                rows="4"
                                placeholder='{"key": "value"}'
                            ></textarea>
                            <div v-if="errors.metadata" class="invalid-feedback">
                                {{ errors.metadata[0] }}
                            </div>
                            <small class="form-text text-muted">
                                Optional JSON data for custom configuration
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Review -->
                <div v-if="currentStep === 4" class="form-step">
                    <div class="review-section">
                        <h3 class="section-title">Review Tenant Details</h3>

                        <div class="review-grid">
                            <div class="review-card">
                                <h4 class="review-title">Basic Information</h4>
                                <div class="review-content">
                                    <div class="review-item">
                                        <span class="review-label">Name:</span>
                                        <span class="review-value">{{ formData.name }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Slug:</span>
                                        <span class="review-value">{{ formData.slug }}.{{ baseDomain }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Email:</span>
                                        <span class="review-value">{{ formData.email }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Status:</span>
                                        <span class="review-value badge" :class="formData.is_active ? 'active' : 'inactive'">
                      {{ formData.is_active ? 'Active' : 'Inactive' }}
                    </span>
                                    </div>
                                </div>
                            </div>

                            <div class="review-card">
                                <h4 class="review-title">Plan & Limits</h4>
                                <div class="review-content">
                                    <div class="review-item">
                                        <span class="review-label">Plan:</span>
                                        <span class="review-value">{{ formData.plan }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Max Users:</span>
                                        <span class="review-value">{{ formData.max_users }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Daily Vouchers:</span>
                                        <span class="review-value">{{ formData.max_vouchers_per_day }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Data Retention:</span>
                                        <span class="review-value">{{ formData.data_retention_days }} days</span>
                                    </div>
                                </div>
                            </div>

                            <div class="review-card">
                                <h4 class="review-title">Billing</h4>
                                <div class="review-content">
                                    <div class="review-item">
                                        <span class="review-label">Billing Cycle:</span>
                                        <span class="review-value">{{ formData.billing_cycle }}</span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Next Billing:</span>
                                        <span class="review-value">{{ formatDate(formData.next_billing_date) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button
                        type="button"
                        @click="previousStep"
                        class="btn btn-secondary"
                        v-if="currentStep > 1 && !isEditMode"
                    >
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>

                    <button
                        type="button"
                        @click="nextStep"
                        class="btn btn-primary"
                        v-if="currentStep < 4 && !isEditMode"
                    >
                        Next <i class="fas fa-arrow-right"></i>
                    </button>

                    <button
                        type="submit"
                        class="btn btn-success"
                        :disabled="submitting"
                    >
            <span v-if="submitting">
              <i class="fas fa-spinner fa-spin"></i> Processing...
            </span>
                        <span v-else>
              {{ isEditMode ? 'Update Tenant' : 'Create Tenant' }}
            </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'

export default {
    name: 'TenantForm',

    props: {
        tenantId: {
            type: String,
            default: null
        }
    },

    data() {
        return {
            currentStep: 1,
            formData: {
                name: '',
                slug: '',
                email: '',
                phone: '',
                address: '',
                logo: null,
                domains: [''],
                plan: 'basic',
                max_users: 10,
                max_vouchers_per_day: 100,
                data_retention_days: 365,
                billing_cycle: 'monthly',
                next_billing_date: this.getNextBillingDate(),
                is_active: true,
                metadata: {},
                metadata_json: ''
            },
            logoPreview: null,
            logoFile: null,
            plans: [
                {
                    id: 'basic',
                    name: 'Basic',
                    price: 29,
                    features: ['Up to 10 users', '100 vouchers/day', 'Basic support']
                },
                {
                    id: 'premium',
                    name: 'Premium',
                    price: 79,
                    features: ['Up to 50 users', '500 vouchers/day', 'Priority support', 'Custom branding']
                },
                {
                    id: 'enterprise',
                    name: 'Enterprise',
                    price: 199,
                    features: ['Unlimited users', 'Unlimited vouchers', '24/7 support', 'Custom development', 'API access']
                },
                {
                    id: 'custom',
                    name: 'Custom',
                    price: 'Custom',
                    features: ['Fully customized', 'Dedicated support', 'White-label', 'Custom integrations']
                }
            ],
            errors: {},
            submitting: false
        }
    },

    computed: {
        ...mapState(['baseDomain']),
        isEditMode() {
            return !!this.tenantId
        }
    },

    created() {
        if (this.isEditMode) {
            this.fetchTenant()
        }
    },

    methods: {
        ...mapActions(['createTenant', 'updateTenant', 'fetchTenantById']),

        async fetchTenant() {
            try {
                const tenant = await this.fetchTenantById(this.tenantId)
                this.formData = { ...tenant }
                this.formData.metadata_json = JSON.stringify(tenant.metadata || {}, null, 2)
                this.formData.domains = tenant.domains || ['']
            } catch (error) {
                this.$toast.error('Failed to load tenant data')
                this.$router.push('/tenants')
            }
        },

        getNextBillingDate() {
            const date = new Date()
            date.setMonth(date.getMonth() + 1)
            return date.toISOString().split('T')[0]
        },

        nextStep() {
            if (this.validateStep()) {
                this.currentStep++
            }
        },

        previousStep() {
            this.currentStep--
        },

        validateStep() {
            const stepValidations = {
                1: () => {
                    if (!this.formData.name.trim()) {
                        this.$toast.error('Tenant name is required')
                        return false
                    }
                    if (!this.formData.slug.trim()) {
                        this.$toast.error('Slug is required')
                        return false
                    }
                    if (!this.formData.email.trim()) {
                        this.$toast.error('Email is required')
                        return false
                    }
                    return true
                },
                2: () => {
                    if (!this.formData.plan) {
                        this.$toast.error('Please select a plan')
                        return false
                    }
                    return true
                }
            }

            const validate = stepValidations[this.currentStep]
            return validate ? validate() : true
        },

        handleLogoUpload(event) {
            const file = event.target.files[0]
            if (!file) return

            // Validate file size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                this.$toast.error('Logo file size must be less than 2MB')
                return
            }

            // Validate file type
            if (!file.type.startsWith('image/')) {
                this.$toast.error('Please upload an image file')
                return
            }

            this.logoFile = file

            // Create preview
            const reader = new FileReader()
            reader.onload = (e) => {
                this.logoPreview = e.target.result
            }
            reader.readAsDataURL(file)
        },

        removeLogo() {
            this.logoFile = null
            this.logoPreview = null
            this.formData.logo = null
        },

        addDomain() {
            this.formData.domains.push('')
        },

        removeDomain(index) {
            this.formData.domains.splice(index, 1)
        },

        formatDate(date) {
            if (!date) return '-'
            return new Date(date).toLocaleDateString()
        },

        async submitForm() {
            this.submitting = true
            this.errors = {}

            try {
                // Prepare form data
                const formData = new FormData()
                const data = { ...this.formData }

                // Process metadata
                if (data.metadata_json) {
                    try {
                        data.metadata = JSON.parse(data.metadata_json)
                        delete data.metadata_json
                    } catch (error) {
                        this.$toast.error('Invalid JSON in metadata field')
                        this.submitting = false
                        return
                    }
                }

                // Add logo if uploaded
                if (this.logoFile) {
                    formData.append('logo', this.logoFile)
                }

                // Add other fields
                Object.keys(data).forEach(key => {
                    if (key === 'domains') {
                        data[key].forEach((domain, index) => {
                            if (domain.trim()) {
                                formData.append(`domains[${index}]`, domain.trim())
                            }
                        })
                    } else if (data[key] !== null && data[key] !== undefined) {
                        formData.append(key, data[key])
                    }
                })

                let response
                if (this.isEditMode) {
                    response = await this.updateTenant({
                        id: this.tenantId,
                        data: formData
                    })
                    this.$toast.success('Tenant updated successfully')
                } else {
                    response = await this.createTenant(formData)
                    this.$toast.success('Tenant created successfully')
                }

                // Redirect to tenant details
                this.$router.push(`/tenants/${response.data.id}`)

            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                    this.$toast.error('Please fix the validation errors')
                } else {
                    this.$toast.error(error.response?.data?.message || 'Operation failed')
                }
            } finally {
                this.submitting = false
            }
        }
    }
}
</script>

<style scoped>
.tenant-form {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 32px;
    position: relative;
}

.form-steps::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step:not(:last-child) {
    margin-right: 16px;
}

.step-number {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.125rem;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: #3b82f6;
    color: white;
    box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
}

.step.completed .step-number {
    background: #10b981;
    color: white;
}

.step-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
    text-align: center;
}

.step.active .step-label {
    color: #3b82f6;
    font-weight: 600;
}

.form-section {
    margin-bottom: 32px;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #475569;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control.is-invalid {
    border-color: #ef4444;
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.invalid-feedback {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
}

.form-text {
    display: block;
    margin-top: 4px;
    font-size: 0.875rem;
    color: #6b7280;
}

.input-group {
    display: flex;
}

.input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-group-text {
    padding: 10px 14px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-left: 0;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
    color: #6b7280;
    font-size: 0.95rem;
}

.file-upload {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.file-input {
    display: none;
}

.file-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 6px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    width: fit-content;
}

.file-label:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

.logo-preview {
    position: relative;
    width: 120px;
    height: 120px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.btn-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
}

.domains-list {
    margin-bottom: 16px;
}

.domain-item {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.btn-remove-domain {
    width: 40px;
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-remove-domain:hover {
    background: #fee2e2;
}

.btn-outline {
    padding: 8px 16px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.plan-card {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.plan-card:hover {
    border-color: #9ca3af;
    transform: translateY(-2px);
}

.plan-card.selected {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.plan-header {
    margin-bottom: 16px;
}

.plan-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 8px;
}

.plan-price {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.price {
    font-size: 2rem;
    font-weight: 700;
    color: #3b82f6;
}

.period {
    color: #6b7280;
    font-size: 0.875rem;
}

.plan-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.plan-features li {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: #4b5563;
    font-size: 0.95rem;
}

.plan-features li i {
    color: #10b981;
    font-size: 0.875rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.checkbox-input {
    display: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    position: relative;
    transition: all 0.2s ease;
}

.checkbox-input:checked + .checkbox-custom {
    background: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.checkbox-text {
    color: #374151;
    font-size: 0.95rem;
}

.review-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.review-card {
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.review-title {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}

.review-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.review-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.review-label {
    color: #6b7280;
    font-size: 0.9rem;
}

.review-value {
    color: #1f2937;
    font-weight: 500;
    font-size: 0.95rem;
}

.review-value.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.review-value.badge.active {
    background: #d1fae5;
    color: #065f46;
}

.review-value.badge.inactive {
    background: #fee2e2;
    color: #991b1b;
}

.form-navigation {
    display: flex;
    justify-content: space-between;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #4b5563;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #2563eb;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #059669;
}
</style>
