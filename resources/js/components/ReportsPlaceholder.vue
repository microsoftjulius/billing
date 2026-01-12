<template>
  <div class="reports-placeholder">
    <div class="placeholder-header">
      <div class="placeholder-icon">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h8c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
        </svg>
      </div>
      <h1>Reports & Analytics</h1>
      <p>Generate comprehensive reports and analytics for your ISP business</p>
    </div>

    <div class="placeholder-content">
      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
            </svg>
          </div>
          <h3>Revenue Reports</h3>
          <p>Track revenue trends, payment analytics, and financial performance across different time periods.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M16 7c0-2.21-1.79-4-4-4S8 4.79 8 7s1.79 4 4 4 4-1.79 4-4zm-4 6c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/>
            </svg>
          </div>
          <h3>Customer Analytics</h3>
          <p>Analyze customer behavior, usage patterns, and retention metrics to optimize your services.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M22 10V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v4c1.1 0 2 .9 2 2s-.9 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2z"/>
            </svg>
          </div>
          <h3>Voucher Reports</h3>
          <p>Monitor voucher sales, usage rates, and expiration patterns to optimize your voucher strategy.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
          </div>
          <h3>Network Performance</h3>
          <p>Track MikroTik router performance, bandwidth usage, and network health metrics.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
            </svg>
          </div>
          <h3>SMS Analytics</h3>
          <p>Monitor SMS delivery rates, costs, and effectiveness of your communication campaigns.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h8c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
            </svg>
          </div>
          <h3>Custom Reports</h3>
          <p>Create custom reports tailored to your specific business needs and KPIs.</p>
        </div>
      </div>

      <div class="placeholder-cta">
        <h3>Coming Soon</h3>
        <p>Advanced reporting and analytics features are currently in development. This will include:</p>
        <ul>
          <li>Interactive dashboards with real-time data</li>
          <li>Scheduled report generation and email delivery</li>
          <li>Export capabilities (PDF, Excel, CSV)</li>
          <li>Custom date ranges and filtering options</li>
          <li>Comparative analysis and trend forecasting</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Reports placeholder component
</script>

<style scoped>
.reports-placeholder {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.placeholder-header {
  text-align: center;
  margin-bottom: 3rem;
}

.placeholder-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  color: white;
}

.placeholder-icon svg {
  width: 40px;
  height: 40px;
}

.placeholder-header h1 {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
}

.placeholder-header p {
  font-size: 1.125rem;
  color: var(--text-secondary);
  margin: 0;
}

.feature-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}

.feature-card {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: var(--primary-color);
}

.feature-icon {
  width: 60px;
  height: 60px;
  background: var(--primary-color);
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  color: white;
}

.feature-icon svg {
  width: 30px;
  height: 30px;
}

.feature-card h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
}

.feature-card p {
  color: var(--text-secondary);
  line-height: 1.6;
  margin: 0;
}

.placeholder-cta {
  background: var(--hover-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 2rem;
  text-align: center;
}

.placeholder-cta h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
}

.placeholder-cta p {
  color: var(--text-secondary);
  margin: 0 0 1.5rem 0;
  font-size: 1.125rem;
}

.placeholder-cta ul {
  list-style: none;
  padding: 0;
  margin: 0;
  text-align: left;
  max-width: 600px;
  margin: 0 auto;
}

.placeholder-cta li {
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
  padding-left: 1.5rem;
  position: relative;
}

.placeholder-cta li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--primary-color);
  font-weight: 600;
}

@media (max-width: 768px) {
  .reports-placeholder {
    padding: 1rem;
  }
  
  .placeholder-header h1 {
    font-size: 2rem;
  }
  
  .feature-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  
  .feature-card {
    padding: 1.5rem;
  }
}
</style>