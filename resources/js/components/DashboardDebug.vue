<template>
  <div class="dashboard-debug">
    <h1>Dashboard Debug</h1>
    
    <div class="debug-section">
      <h2>Authentication Status</h2>
      <p>Token: {{ authToken ? 'Present' : 'Missing' }}</p>
      <p>User: {{ user ? user.name : 'Not logged in' }}</p>
    </div>

    <div class="debug-section">
      <h2>API Test</h2>
      <button @click="testDashboardAPI" :disabled="loading">
        {{ loading ? 'Loading...' : 'Test Dashboard API' }}
      </button>
      
      <div v-if="apiResponse" class="api-response">
        <h3>API Response:</h3>
        <pre>{{ JSON.stringify(apiResponse, null, 2) }}</pre>
      </div>
      
      <div v-if="apiError" class="api-error">
        <h3>API Error:</h3>
        <pre>{{ JSON.stringify(apiError, null, 2) }}</pre>
      </div>
    </div>

    <div class="debug-section">
      <h2>Stats Data</h2>
      <div v-if="stats">
        <h3>Overview:</h3>
        <ul>
          <li>Total Tenants: {{ stats.overview?.total_tenants || 0 }}</li>
          <li>Total Customers: {{ stats.overview?.total_customers || 0 }}</li>
          <li>Total Payments: {{ stats.overview?.total_payments || 0 }}</li>
          <li>Total Revenue: {{ stats.overview?.total_revenue || 0 }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useAppStore } from '@/store/modules/app';
import api from '@/api/client';

const appStore = useAppStore();
const loading = ref(false);
const apiResponse = ref(null);
const apiError = ref(null);
const stats = ref(null);

const authToken = computed(() => localStorage.getItem('auth_token'));
const user = computed(() => appStore.user);

const testDashboardAPI = async () => {
  try {
    loading.value = true;
    apiError.value = null;
    
    console.log('Testing dashboard API...');
    console.log('Auth token:', authToken.value ? 'Present' : 'Missing');
    
    const response = await api.get('/api/v1/dashboard/stats');
    
    console.log('Dashboard API response:', response);
    
    apiResponse.value = response.data;
    stats.value = response.data.data;
    
  } catch (error) {
    console.error('Dashboard API error:', error);
    apiError.value = {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    };
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  console.log('DashboardDebug mounted');
  console.log('Auth token:', authToken.value);
  console.log('User:', user.value);
});
</script>

<style scoped>
.dashboard-debug {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.debug-section {
  margin-bottom: 2rem;
  padding: 1rem;
  border: 1px solid #ddd;
  border-radius: 0.5rem;
  background: #f9f9f9;
}

.debug-section h2 {
  margin-top: 0;
  color: #333;
}

.api-response,
.api-error {
  margin-top: 1rem;
}

.api-response pre,
.api-error pre {
  background: #fff;
  padding: 1rem;
  border-radius: 0.25rem;
  overflow-x: auto;
  font-size: 0.875rem;
}

.api-error pre {
  background: #fee;
  color: #c00;
}

button {
  padding: 0.5rem 1rem;
  background: #007bff;
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
}

button:disabled {
  background: #ccc;
  cursor: not-allowed;
}

ul {
  list-style-type: none;
  padding: 0;
}

li {
  padding: 0.25rem 0;
  border-bottom: 1px solid #eee;
}
</style>