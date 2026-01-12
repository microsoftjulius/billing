<template>
  <div class="signup-page">
    <div class="signup-container">
      <div class="signup-card">
        <div class="signup-header">
          <div class="logo">
            <svg viewBox="0 0 24 24" fill="currentColor" class="logo-icon">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <h1>NetBill Pro</h1>
          </div>
          <h2>Create Your Account</h2>
          <p>Start your 14-day free trial today</p>
        </div>
        
        <!-- Plan Selection -->
        <div v-if="!selectedPlan" class="plan-selection">
          <h3>Choose Your Plan</h3>
          <div class="plans-grid">
            <div 
              v-for="plan in plans" 
              :key="plan.id"
              @click="selectPlan(plan)"
              class="plan-card"
              :class="{ recommended: plan.recommended }"
            >
              <div v-if="plan.recommended" class="plan-badge">Recommended</div>
              <h4>{{ plan.name }}</h4>
              <div class="plan-price">
                <span class="currency">$</span>
                <span class="amount">{{ plan.price }}</span>
                <span class="period">/month</span>
              </div>
              <ul class="plan-features">
                <li v-for="feature in plan.features" :key="feature">
                  <span class="check">✓</span>
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Signup Form -->
        <div v-else class="signup-form-container">
          <div class="selected-plan">
            <div class="plan-info">
              <h4>{{ selectedPlan.name }} Plan</h4>
              <div class="plan-price">
                <span class="currency">$</span>
                <span class="amount">{{ selectedPlan.price }}</span>
                <span class="period">/month</span>
              </div>
            </div>
            <button @click="changePlan" class="change-plan-btn">
              Change Plan
            </button>
          </div>

          <form @submit.prevent="handleSignup" class="signup-form">
            <div class="form-row">
              <div class="form-group">
                <label for="firstName">First Name</label>
                <input
                  id="firstName"
                  v-model="form.firstName"
                  type="text"
                  required
                  :disabled="isLoading"
                  class="form-input"
                  placeholder="Enter your first name"
                />
              </div>
              
              <div class="form-group">
                <label for="lastName">Last Name</label>
                <input
                  id="lastName"
                  v-model="form.lastName"
                  type="text"
                  required
                  :disabled="isLoading"
                  class="form-input"
                  placeholder="Enter your last name"
                />
              </div>
            </div>
            
            <div class="form-group">
              <label for="email">Email Address</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                :disabled="isLoading"
                class="form-input"
                placeholder="Enter your email"
              />
            </div>

            <div class="form-group">
              <label for="company">Company Name</label>
              <input
                id="company"
                v-model="form.company"
                type="text"
                required
                :disabled="isLoading"
                class="form-input"
                placeholder="Enter your ISP name"
              />
            </div>

            <div class="form-group">
              <label for="subdomain">Choose Your URL</label>
              <div class="subdomain-input">
                <input
                  id="subdomain"
                  v-model="form.subdomain"
                  type="text"
                  required
                  :disabled="isLoading"
                  class="form-input subdomain-field"
                  placeholder="your-isp"
                  @input="validateSubdomain"
                />
                <span class="subdomain-suffix">.netbillpro.com</span>
              </div>
              <div v-if="subdomainError" class="field-error">
                {{ subdomainError }}
              </div>
              <div v-else-if="form.subdomain" class="field-success">
                Your dashboard will be available at: {{ form.subdomain }}.netbillpro.com
              </div>
            </div>
            
            <div class="form-group">
              <label for="password">Password</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                required
                :disabled="isLoading"
                class="form-input"
                placeholder="Create a strong password"
                minlength="8"
              />
            </div>

            <div class="form-group">
              <label for="confirmPassword">Confirm Password</label>
              <input
                id="confirmPassword"
                v-model="form.confirmPassword"
                type="password"
                required
                :disabled="isLoading"
                class="form-input"
                placeholder="Confirm your password"
              />
            </div>
            
            <div class="form-group">
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="form.agreeToTerms"
                  required
                  :disabled="isLoading"
                >
                <span class="checkmark"></span>
                I agree to the <a href="#" class="link">Terms of Service</a> and <a href="#" class="link">Privacy Policy</a>
              </label>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="form.subscribeNewsletter"
                  :disabled="isLoading"
                >
                <span class="checkmark"></span>
                Send me product updates and ISP industry insights
              </label>
            </div>
            
            <button 
              type="submit" 
              :disabled="isLoading || !isFormValid"
              class="signup-btn"
            >
              <svg v-if="isLoading" class="spinner" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
              </svg>
              <span>{{ isLoading ? 'Creating Account...' : 'Start Free Trial' }}</span>
            </button>
          </form>

          <div class="signup-footer">
            <p>Already have an account? <router-link to="/login" class="link">Sign in</router-link></p>
          </div>
        </div>
        
        <div v-if="error" class="error-message">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
          {{ error }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/store/modules/app'
import type { User } from '@/types'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()

const plans = ref([
  {
    id: 'starter',
    name: 'Starter',
    price: 15,
    recommended: false,
    features: [
      'Up to 100 customers',
      '2 MikroTik routers',
      'Basic SMS notifications',
      'Single payment gateway',
      'Basic analytics',
      'Email support',
      'Basic voucher management',
      'Customer portal'
    ]
  },
  {
    id: 'professional',
    name: 'Professional',
    price: 65,
    recommended: true,
    features: [
      'Up to 1,000 customers',
      'Unlimited MikroTik routers',
      'Advanced SMS automation',
      'Multiple payment gateways',
      'Advanced analytics & reports',
      'API access',
      'Priority support',
      'Custom branding',
      'Bulk voucher operations',
      'Advanced customer management',
      'Payment reconciliation',
      'Router monitoring & alerts'
    ]
  },
  {
    id: 'enterprise',
    name: 'Enterprise',
    price: 199,
    recommended: false,
    features: [
      'Unlimited customers',
      'Unlimited routers',
      'White-label solution',
      'Custom integrations',
      'Advanced security features',
      'Dedicated account manager',
      '24/7 phone support',
      'SLA guarantee',
      'Multi-tenant management',
      'Advanced reporting suite',
      'Custom payment flows',
      'Enterprise-grade monitoring'
    ]
  }
])

const selectedPlan = ref(null)

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  company: '',
  subdomain: '',
  password: '',
  confirmPassword: '',
  agreeToTerms: false,
  subscribeNewsletter: false
})

