<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-card">
        <div class="login-header">
          <div class="logo">
            <svg viewBox="0 0 24 24" fill="currentColor" class="logo-icon">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <h1>NetBill Pro</h1>
          </div>
          <h2>Welcome Back</h2>
          <p>Sign in to access your billing dashboard</p>
        </div>
        
        <!-- Demo Credentials Notice -->
        <div class="demo-notice">
          <h4>Demo Credentials</h4>
          <div class="credentials-options">
            <div class="credential-option">
              <p><strong>Global Admin:</strong></p>
              <p>Email: admin@gmail.com</p>
              <p>Password: 12345678</p>
            </div>
            <div class="credential-option">
              <p><strong>System Admin:</strong></p>
              <p>Email: admin@billing.com</p>
              <p>Password: password123</p>
            </div>
          </div>
        </div>
        
        <form @submit.prevent="handleLogin" class="login-form">
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
            <label for="password">Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              :disabled="isLoading"
              class="form-input"
              placeholder="Enter your password"
            />
          </div>
          
          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox" v-model="rememberMe">
              <span class="checkmark"></span>
              Remember me
            </label>
            <a href="#" class="forgot-link">Forgot password?</a>
          </div>
          
          <button 
            type="submit" 
            :disabled="isLoading"
            class="login-btn"
          >
            <svg v-if="isLoading" class="spinner" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
              <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span>{{ isLoading ? 'Signing In...' : 'Sign In' }}</span>
          </button>
          
          <button 
            type="button" 
            @click="loginWithDemo"
            :disabled="isLoading"
            class="demo-btn"
          >
            Use Global Admin (Recommended)
          </button>
          
          <button 
            type="button" 
            @click="loginWithSystemAdmin"
            :disabled="isLoading"
            class="demo-btn secondary"
          >
            Use System Admin
          </button>
        </form>
        
        <div class="login-footer">
          <p>Don't have an account? <router-link to="/signup" class="link">Sign up for free</router-link></p>
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
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAppStore } from '@/store/modules/app';
import type { User } from '@/types';

const router = useRouter();
const appStore = useAppStore();

const form = ref({
  email: '',
  password: ''
});

const rememberMe = ref(false);
const isLoading = ref(false);
const error = ref('');

const loginWithDemo = () => {
  form.value.email = 'admin@gmail.com';
  form.value.password = '12345678';
  handleLogin();
};

const loginWithSystemAdmin = () => {
  form.value.email = 'admin@billing.com';
  form.value.password = 'password123';
  handleLogin();
};

const handleLogin = async () => {
  try {
    isLoading.value = true;
    error.value = '';
    
    // Determine tenant context from subdomain
    const hostname = window.location.hostname;
    const isSubdomain = hostname.includes('.netbillpro.com') && !hostname.startsWith('www.');
    let tenantSlug = null;
    
    if (isSubdomain) {
      tenantSlug = hostname.split('.')[0];
    }
    
    // Prepare login data
    const loginData = {
      email: form.value.email,
      password: form.value.password,
      tenant_slug: tenantSlug,
      remember_me: rememberMe.value
    };
    
    // Make API call to login endpoint
    const response = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(tenantSlug && { 'X-Tenant-Slug': tenantSlug })
      },
      body: JSON.stringify(loginData)
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Login failed');
    }
    
    if (!data.success) {
      throw new Error(data.message || 'Login failed');
    }
    
    // Extract user and tenant data from response
    const { user, tenant, token } = data.data;
    
    // Set user in store
    appStore.setUser({
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      token: token,
      tenantId: tenant?.id || null,
      plan: tenant?.plan || null,
      created_at: user.created_at,
      updated_at: user.updated_at
    });
    
    // Set tenant in store if available
    if (tenant) {
      appStore.setTenant({
        id: tenant.id,
        name: tenant.name,
        subdomain: tenant.slug,
        plan: tenant.plan,
        planName: tenant.plan.charAt(0).toUpperCase() + tenant.plan.slice(1),
        planPrice: getPlanPrice(tenant.plan),
        planFeatures: getPlanFeatures(tenant.plan),
        owner: {
          firstName: user.name.split(' ')[0],
          lastName: user.name.split(' ').slice(1).join(' '),
          email: user.email
        },
        settings: tenant.metadata?.features || {},
        created_at: tenant.created_at
      });
    }
    
    // Store token and user data
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user', JSON.stringify(user));
    if (tenant) {
      localStorage.setItem('tenant', JSON.stringify(tenant));
    }
    
    // Set axios default authorization header
    if (typeof window !== 'undefined' && (window as any).axios) {
      (window as any).axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    // Redirect to dashboard
    router.push('/app/dashboard');
    
    const welcomeMessage = tenant 
      ? `Welcome back to ${tenant.name}!`
      : `Welcome back, ${user.name}!`;
      
    appStore.addSuccessNotification(
      welcomeMessage,
      'Login Successful'
    );
    
  } catch (err: any) {
    error.value = err.message || 'Login failed. Please try again.';
    
    appStore.addErrorNotification(
      error.value,
      'Login Failed'
    );
  } finally {
    isLoading.value = false;
  }
};

