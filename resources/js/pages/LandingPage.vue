<template>
  <div class="landing-page">
    <!-- Navigation Header -->
    <nav class="navbar">
      <div class="container">
        <div class="nav-brand">
          <h2>BillingSystem</h2>
        </div>
        <div class="nav-actions">
          <ThemeToggle />
          <router-link to="/login" class="btn btn-primary">
            Sign In
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">
            Modern Internet Billing System
          </h1>
          <p class="hero-subtitle">
            Complete billing solution with MikroTik integration, real-time monitoring, 
            SMS notifications, and payment gateway support for ISPs and internet cafes.
          </p>
          <div class="hero-actions">
            <router-link to="/login" class="btn btn-primary btn-lg">
              Get Started
            </router-link>
            <button @click="scrollToFeatures" class="btn btn-secondary btn-lg">
              Learn More
            </button>
          </div>
        </div>
        <div class="hero-image">
          <div class="dashboard-preview">
            <div class="preview-header">
              <div class="preview-dots">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
            <div class="preview-content">
              <div class="preview-sidebar"></div>
              <div class="preview-main">
                <div class="preview-cards">
                  <div class="preview-card"></div>
                  <div class="preview-card"></div>
                  <div class="preview-card"></div>
                </div>
                <div class="preview-chart"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
      <div class="container">
        <div class="section-header">
          <h2>Powerful Features</h2>
          <p>Everything you need to manage your internet billing business</p>
        </div>
        
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <h3>MikroTik Integration</h3>
            <p>Seamless integration with MikroTik routers for automated user management and real-time monitoring</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
              </svg>
            </div>
            <h3>SMS Notifications</h3>
            <p>Automated SMS notifications for voucher delivery, payment confirmations, and service updates</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
              </svg>
            </div>
            <h3>Payment Gateways</h3>
            <p>Multiple payment gateway support including CollectUG for seamless payment processing</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
              </svg>
            </div>
            <h3>Real-time Analytics</h3>
            <p>Live dashboard with real-time statistics, revenue tracking, and customer usage analytics</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
              </svg>
            </div>
            <h3>Voucher Management</h3>
            <p>Generate, track, and manage internet vouchers with bulk operations and usage monitoring</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <h3>Modern Interface</h3>
            <p>Beautiful, responsive interface with dark/light themes and mobile-friendly design</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-content">
          <h2>Ready to Get Started?</h2>
          <p>Join thousands of ISPs using our billing system to manage their business efficiently</p>
          <router-link to="/login" class="btn btn-primary btn-lg">
            Start Your Free Trial
          </router-link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-brand">
            <h3>BillingSystem</h3>
            <p>Modern internet billing solution</p>
          </div>
          <div class="footer-links">
            <div class="footer-section">
              <h4>Product</h4>
              <ul>
                <li><a href="#features">Features</a></li>
                <li><a href="#">Pricing</a></li>
                <li><a href="#">Documentation</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Support</h4>
              <ul>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Contact Us</a></li>
                <li><a href="#">API Reference</a></li>
              </ul>
            </div>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2024 BillingSystem. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import ThemeToggle from '@/components/common/ThemeToggle.vue'

const scrollToFeatures = () => {
  const featuresSection = document.getElementById('features')
  if (featuresSection) {
    featuresSection.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<style scoped>
/* Landing page specific styles */
.landing-page {
  min-height: 100vh;
  overflow-x: hidden; /* Prevent horizontal scroll */
}

/* Ensure smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Fix any potential scroll issues */
body {
  overflow-x: hidden;
}

/* Navigation */
.navbar {
  background: var(--card-bg);
  border-bottom: 1px solid var(--border-color);
  padding: 1rem 0;
  position: sticky;
  top: 0;
  z-index: 100;
  backdrop-filter: blur(10px);
}

.navbar .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-brand h2 {
  margin: 0;
  color: var(--primary-color);
  font-weight: 700;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* Hero Section */
.hero-section {
  padding: 4rem 0;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
  overflow: hidden;
}

.hero-section .container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
}

.hero-title {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.1;
  margin: 0 0 1.5rem 0;
}

.hero-subtitle {
  font-size: 1.25rem;
  line-height: 1.6;
  margin: 0 0 2rem 0;
  opacity: 0.9;
}

.hero-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-lg {
  padding: 1rem 2rem;
  font-size: 1.125rem;
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Dashboard Preview */
.dashboard-preview {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 1rem;
  overflow: hidden;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.preview-header {
  padding: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.preview-dots {
  display: flex;
  gap: 0.5rem;
}

.preview-dots span {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
}

.preview-content {
  display: flex;
  height: 300px;
}

.preview-sidebar {
  width: 80px;
  background: rgba(255, 255, 255, 0.05);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.preview-main {
  flex: 1;
  padding: 1rem;
}

.preview-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.preview-card {
  height: 60px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
}

.preview-chart {
  height: 150px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 0.5rem;
}

/* Features Section */
.features-section {
  padding: 6rem 0;
  background: var(--bg-color);
}

.section-header {
  text-align: center;
  margin-bottom: 4rem;
}

.section-header h2 {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.section-header p {
  font-size: 1.125rem;
  color: var(--text-secondary);
  margin: 0;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
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
  margin: 0 auto 1.5rem;
  background: var(--primary-color);
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.feature-icon svg {
  width: 30px;
  height: 30px;
}

.feature-card h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.feature-card p {
  color: var(--text-secondary);
  line-height: 1.6;
  margin: 0;
}

/* CTA Section */
.cta-section {
  padding: 4rem 0;
  background: var(--hover-bg);
  text-align: center;
}

.cta-content h2 {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.cta-content p {
  font-size: 1.125rem;
  color: var(--text-secondary);
  margin: 0 0 2rem 0;
}

/* Footer */
.footer {
  background: var(--card-bg);
  border-top: 1px solid var(--border-color);
  padding: 3rem 0 1rem;
}

.footer-content {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 3rem;
  margin-bottom: 2rem;
}

.footer-brand h3 {
  color: var(--primary-color);
  margin: 0 0 0.5rem 0;
}

.footer-brand p {
  color: var(--text-secondary);
  margin: 0;
}

.footer-links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 2rem;
}

.footer-section h4 {
  color: var(--text-primary);
  margin: 0 0 1rem 0;
  font-weight: 600;
}

.footer-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-section li {
  margin-bottom: 0.5rem;
}

.footer-section a {
  color: var(--text-secondary);
  text-decoration: none;
  transition: color 0.2s;
}

.footer-section a:hover {
  color: var(--primary-color);
}

.footer-bottom {
  border-top: 1px solid var(--border-color);
  padding-top: 1rem;
  text-align: center;
}

.footer-bottom p {
  color: var(--text-tertiary);
  margin: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .hero-section .container {
    grid-template-columns: 1fr;
    gap: 2rem;
    text-align: center;
  }

  .hero-title {
    font-size: 2.5rem;
  }

  .hero-actions {
    justify-content: center;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .footer-content {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .nav-actions {
    gap: 0.5rem;
  }

  .btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
  }
}

@media (max-width: 480px) {
  .hero-title {
    font-size: 2rem;
  }

  .hero-actions {
    flex-direction: column;
    width: 100%;
  }

  .hero-actions .btn {
    width: 100%;
  }
}
</style>