const isLoading = ref(false)
const error = ref('')
const subdomainError = ref('')

const isFormValid = computed(() => {
  return form.value.firstName &&
         form.value.lastName &&
         form.value.email &&
         form.value.company &&
         form.value.subdomain &&
         form.value.password &&
         form.value.confirmPassword &&
         form.value.password === form.value.confirmPassword &&
         form.value.agreeToTerms &&
         !subdomainError.value
})

const selectPlan = (plan) => {
  selectedPlan.value = plan
}

const changePlan = () => {
  selectedPlan.value = null
}

const validateSubdomain = () => {
  const subdomain = form.value.subdomain.toLowerCase()
  
  // Reset error
  subdomainError.value = ''
  
  if (!subdomain) return
  
  // Check format
  const subdomainRegex = /^[a-z0-9][a-z0-9-]*[a-z0-9]$/
  if (subdomain.length < 3) {
    subdomainError.value = 'Subdomain must be at least 3 characters long'
    return
  }
  
  if (subdomain.length > 30) {
    subdomainError.value = 'Subdomain must be less than 30 characters'
    return
  }
  
  if (!subdomainRegex.test(subdomain)) {
    subdomainError.value = 'Subdomain can only contain letters, numbers, and hyphens'
    return
  }
  
  // Check for reserved words
  const reserved = ['www', 'api', 'admin', 'app', 'mail', 'ftp', 'blog', 'shop', 'store']
  if (reserved.includes(subdomain)) {
    subdomainError.value = 'This subdomain is reserved'
    return
  }
  
  // Update form value to lowercase
  form.value.subdomain = subdomain
}

