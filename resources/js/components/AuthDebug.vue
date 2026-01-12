<template>
  <div class="auth-debug">
    <h2>Authentication Debug</h2>
    
    <div class="debug-section">
      <h3>Local Storage</h3>
      <div class="debug-item">
        <strong>Auth Token:</strong> 
        <span v-if="authToken">{{ authToken.substring(0, 20) }}...</span>
        <span v-else class="error">Not found</span>
      </div>
      <div class="debug-item">
        <strong>User:</strong> 
        <pre v-if="storedUser">{{ JSON.stringify(storedUser, null, 2) }}</pre>
        <span v-else class="error">Not found</span>
      </div>
      <div class="debug-item">
        <strong>Tenant:</strong> 
        <pre v-if="storedTenant">{{ JSON.stringify(storedTenant, null, 2) }}</pre>
        <span v-else class="error">Not found</span>
      </div>
    </div>

    <div class="debug-section">
      <h3>App Store State</h3>
      <div class="debug-item">
        <strong>Is Authenticated:</strong> {{ appStore.isAuthenticated }}
      </div>
      <div class="debug-item">
        <strong>User:</strong> 
        <pre v-if="appStore.user">{{ JSON.stringify(appStore.user, null, 2) }}</pre>
        <span v-else class="error">Not set</span>
      </div>
      <div class="debug-item">
        <strong>Tenant:</strong> 
        <pre v-if="appStore.tenant">{{ JSON.stringify(appStore.tenant, null, 2) }}</pre>
        <span v-else class="error">Not set</span>
      </div>
    </div>

    <div class="debug-section">
      <h3>API Test</h3>
      <button @click="testDashboardAPI" :disabled="testingAPI">
        {{ testingAPI ? 'Testing...' : 'Test Dashboard API' }}
      </button>
      <div v-if="apiResult" class="api-result">
        <strong>Result:</strong>
        <pre>{{ JSON.stringify(apiResult, null, 2) }}</pre>
      </div>
    </div>

    <div class="debug-section">
      <h3>Quick Login</h3>
      <button @click="quickLogin" :disabled="loggingIn">
        {{ loggingIn ? 'Logging in...' : 'Login as admin@gmail.com' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAppStore } from '@/store/modules/app'
import api from '@/api/client'

const appStore = useAppStore()

const authToken = ref<string | null>(null)
const storedUser = ref<any>(null)
const storedTenant = ref<any>(null)
const testingAPI = ref(false)
const apiResult = ref<any>(null)
const loggingIn = ref(false)

const loadDebugInfo = () => {
  authToken.value = localStorage.getItem('auth_token')
  
  const userStr = localStorage.getItem('user')
  storedUser.value = userStr ? JSON.parse(userStr) : null
  
  const tenantStr = localStorage.getItem('tenant')
  storedTenant.value = tenantStr ? JSON.parse(tenantStr) : null
}

const testDashboardAPI = async () => {
  testingAPI.value = true
  try {
    const response = await api.get('/api/v1/dashboard/stats')
    apiResult.value = { success: true, data: response.data }
  } catch (error: any) {
    apiResult.value = { 
      success: false, 
      error: error.message,
      status: error.response?.status,
      data: error.response?.data
    }
  } finally {
    testingAPI.value = false
  }
}

const quickLogin = async () => {
  loggingIn.value = true
  try {
    const response = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        email: 'admin@gmail.com',
        password: '12345678'
      })
    })
    
    const data = await response.json()
    
    if (data.success) {
      const { user, tenant, token } = data.data
      
      // Store in localStorage
      localStorage.setItem('auth_token', token)
      localStorage.setItem('user', JSON.stringify(user))
      if (tenant) {
        localStorage.setItem('tenant', JSON.stringify(tenant))
      }
      
      // Update app store
      appStore.setUser(user)
      if (tenant) {
        appStore.setTenant(tenant)
      }
      
      // Reload debug info
      loadDebugInfo()
      
      alert('Login successful!')
    } else {
      alert('Login failed: ' + data.message)
    }
  } catch (error: any) {
    alert('Login error: ' + error.message)
  } finally {
    loggingIn.value = false
  }
}

onMounted(() => {
  loadDebugInfo()
})
</script>

<style scoped>
.auth-debug {
  padding: 2rem;
  max-width: 800px;
  margin: 0 auto;
}

.debug-section {
  margin-bottom: 2rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--card-bg);
}

.debug-section h3 {
  margin-top: 0;
  color: var(--text-primary);
}

.debug-item {
  margin-bottom: 1rem;
}

.debug-item strong {
  color: var(--text-primary);
}

.error {
  color: var(--error-color);
}

pre {
  background: var(--bg-secondary);
  padding: 0.5rem;
  border-radius: 4px;
  overflow-x: auto;
  font-size: 0.875rem;
  color: var(--text-primary);
}

button {
  background: var(--primary-color);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.api-result {
  margin-top: 1rem;
}
</style>