<template>
  <div class="landing-page">
    <!-- Navigation Header -->
    <nav class="navbar">
      <div class="container">
        <div class="nav-brand">
          <div class="logo">
            <svg viewBox="0 0 24 24" fill="currentColor" class="logo-icon">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <h2>NetBill Pro</h2>
          </div>
        </div>
        <div class="nav-menu">
          <a href="#features" class="nav-link">Features</a>
          <a href="#pricing" class="nav-link">Pricing</a>
          <a href="#contact" class="nav-link">Contact</a>
        </div>
        <div class="nav-actions">
          <ThemeToggle />
          <div class="account-dropdown" ref="accountDropdown">
            <button 
              @click="toggleAccountMenu" 
              class="account-btn"
              :class="{ 'account-btn--active': showAccountMenu }"
            >
              <svg viewBox="0 0 24 24" fill="currentColor" class="account-icon">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
              </svg>
              <span>Account</span>
              <svg viewBox="0 0 24 24" fill="currentColor" class="dropdown-arrow">
                <path d="M7 10l5 5 5-5z"/>
              </svg>
            </button>
            
            <div v-if="showAccountMenu" class="account-menu">
              <router-link to="/login" class="account-menu-item" @click="closeAccountMenu">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5z"/>
                </svg>
                Sign In
              </router-link>
              <router-link to="/signup" class="account-menu-item" @click="closeAccountMenu">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Sign Up
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">
            Complete Internet Billing Solution for ISPs
          </h1>
          <p class="hero-subtitle">
            Streamline your internet service business with our comprehensive billing system. 
            Features MikroTik integration, automated voucher management, SMS notifications, 
            and multi-gateway payment processing - all in one powerful platform.
          </p>
          <div class="hero-stats">
            <div class="stat">
              <div class="stat-number">500+</div>
              <div class="stat-label">Active ISPs</div>
            </div>
            <div class="stat">
              <div class="stat-number">50K+</div>
              <div class="stat-label">Customers Served</div>
            </div>
            <div class="stat">
              <div class="stat-number">99.9%</div>
              <div class="stat-label">Uptime</div>
            </div>
          </div>
          <div class="hero-actions">
            <router-link to="/signup" class="btn btn-primary btn-lg">
              Start Free Trial
            </router-link>
            <button @click="scrollToFeatures" class="btn btn-secondary btn-lg">
              Watch Demo
            </button>
          </div>
          <div class="hero-trust">
            <p>Trusted by leading ISPs across Africa</p>
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
              <div class="preview-title">NetBill Pro Dashboard</div>
            </div>
            <div class="preview-content">
              <div class="preview-sidebar">
                <div class="sidebar-item active"></div>
                <div class="sidebar-item"></div>
                <div class="sidebar-item"></div>
                <div class="sidebar-item"></div>
                <div class="sidebar-item"></div>
              </div>
              <div class="preview-main">
                <div class="preview-cards">
                  <div class="preview-card">
                    <div class="card-icon revenue"></div>
                    <div class="card-content">
                      <div class="card-title">Revenue</div>
                      <div class="card-value">$12,450</div>
                    </div>
                  </div>
                  <div class="preview-card">
                    <div class="card-icon customers"></div>
                    <div class="card-content">
                      <div class="card-title">Customers</div>
                      <div class="card-value">1,234</div>
                    </div>
                  </div>
                  <div class="preview-card">
                    <div class="card-icon vouchers"></div>
                    <div class="card-content">
                      <div class="card-title">Active Vouchers</div>
                      <div class="card-value">856</div>
                    </div>
                  </div>
                </div>
                <div class="preview-chart">
                  <div class="chart-bars">
                    <div class="bar" style="height: 60%"></div>
                    <div class="bar" style="height: 80%"></div>
                    <div class="bar" style="height: 45%"></div>
                    <div class="bar" style="height: 90%"></div>
                    <div class="bar" style="height: 70%"></div>
                    <div class="bar" style="height: 85%"></div>
                  </div>
                </div>
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
          <h2>Everything You Need to Run Your ISP</h2>
          <p>Comprehensive tools designed specifically for internet service providers</p>
        </div>
        
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <h3>MikroTik Integration</h3>
            <p>Direct API integration with MikroTik routers for automated user management, real-time monitoring, and seamless configuration management</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
              </svg>
            </div>
            <h3>SMS Automation</h3>
            <p>Automated SMS notifications for voucher delivery, payment confirmations, service alerts, and customer communications via UGSMS integration</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
              </svg>
            </div>
            <h3>Multi-Gateway Payments</h3>
            <p>Support for multiple payment gateways including CollectUG, mobile money, and bank transfers with automated reconciliation</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
              </svg>
            </div>
            <h3>Real-time Analytics</h3>
            <p>Live dashboard with comprehensive analytics, revenue tracking, customer insights, and performance metrics with exportable reports</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
              </svg>
            </div>
            <h3>Smart Voucher System</h3>
            <p>Advanced voucher generation, bulk operations, usage tracking, expiration management, and customer transfer capabilities</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <h3>Customer Management</h3>
            <p>Complete customer lifecycle management with service history, payment tracking, communication logs, and automated service provisioning</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <h3>Multi-Tenant Architecture</h3>
            <p>Support for multiple ISPs on a single platform with isolated data, custom branding, and tenant-specific configurations</p>
          </div>

          <div class="feature-card">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <h3>Modern Interface</h3>
            <p>Beautiful, responsive interface with dark/light themes, mobile optimization, and intuitive user experience designed for efficiency</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section">
      <div class="container">
        <div class="section-header">
          <h2>Simple, Transparent Pricing</h2>
          <p>Choose the plan that fits your business size and needs</p>
        </div>
        
        <div class="pricing-grid">
          <div class="pricing-card">
            <div class="pricing-header">
              <h3>Starter</h3>
              <div class="price">
                <span class="currency">$</span>
                <span class="amount">15</span>
                <span class="period">/month</span>
              </div>
              <p>Perfect for small ISPs getting started</p>
            </div>
            <div class="pricing-features">
              <ul>
                <li><span class="check">✓</span> Up to 100 customers</li>
                <li><span class="check">✓</span> 2 MikroTik routers</li>
                <li><span class="check">✓</span> Basic SMS notifications</li>
                <li><span class="check">✓</span> Single payment gateway</li>
                <li><span class="check">✓</span> Basic analytics</li>
                <li><span class="check">✓</span> Email support</li>
                <li><span class="check">✓</span> Basic voucher management</li>
                <li><span class="check">✓</span> Customer portal</li>
              </ul>
            </div>
            <div class="pricing-action">
              <router-link to="/signup?plan=starter" class="btn btn-outline btn-full">
                Start Free Trial
              </router-link>
            </div>
          </div>

          <div class="pricing-card featured">
            <div class="pricing-badge">Most Popular</div>
            <div class="pricing-header">
              <h3>Professional</h3>
              <div class="price">
                <span class="currency">$</span>
                <span class="amount">65</span>
                <span class="period">/month</span>
              </div>
              <p>Ideal for growing ISPs with advanced needs</p>
            </div>
            <div class="pricing-features">
              <ul>
                <li><span class="check">✓</span> Up to 1,000 customers</li>
                <li><span class="check">✓</span> Unlimited MikroTik routers</li>
                <li><span class="check">✓</span> Advanced SMS automation</li>
                <li><span class="check">✓</span> Multiple payment gateways</li>
                <li><span class="check">✓</span> Advanced analytics & reports</li>
                <li><span class="check">✓</span> API access</li>
                <li><span class="check">✓</span> Priority support</li>
                <li><span class="check">✓</span> Custom branding</li>
                <li><span class="check">✓</span> Bulk voucher operations</li>
                <li><span class="check">✓</span> Advanced customer management</li>
                <li><span class="check">✓</span> Payment reconciliation</li>
                <li><span class="check">✓</span> Router monitoring & alerts</li>
              </ul>
            </div>
            <div class="pricing-action">
              <router-link to="/signup?plan=professional" class="btn btn-primary btn-full">
                Start Free Trial
              </router-link>
            </div>
          </div>

          <div class="pricing-card">
            <div class="pricing-header">
              <h3>Enterprise</h3>
              <div class="price">
                <span class="currency">$</span>
                <span class="amount">199</span>
                <span class="period">/month</span>
              </div>
              <p>For large ISPs with complex requirements</p>
            </div>
            <div class="pricing-features">
              <ul>
                <li><span class="check">✓</span> Unlimited customers</li>
                <li><span class="check">✓</span> Unlimited routers</li>
                <li><span class="check">✓</span> White-label solution</li>
                <li><span class="check">✓</span> Custom integrations</li>
                <li><span class="check">✓</span> Advanced security features</li>
                <li><span class="check">✓</span> Dedicated account manager</li>
                <li><span class="check">✓</span> 24/7 phone support</li>
                <li><span class="check">✓</span> SLA guarantee</li>
                <li><span class="check">✓</span> Multi-tenant management</li>
                <li><span class="check">✓</span> Advanced reporting suite</li>
                <li><span class="check">✓</span> Custom payment flows</li>
                <li><span class="check">✓</span> Enterprise-grade monitoring</li>
              </ul>
            </div>
            <div class="pricing-action">
              <router-link to="/signup?plan=enterprise" class="btn btn-outline btn-full">
                Contact Sales
              </router-link>
            </div>
          </div>
        </div>

        <div class="pricing-note">
          <p>All plans include a 14-day free trial. No credit card required. Cancel anytime.</p>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
      <div class="container">
        <div class="contact-content">
          <div class="contact-info">
            <h2>Get in Touch</h2>
            <p>Ready to transform your ISP business? Our team is here to help you get started.</p>
            
            <div class="contact-methods">
              <div class="contact-method">
                <div class="method-icon">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                  </svg>
                </div>
                <div class="method-content">
                  <h4>Email Us</h4>
                  <p>sales@netbillpro.com</p>
                  <p>support@netbillpro.com</p>
                </div>
              </div>

              <div class="contact-method">
                <div class="method-icon">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                  </svg>
                </div>
                <div class="method-content">
                  <h4>Call Us</h4>
                  <p>+256 700 123 456</p>
                  <p>Mon-Fri 8AM-6PM EAT</p>
                </div>
              </div>

              <div class="contact-method">
                <div class="method-icon">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                  </svg>
                </div>
                <div class="method-content">
                  <h4>Visit Us</h4>
                  <p>Kampala, Uganda</p>
                  <p>East Africa</p>
                </div>
              </div>
            </div>
          </div>

          <div class="contact-form">
            <form @submit.prevent="submitContactForm" class="form">
              <div class="form-row">
                <div class="form-group">
                  <label for="firstName">First Name</label>
                  <input
                    id="firstName"
                    v-model="contactForm.firstName"
                    type="text"
                    required
                    class="form-input"
                  />
                </div>
                <div class="form-group">
                  <label for="lastName">Last Name</label>
                  <input
                    id="lastName"
                    v-model="contactForm.lastName"
                    type="text"
                    required
                    class="form-input"
                  />
                </div>
              </div>

              <div class="form-group">
                <label for="email">Email Address</label>
                <input
                  id="email"
                  v-model="contactForm.email"
                  type="email"
                  required
                  class="form-input"
                />
              </div>

              <div class="form-group">
                <label for="company">Company Name</label>
                <input
                  id="company"
                  v-model="contactForm.company"
                  type="text"
                  class="form-input"
                />
              </div>

              <div class="form-group">
                <label for="message">Message</label>
                <textarea
                  id="message"
                  v-model="contactForm.message"
                  rows="4"
                  required
                  class="form-input"
                  placeholder="Tell us about your ISP and how we can help..."
                ></textarea>
              </div>

              <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-full">
                <span v-if="isSubmitting">Sending...</span>
                <span v-else>Send Message</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-content">
          <h2>Ready to Transform Your ISP Business?</h2>
          <p>Join hundreds of ISPs already using NetBill Pro to streamline their operations and grow their revenue</p>
          <div class="cta-actions">
            <router-link to="/signup" class="btn btn-primary btn-lg">
              Start Free Trial
            </router-link>
            <router-link to="/login" class="btn btn-secondary btn-lg">
              Sign In
            </router-link>
          </div>
          <div class="cta-features">
            <div class="cta-feature">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span>14-day free trial</span>
            </div>
            <div class="cta-feature">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span>No credit card required</span>
            </div>
            <div class="cta-feature">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span>Cancel anytime</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-brand">
            <div class="logo">
              <svg viewBox="0 0 24 24" fill="currentColor" class="logo-icon">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <h3>NetBill Pro</h3>
            </div>
            <p>The complete internet billing solution for modern ISPs. Streamline operations, increase revenue, and delight customers.</p>
            <div class="social-links">
              <a href="#" class="social-link">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                </svg>
              </a>
              <a href="#" class="social-link">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                </svg>
              </a>
              <a href="#" class="social-link">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
              </a>
            </div>
          </div>
          <div class="footer-links">
            <div class="footer-section">
              <h4>Product</h4>
              <ul>
                <li><a href="#features">Features</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#">API Documentation</a></li>
                <li><a href="#">Integrations</a></li>
                <li><a href="#">Security</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Support</h4>
              <ul>
                <li><a href="#">Help Center</a></li>
                <li><a href="#contact">Contact Us</a></li>
                <li><a href="#">System Status</a></li>
                <li><a href="#">Training</a></li>
                <li><a href="#">Community</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Company</h4>
              <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Press</a></li>
                <li><a href="#">Partners</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Legal</h4>
              <ul>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Cookie Policy</a></li>
                <li><a href="#">GDPR</a></li>
              </ul>
            </div>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2024 NetBill Pro. All rights reserved.</p>
          <div class="footer-bottom-links">
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
            <a href="#">Cookies</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

