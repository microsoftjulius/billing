<template>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar--collapsed': sidebarCollapsed }">
      <div class="sidebar-header">
        <div class="logo">
          <h2>BillingSystem</h2>
        </div>
        <button 
          @click="toggleSidebar" 
          class="sidebar-toggle"
          :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
          </svg>
        </button>
      </div>

      <nav class="sidebar-nav">
        <ul class="nav-list">
          <li class="nav-item">
            <router-link 
              to="/app/dashboard" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
              </svg>
              <span class="nav-text">Dashboard</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/customers" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M16 7c0-2.21-1.79-4-4-4S8 4.79 8 7s1.79 4 4 4 4-1.79 4-4zm-4 6c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/>
              </svg>
              <span class="nav-text">Customers</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/vouchers" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22 10V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v4c1.1 0 2 .9 2 2s-.9 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2z"/>
              </svg>
              <span class="nav-text">Vouchers</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/payments" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
              </svg>
              <span class="nav-text">Payments</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/mikrotik" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
              <span class="nav-text">MikroTik</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/router-management" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
              <span class="nav-text">Router Management</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <router-link 
              to="/app/sms" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
              </svg>
              <span class="nav-text">SMS Config</span>
            </router-link>
          </li>
        </ul>

        <div class="nav-divider"></div>

        <ul class="nav-list">
          <li class="nav-item">
            <router-link 
              to="/app/settings" 
              class="nav-link"
              active-class="nav-link--active"
              exact-active-class="nav-link--active"
            >
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
              </svg>
              <span class="nav-text">Settings</span>
            </router-link>
          </li>
          
          <li class="nav-item">
            <button @click="logout" class="nav-link nav-link--button">
              <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
              </svg>
              <span class="nav-text">Logout</span>
            </button>
          </li>
        </ul>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info" v-if="user">
          <div class="user-avatar">
            <span>{{ user.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="user-details" v-if="!sidebarCollapsed">
            <div class="user-name">{{ user.name }}</div>
            <div class="user-role">{{ user.role }}</div>
          </div>
        </div>
        
        <div class="theme-toggle-container" v-if="!sidebarCollapsed">
          <ThemeToggle />
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Top Header -->
      <header class="top-header">
        <div class="header-left">
          <button 
            @click="toggleSidebar" 
            class="mobile-menu-toggle"
            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
          </button>
          
          <div class="breadcrumb" v-if="$route.meta.breadcrumb">
            <span 
              v-for="(crumb, index) in $route.meta.breadcrumb" 
              :key="index"
              class="breadcrumb-item"
            >
              {{ crumb }}
              <span v-if="index < $route.meta.breadcrumb.length - 1" class="breadcrumb-separator">/</span>
            </span>
          </div>
        </div>

        <div class="header-right">
          <button 
            @click="refreshPage" 
            class="refresh-btn"
            title="Refresh page"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
            </svg>
          </button>
          <ConnectionStatus />
        </div>
      </header>

      <!-- Page Content -->
      <div class="page-content">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/store/modules/app'
import ConnectionStatus from '@/components/common/ConnectionStatus.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)

const user = computed(() => appStore.user)

const toggleSidebar = () => {
  appStore.toggleSidebar()
}

const navigateTo = async (path: string) => {
  console.log('Navigating to:', path)
  try {
    await router.push(path)
    console.log('Navigation successful to:', path)
  } catch (error) {
    console.error('Navigation failed:', error)
    appStore.addErrorNotification(`Failed to navigate to ${path}`, 'Navigation Error')
  }
}

const logout = () => {
  appStore.logout()
  router.push('/login')
}

const refreshPage = () => {
  window.location.reload()
}
</script>

<style scoped>
.dashboard-layout {
  display: flex;
  min-height: 100vh;
  background: var(--bg-color);
}

/* Sidebar */
.sidebar {
  width: 280px;
  background: var(--card-bg);
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  transition: width 0.3s ease;
  position: fixed;
  height: 100vh;
  z-index: 100;
  overflow: hidden;
}

.sidebar--collapsed {
  width: 80px;
}

.sidebar-header {
  padding: 1.5rem 1rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 80px;
}

.logo h2 {
  color: var(--primary-color);
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  opacity: 1;
  transition: opacity 0.3s ease;
}

.sidebar--collapsed .logo h2 {
  opacity: 0;
}

.sidebar-toggle {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.375rem;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar-toggle:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  overflow-y: auto;
}

.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-item {
  margin-bottom: 0.25rem;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: var(--text-secondary);
  text-decoration: none;
  border-radius: 0.5rem;
  margin: 0 0.75rem;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.nav-link:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

/* Debug: Make active states more visible */
.nav-link.router-link-active {
  background: #3b82f6 !important;
  color: white !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3) !important;
}

.nav-link.router-link-exact-active {
  background: #2563eb !important;
  color: white !important;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3) !important;
}

.nav-link--active,
.nav-link.router-link-active,
.nav-link.router-link-exact-active {
  background: var(--primary-color) !important;
  color: white !important;
}

.nav-link--active:hover,
.nav-link.router-link-active:hover,
.nav-link.router-link-exact-active:hover {
  background: var(--primary-hover) !important;
}

.nav-icon {
  margin-right: 0.75rem;
  flex-shrink: 0;
  transition: margin 0.3s ease;
}

.sidebar--collapsed .nav-icon {
  margin-right: 0;
}

.nav-text {
  white-space: nowrap;
  opacity: 1;
  transition: opacity 0.3s ease;
}

.sidebar--collapsed .nav-text {
  opacity: 0;
}

.nav-divider {
  height: 1px;
  background: var(--border-color);
  margin: 1rem 0.75rem;
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid var(--border-color);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  flex-shrink: 0;
}

.user-details {
  flex: 1;
  min-width: 0;
  opacity: 1;
  transition: opacity 0.3s ease;
}

.sidebar--collapsed .user-details {
  opacity: 0;
}

.user-name {
  font-weight: 500;
  color: var(--text-primary);
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: capitalize;
}

.theme-toggle-container {
  opacity: 1;
  transition: opacity 0.3s ease;
}

.sidebar--collapsed .theme-toggle-container {
  opacity: 0;
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 280px;
  transition: margin-left 0.3s ease;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.sidebar--collapsed + .main-content {
  margin-left: 80px;
}

.top-header {
  background: var(--card-bg);
  border-bottom: 1px solid var(--border-color);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 50;
  backdrop-filter: blur(10px);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.mobile-menu-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.375rem;
  transition: all 0.2s ease;
}

.mobile-menu-toggle:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.breadcrumb-item {
  color: var(--text-secondary);
}

.breadcrumb-item:last-child {
  color: var(--text-primary);
  font-weight: 500;
}

.breadcrumb-separator {
  margin: 0 0.5rem;
  color: var(--text-tertiary);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.refresh-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.375rem;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.refresh-btn:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.page-content {
  flex: 1;
  overflow: auto;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }
  
  .sidebar--mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-menu-toggle {
    display: flex;
  }
  
  .breadcrumb {
    display: none;
  }
}

/* Tablet */
@media (max-width: 1024px) {
  .sidebar {
    width: 240px;
  }
  
  .main-content {
    margin-left: 240px;
  }
  
  .sidebar--collapsed {
    width: 70px;
  }
  
  .sidebar--collapsed + .main-content {
    margin-left: 70px;
  }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
  .sidebar,
  .main-content,
  .nav-icon,
  .nav-text,
  .user-details,
  .theme-toggle-container {
    transition: none;
  }
}

/* Focus styles */
.nav-link:focus-visible,
.sidebar-toggle:focus-visible,
.mobile-menu-toggle:focus-visible {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Dark theme adjustments */
[data-theme="dark"] .sidebar {
  background: var(--card-bg);
  border-color: var(--border-color);
}

[data-theme="dark"] .top-header {
  background: var(--card-bg);
  border-color: var(--border-color);
}
</style>