const handleSignup = async () => {
  try {
    isLoading.value = true
    error.value = ''
    
    // Validate passwords match
    if (form.value.password !== form.value.confirmPassword) {
      throw new Error('Passwords do not match')
    }
    
    // Prepare signup data with correct field names
    const signupData = {
      first_name: form.value.firstName,
      last_name: form.value.lastName,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword,
      company_name: form.value.company,
      tenant_slug: form.value.subdomain.toLowerCase(),
      plan: selectedPlan.value.id,
      agree_to_terms: form.value.agreeToTerms,
      subscribe_newsletter: form.value.subscribeNewsletter
    }
    
    // Make API call to register endpoint
    const response = await fetch('/api/v1/auth/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(signupData)
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      // Handle validation errors
      if (data.errors) {
        const errorMessages = Object.values(data.errors).flat()
        throw new Error(errorMessages.join(', '))
      }
      throw new Error(data.message || 'Registration failed')
    }
    
    if (!data.success) {
      throw new Error(data.message || 'Registration failed')
    }
    
    // Extract user and tenant data from response
    const { user, tenant, token, redirect_url } = data.data
    
    // Set user in store
    appStore.setUser({
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      token: token,
      tenantId: tenant.id,
      plan: tenant.plan,
      created_at: user.created_at,
      updated_at: user.updated_at
    })
    
    // Set tenant in store
    appStore.setTenant({
      id: tenant.id,
      name: tenant.name,
      subdomain: tenant.slug,
      plan: tenant.plan,
      planName: selectedPlan.value.name,
      planPrice: selectedPlan.value.price,
      planFeatures: selectedPlan.value.features,
      owner: {
        firstName: form.value.firstName,
        lastName: form.value.lastName,
        email: form.value.email
      },
      settings: tenant.metadata?.plan_features || getDefaultPlanSettings(tenant.plan),
      created_at: tenant.created_at,
      trial_ends_at: tenant.trial_ends_at
    })
    
    // Store token and user data
    localStorage.setItem('auth_token', token)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('tenant', JSON.stringify(tenant))
    
    // Set axios default authorization header
    if (typeof window !== 'undefined' && (window as any).axios) {
      (window as any).axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }
    
    // Show success message with tenant URL
    const tenantUrl = tenant.tenant_url || `https://${tenant.slug}.netbillpro.com`
    appStore.addSuccessNotification(
      `Welcome to NetBill Pro! Your account is ready. You'll be redirected to your dashboard.`,
      'Account Created Successfully',
      3000
    )
    
    // Small delay to show the success message
    setTimeout(() => {
      // Redirect to tenant URL if available, otherwise use local dashboard
      if (redirect_url) {
        window.location.href = redirect_url
      } else {
        // Fallback to local dashboard with tenant context
        router.push({
          path: '/app/dashboard',
          query: { 
            tenant: tenant.slug,
            plan: tenant.plan,
            welcome: 'true'
          }
        })
      }
    }, 1500)
    
  } catch (err: any) {
    error.value = err.message || 'Signup failed. Please try again.'
    
    appStore.addErrorNotification(
      error.value,
      'Signup Failed'
    )
  } finally {
    isLoading.value = false
  }
}

// Helper functions for plan limits
const getMaxCustomers = (planId: string) => {
  switch (planId) {
    case 'starter': return 100
    case 'professional': return 1000
    case 'enterprise': return -1 // unlimited
    default: return 100
  }
}

const getMaxRouters = (planId: string) => {
  switch (planId) {
    case 'starter': return 2
    case 'professional': return -1 // unlimited
    case 'enterprise': return -1 // unlimited
    default: return 2
  }
}

// Helper function for default plan settings
const getDefaultPlanSettings = (planId: string) => {
  const settings = {
    starter: {
      maxCustomers: 100,
      maxRouters: 2,
      hasAdvancedSMS: false,
      hasMultipleGateways: false,
      hasAPIAccess: false,
      hasCustomBranding: false,
      hasWhiteLabel: false,
      hasPrioritySupport: false,
      hasAdvancedReports: false
    },
    professional: {
      maxCustomers: 1000,
      maxRouters: -1,
      hasAdvancedSMS: true,
      hasMultipleGateways: true,
      hasAPIAccess: true,
      hasCustomBranding: true,
      hasWhiteLabel: false,
      hasPrioritySupport: true,
      hasAdvancedReports: true
    },
    enterprise: {
      maxCustomers: -1,
      maxRouters: -1,
      hasAdvancedSMS: true,
      hasMultipleGateways: true,
      hasAPIAccess: true,
      hasCustomBranding: true,
      hasWhiteLabel: true,
      hasPrioritySupport: true,
      hasAdvancedReports: true,
      hasDedicatedSupport: true,
      hasSLAGuarantee: true
    }
  }
  
  return settings[planId] || settings.starter
}

