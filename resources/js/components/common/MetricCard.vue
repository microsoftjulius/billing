<template>
  <div class="metric-card" :class="{ loading }">
    <div class="metric-icon" :class="color">
      <i :class="icon"></i>
    </div>
    
    <div class="metric-content">
      <div class="metric-value">
        <span v-if="loading" class="loading-placeholder"></span>
        <span v-else>{{ value }}</span>
      </div>
      
      <div class="metric-title">{{ title }}</div>
      
      <div v-if="subtitle" class="metric-subtitle">
        {{ subtitle }}
      </div>
      
      <div v-if="change !== undefined && !loading" class="metric-change">
        <i 
          class="fas" 
          :class="{
            'fa-arrow-up': change > 0,
            'fa-arrow-down': change < 0,
            'fa-minus': change === 0
          }"
        ></i>
        <span>{{ Math.abs(change) }}%</span>
        <span class="change-label">
          {{ change > 0 ? 'increase' : change < 0 ? 'decrease' : 'no change' }}
        </span>
      </div>
    </div>
    
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  title: string;
  value: string | number;
  change?: number;
  subtitle?: string;
  icon: string;
  color: 'green' | 'blue' | 'purple' | 'orange' | 'red' | 'yellow';
  loading?: boolean;
}

defineProps<Props>();
</script>

<style scoped>
.metric-card {
  background: var(--card-bg);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  position: relative;
  transition: all 0.3s ease;
  overflow: hidden;
}

.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.metric-card.loading {
  pointer-events: none;
}

.metric-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.metric-icon.green {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.metric-icon.blue {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.metric-icon.purple {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  color: white;
}

.metric-icon.orange {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.metric-icon.red {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.metric-icon.yellow {
  background: linear-gradient(135deg, #eab308, #ca8a04);
  color: white;
}

.metric-content {
  flex: 1;
  min-width: 0;
}

.metric-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
  line-height: 1.2;
}

.metric-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
}

.metric-subtitle {
  font-size: 0.75rem;
  color: var(--text-tertiary);
  margin-bottom: 0.5rem;
}

.metric-change {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.metric-change i {
  font-size: 0.625rem;
}

.metric-change .fa-arrow-up {
  color: #10b981;
}

.metric-change .fa-arrow-down {
  color: #ef4444;
}

.metric-change .fa-minus {
  color: #6b7280;
}

.change-label {
  color: var(--text-tertiary);
  margin-left: 0.25rem;
}

.loading-placeholder {
  display: inline-block;
  width: 120px;
  height: 2rem;
  background: linear-gradient(90deg, var(--border-color) 25%, var(--hover-bg) 50%, var(--border-color) 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
  border-radius: 0.25rem;
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid var(--border-color);
  border-top: 2px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* Dark theme adjustments */
[data-theme="dark"] .loading-overlay {
  background: rgba(0, 0, 0, 0.8);
}

/* Responsive design */
@media (max-width: 640px) {
  .metric-card {
    padding: 1rem;
  }
  
  .metric-icon {
    width: 48px;
    height: 48px;
    font-size: 1.25rem;
  }
  
  .metric-value {
    font-size: 1.5rem;
  }
}
</style>