<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-card">
        <div class="login-header">
          <div class="logo">
            <h1>BillingSystem</h1>
          </div>
          <h2>Welcome Back</h2>
          <p>Sign in to access your billing dashboard</p>
        </div>
        
        <!-- Demo Credentials Notice -->
        <div class="demo-notice">
          <h4>Demo Credentials</h4>
          <p><strong>Email:</strong> admin@billing.com</p>
          <p><strong>Password:</strong> password123</p>
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
            Use Demo Credentials
          </button>
        </form>
        
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
  form.value.email = 'admin@billing.com';
  form.value.password = 'password123';
  handleLogin();
};

const handleLogin = async () => {
  try {
    isLoading.value = true;
    error.value = '';
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Demo login - accept any credentials for now
    if (form.value.email && form.value.password) {
      // Create a demo user
      const demoUser = {
        id: 1,
        name: 'Demo Admin',
        email: form.value.email,
        role: 'admin',
        avatar: null,
        token: 'demo-token-' + Date.now()
      };
      
      // Set user in store
      appStore.setUser(demoUser);
      
      // Store token in localStorage
      localStorage.setItem('auth_token', demoUser.token);
      localStorage.setItem('user', JSON.stringify(demoUser));
      
      // Redirect to dashboard
      router.push('/app/dashboard');
      
      appStore.addSuccessNotification(
        `Welcome back, ${demoUser.name}!`,
        'Login Successful'
      );
    } else {
      throw new Error('Please enter both email and password');
    }
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

// Auto-fill demo credentials on component mount
form.value.email = 'admin@billing.com';
form.value.password = 'password123';
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
  margin: 0 0 0.5rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  opacity: 0.9;
}

.demo-notice p {
  margin: 0.25rem 0;
  font-size: 0.8rem;
  font-family: 'Courier New', monospace;
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