// Check for plan parameter in URL
onMounted(() => {
  const planParam = route.query.plan as string
  if (planParam) {
    const plan = plans.value.find(p => p.id === planParam)
    if (plan) {
      selectedPlan.value = plan
    }
  }
})
</script>

<style scoped>
.signup-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem 1rem;
  position: relative;
}

.signup-page::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 0.3;
}

.signup-container {
  width: 100%;
  max-width: 800px;
  position: relative;
  z-index: 1;
}

.signup-card {
  background: var(--card-bg);
  border-radius: 1.5rem;
  padding: 2.5rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border-color);
  backdrop-filter: blur(10px);
}

.signup-header {
  text-align: center;
  margin-bottom: 2rem;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.logo-icon {
  width: 32px;
  height: 32px;
  color: var(--primary-color);
}

.logo h1 {
  color: var(--primary-color);
  font-size: 1.75rem;
  font-weight: 800;
  margin: 0;
}

.signup-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.signup-header p {
  color: var(--text-secondary);
  margin: 0;
  font-size: 0.95rem;
}

/* Plan Selection */
.plan-selection h3 {
  text-align: center;
  color: var(--text-primary);
  margin-bottom: 2rem;
  font-size: 1.25rem;
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.plan-card {
  background: var(--card-bg);
  border: 2px solid var(--border-color);
  border-radius: 1rem;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.plan-card:hover {
  border-color: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.plan-card.recommended {
  border-color: var(--primary-color);
}

.plan-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--primary-color);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.plan-card h4 {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  text-align: center;
}

.plan-price {
  display: flex;
  align-items: baseline;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.currency {
  font-size: 1rem;
  color: var(--text-secondary);
}

.amount {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text-primary);
}

.period {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.check {
  color: var(--primary-color);
  font-weight: 600;
}

/* Selected Plan */
.selected-plan {
  background: var(--hover-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.plan-info h4 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.change-plan-btn {
  background: transparent;
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.change-plan-btn:hover {
  background: var(--primary-color);
  color: white;
}

/* Form Styles */
.signup-form {
  space-y: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.form-input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 2px solid var(--border-color);
  border-radius: 0.75rem;
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 1rem;
  transition: all 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  transform: translateY(-1px);
}

.form-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.subdomain-input {
  display: flex;
  align-items: center;
  border: 2px solid var(--border-color);
  border-radius: 0.75rem;
  background: var(--card-bg);
  transition: all 0.2s ease;
}

.subdomain-input:focus-within {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  transform: translateY(-1px);
}

.subdomain-field {
  border: none;
  box-shadow: none;
  transform: none;
  flex: 1;
}

.subdomain-field:focus {
  border: none;
  box-shadow: none;
  transform: none;
}

.subdomain-suffix {
  padding: 0.875rem 1rem;
  color: var(--text-secondary);
  font-size: 1rem;
  border-left: 1px solid var(--border-color);
  background: var(--hover-bg);
}

.field-error {
  color: #dc2626;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

.field-success {
  color: #059669;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--text-secondary);
  line-height: 1.5;
}

.checkbox-label input {
  margin-right: 0.75rem;
  margin-top: 0.125rem;
}

.link {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 500;
}

.link:hover {
  text-decoration: underline;
}

.signup-btn {
  width: 100%;
  padding: 1rem;
  background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
  color: white;
  border: none;
  border-radius: 0.75rem;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.signup-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.signup-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

.spinner {
  width: 20px;
  height: 20px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.signup-footer {
  text-align: center;
  margin-top: 1rem;
}

.signup-footer p {
  color: var(--text-secondary);
  font-size: 0.875rem;
  margin: 0;
}

.error-message {
  margin-top: 1rem;
  padding: 1rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 0.75rem;
  color: #dc2626;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.error-message svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Dark theme adjustments */
[data-theme="dark"] .signup-page {
  background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
}

[data-theme="dark"] .signup-card {
  background: rgba(30, 41, 59, 0.8);
  border-color: rgba(71, 85, 105, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
  .signup-card {
    padding: 2rem 1.5rem;
    margin: 1rem;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .plans-grid {
    grid-template-columns: 1fr;
  }
  
  .selected-plan {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
}

@media (max-width: 480px) {
  .signup-container {
    max-width: 100%;
  }
  
  .signup-card {
    padding: 1.5rem 1rem;
  }
}
</style>