const contactForm = ref({
  firstName: '',
  lastName: '',
  email: '',
  company: '',
  message: ''
})

const isSubmitting = ref(false)
const showAccountMenu = ref(false)
const accountDropdown = ref<HTMLElement | null>(null)

const toggleAccountMenu = () => {
  showAccountMenu.value = !showAccountMenu.value
}

const closeAccountMenu = () => {
  showAccountMenu.value = false
}

const handleClickOutside = (event: MouseEvent) => {
  if (accountDropdown.value && !accountDropdown.value.contains(event.target as Node)) {
    closeAccountMenu()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const scrollToFeatures = () => {
  const featuresSection = document.getElementById('features')
  if (featuresSection) {
    featuresSection.scrollIntoView({ behavior: 'smooth' })
  }
}

const submitContactForm = async () => {
  try {
    isSubmitting.value = true
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    // Reset form
    contactForm.value = {
      firstName: '',
      lastName: '',
      email: '',
      company: '',
      message: ''
    }
    
    alert('Thank you for your message! We\'ll get back to you soon.')
  } catch (error) {
    alert('Sorry, there was an error sending your message. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
/* Landing page specific styles */
.landing-page {
  min-height: 100vh;
  overflow-x: hidden;
}

/* Ensure smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Navigation */
.navbar {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border-color);
  padding: 1rem 0;
  position: sticky;
  top: 0;
  z-index: 100;
  transition: all 0.3s ease;
}

[data-theme="dark"] .navbar {
  background: rgba(15, 23, 42, 0.95);
}

.navbar .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-brand .logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.logo-icon {
  width: 32px;
  height: 32px;
  color: var(--primary-color);
}

.nav-brand h2 {
  margin: 0;
  color: var(--primary-color);
  font-weight: 700;
  font-size: 1.5rem;
}

.nav-menu {
  display: flex;
  gap: 2rem;
  align-items: center;
}

.nav-link {
  color: var(--text-secondary);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.nav-link:hover {
  color: var(--primary-color);
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* Account Dropdown */
.account-dropdown {
  position: relative;
}

.account-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: transparent;
  border: 2px solid var(--primary-color);
  border-radius: 0.5rem;
  color: var(--primary-color);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
}

.account-btn:hover,
.account-btn--active {
  background: var(--primary-color);
  color: white;
}

.account-icon {
  width: 18px;
  height: 18px;
}

.dropdown-arrow {
  width: 16px;
  height: 16px;
  transition: transform 0.2s ease;
}

.account-btn--active .dropdown-arrow {
  transform: rotate(180deg);
}

.account-menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  min-width: 180px;
  z-index: 1000;
  overflow: hidden;
  backdrop-filter: blur(10px);
}

[data-theme="dark"] .account-menu {
  background: rgba(30, 41, 59, 0.95);
  border-color: rgba(71, 85, 105, 0.3);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.account-menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  color: var(--text-primary);
  text-decoration: none;
  transition: all 0.2s ease;
  font-weight: 500;
  font-size: 0.875rem;
}

.account-menu-item:hover {
  background: var(--hover-bg);
  color: var(--primary-color);
}

.account-menu-item svg {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.account-menu-item:not(:last-child) {
  border-bottom: 1px solid var(--border-color);
}

.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  border: 2px solid transparent;
  cursor: pointer;
  font-size: 0.875rem;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
}

.btn-outline {
  background: transparent;
  color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-outline:hover {
  background: var(--primary-color);
  color: white;
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.2);
}

.btn-lg {
  padding: 1rem 2rem;
  font-size: 1rem;
}

.btn-full {
  width: 100%;
}

/* Hero Section */
.hero-section {
  padding: 6rem 0;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
  overflow: hidden;
  position: relative;
}

.hero-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 0.3;
}

.hero-section .container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  position: relative;
  z-index: 1;
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

.hero-stats {
  display: flex;
  gap: 2rem;
  margin: 2rem 0;
}

.stat {
  text-align: center;
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

.hero-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.hero-trust {
  opacity: 0.8;
  font-size: 0.875rem;
}

/* Dashboard Preview */
.dashboard-preview {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 1rem;
  overflow: hidden;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.preview-header {
  padding: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
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

.preview-title {
  font-size: 0.875rem;
  opacity: 0.8;
}

.preview-content {
  display: flex;
  height: 300px;
}

.preview-sidebar {
  width: 80px;
  background: rgba(255, 255, 255, 0.05);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  padding: 1rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.sidebar-item {
  height: 32px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.25rem;
}

.sidebar-item.active {
  background: rgba(255, 255, 255, 0.3);
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
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.card-icon {
  width: 32px;
  height: 32px;
  border-radius: 0.25rem;
}

.card-icon.revenue {
  background: #10b981;
}

.card-icon.customers {
  background: #3b82f6;
}

.card-icon.vouchers {
  background: #f59e0b;
}

.card-content {
  flex: 1;
}

.card-title {
  font-size: 0.75rem;
  opacity: 0.8;
  margin-bottom: 0.25rem;
}

.card-value {
  font-size: 1rem;
  font-weight: 600;
}

.preview-chart {
  height: 120px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  align-items: end;
  justify-content: center;
}

.chart-bars {
  display: flex;
  align-items: end;
  gap: 0.5rem;
  height: 80px;
}

.bar {
  width: 20px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 2px;
  transition: height 0.3s ease;
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

/* Pricing Section */
.pricing-section {
  padding: 6rem 0;
  background: var(--hover-bg);
}

.pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}

.pricing-card {
  background: var(--card-bg);
  border: 2px solid var(--border-color);
  border-radius: 1rem;
  padding: 2rem;
  position: relative;
  transition: all 0.3s ease;
}

.pricing-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}

.pricing-card.featured {
  border-color: var(--primary-color);
  transform: scale(1.05);
}

.pricing-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--primary-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.pricing-header {
  text-align: center;
  margin-bottom: 2rem;
}

.pricing-header h3 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.price {
  display: flex;
  align-items: baseline;
  justify-content: center;
  margin-bottom: 1rem;
}

.currency {
  font-size: 1.25rem;
  color: var(--text-secondary);
}

.amount {
  font-size: 3rem;
  font-weight: 800;
  color: var(--text-primary);
}

.period {
  font-size: 1rem;
  color: var(--text-secondary);
}

.pricing-header p {
  color: var(--text-secondary);
  margin: 0;
}

.pricing-features ul {
  list-style: none;
  padding: 0;
  margin: 0 0 2rem 0;
}

.pricing-features li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  color: var(--text-secondary);
}

.check {
  color: var(--primary-color);
  font-weight: 600;
}

.pricing-note {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.875rem;
}

/* Contact Section */
.contact-section {
  padding: 6rem 0;
  background: var(--bg-color);
}

.contact-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: start;
}

.contact-info h2 {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.contact-info p {
  font-size: 1.125rem;
  color: var(--text-secondary);
  margin: 0 0 3rem 0;
}

.contact-methods {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.contact-method {
  display: flex;
  gap: 1rem;
  align-items: start;
}

.method-icon {
  width: 48px;
  height: 48px;
  background: var(--primary-color);
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.method-icon svg {
  width: 24px;
  height: 24px;
}

.method-content h4 {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.method-content p {
  color: var(--text-secondary);
  margin: 0.25rem 0;
  font-size: 0.875rem;
}

.contact-form {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 2rem;
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
  border-radius: 0.5rem;
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 1rem;
  transition: all 0.2s ease;
  font-family: inherit;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

textarea.form-input {
  resize: vertical;
  min-height: 100px;
}

/* CTA Section */
.cta-section {
  padding: 6rem 0;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
  text-align: center;
}

.cta-content h2 {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0 0 1rem 0;
}

.cta-content p {
  font-size: 1.125rem;
  margin: 0 0 2rem 0;
  opacity: 0.9;
}

.cta-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 3rem;
}

.cta-features {
  display: flex;
  gap: 2rem;
  justify-content: center;
  flex-wrap: wrap;
}

.cta-feature {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  opacity: 0.9;
}

.cta-feature svg {
  width: 16px;
  height: 16px;
}

/* Footer */
.footer {
  background: var(--card-bg);
  border-top: 1px solid var(--border-color);
  padding: 4rem 0 2rem;
}

.footer-content {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 4rem;
  margin-bottom: 3rem;
}

.footer-brand .logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.footer-brand h3 {
  color: var(--primary-color);
  margin: 0;
  font-size: 1.25rem;
}

.footer-brand p {
  color: var(--text-secondary);
  margin: 0 0 2rem 0;
  line-height: 1.6;
}

.social-links {
  display: flex;
  gap: 1rem;
}

.social-link {
  width: 40px;
  height: 40px;
  background: var(--hover-bg);
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  transition: all 0.2s;
}

.social-link:hover {
  background: var(--primary-color);
  color: white;
}

.social-link svg {
  width: 20px;
  height: 20px;
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
  font-size: 0.875rem;
}

.footer-section a:hover {
  color: var(--primary-color);
}

.footer-bottom {
  border-top: 1px solid var(--border-color);
  padding-top: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-bottom p {
  color: var(--text-tertiary);
  margin: 0;
  font-size: 0.875rem;
}

.footer-bottom-links {
  display: flex;
  gap: 2rem;
}

.footer-bottom-links a {
  color: var(--text-tertiary);
  text-decoration: none;
  font-size: 0.875rem;
  transition: color 0.2s;
}

.footer-bottom-links a:hover {
  color: var(--primary-color);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .hero-section .container {
    grid-template-columns: 1fr;
    gap: 3rem;
    text-align: center;
  }

  .hero-stats {
    justify-content: center;
  }

  .contact-content {
    grid-template-columns: 1fr;
    gap: 3rem;
  }

  .pricing-card.featured {
    transform: none;
  }
}

@media (max-width: 768px) {
  .nav-menu {
    display: none;
  }

  .nav-actions {
    gap: 0.5rem;
  }

  .account-btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
  }

  .account-btn span {
    display: none;
  }

  .account-menu {
    right: -0.5rem;
    min-width: 160px;
  }

  .hero-title {
    font-size: 2.5rem;
  }

  .hero-stats {
    flex-direction: column;
    gap: 1rem;
  }

  .hero-actions {
    justify-content: center;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .pricing-grid {
    grid-template-columns: 1fr;
  }

  .footer-content {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .footer-bottom {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }

  .btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
  }

  .cta-actions {
    flex-direction: column;
    align-items: center;
  }

  .cta-features {
    flex-direction: column;
    gap: 1rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .hero-title {
    font-size: 2rem;
  }

  .section-header h2 {
    font-size: 2rem;
  }

  .pricing-card {
    padding: 1.5rem;
  }

  .contact-form {
    padding: 1.5rem;
  }

  .feature-card {
    padding: 1.5rem;
  }
}
</style>