// Helper functions for plan data
const getPlanPrice = (plan: string) => {
  switch (plan) {
    case 'starter': return 15;
    case 'professional': return 65;
    case 'enterprise': return 199;
    default: return 0;
  }
};

const getPlanFeatures = (plan: string) => {
  const features = {
    starter: [
      'Up to 100 customers',
      '2 MikroTik routers',
      'Basic SMS notifications',
      'Single payment gateway',
      'Basic analytics',
      'Email support'
    ],
    professional: [
      'Up to 1,000 customers',
      'Unlimited MikroTik routers',
      'Advanced SMS automation',
      'Multiple payment gateways',
      'Advanced analytics & reports',
      'API access',
      'Priority support',
      'Custom branding'
    ],
    enterprise: [
      'Unlimited customers',
      'Unlimited routers',
      'White-label solution',
      'Custom integrations',
      'Advanced security features',
      'Dedicated account manager',
      '24/7 phone support',
      'SLA guarantee'
    ]
  };
  
  return features[plan] || features.starter;
};

// Auto-fill demo credentials on component mount
form.value.email = 'admin@gmail.com';
form.value.password = '12345678';
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 1rem;
  position: relative;
}

.login-page::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 0.3;
}

.login-container {
  width: 100%;
  max-width: 420px;
  position: relative;
  z-index: 1;
}

.login-card {
  background: var(--card-bg);
  border-radius: 1.5rem;
  padding: 2.5rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border-color);
  backdrop-filter: blur(10px);
}

.login-header {
  text-align: center;
  margin-bottom: 2rem;
}

.logo h1 {
  color: var(--primary-color);
  font-size: 1.75rem;
  font-weight: 800;
  margin: 0 0 1rem 0;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.logo-icon {
  width: 32px;
  height: 32px;
  color: var(--primary-color);
}

.login-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.login-header p {
  color: var(--text-secondary);
  margin: 0;
  font-size: 0.95rem;
}

.demo-notice {
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  color: white;
  padding: 1rem;
  border-radius: 0.75rem;
  margin-bottom: 1.5rem;
  text-align: center;
}

.demo-notice h4 {
  margin: 0 0 0.75rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  opacity: 0.9;
}

.credentials-options {
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.credential-option {
  flex: 1;
  padding: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.credential-option p {
  margin: 0.25rem 0;
  font-size: 0.75rem;
  font-family: 'Courier New', monospace;
}

.credential-option p:first-child {
  font-weight: 600;
  font-family: inherit;
  margin-bottom: 0.5rem;
}

.login-form {
  space-y: 1.5rem;
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

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.checkbox-label input {
  margin-right: 0.5rem;
}

.forgot-link {
  color: var(--primary-color);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
}

.forgot-link:hover {
  text-decoration: underline;
}

.login-btn {
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

.login-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.login-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

.demo-btn {
  width: 100%;
  padding: 0.75rem;
  background: transparent;
  color: var(--text-secondary);
  border: 2px dashed var(--border-color);
  border-radius: 0.75rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.demo-btn:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
  background: rgba(59, 130, 246, 0.05);
}

.demo-btn.secondary {
  border-color: var(--text-secondary);
  color: var(--text-secondary);
}

.demo-btn.secondary:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
  background: rgba(59, 130, 246, 0.05);
}

.login-footer {
  text-align: center;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.login-footer p {
  color: var(--text-secondary);
  font-size: 0.875rem;
  margin: 0;
}

.link {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 500;
}

.link:hover {
  text-decoration: underline;
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
[data-theme="dark"] .login-page {
  background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
}

[data-theme="dark"] .login-card {
  background: rgba(30, 41, 59, 0.8);
  border-color: rgba(71, 85, 105, 0.3);
}

/* Responsive Design */
@media (max-width: 480px) {
  .login-card {
    padding: 2rem 1.5rem;
    margin: 1rem;
  }
  
  .demo-notice {
    padding: 0.75rem;
  }
  
  .demo-notice p {
    font-size: 0.75rem;
  }
}
</style>