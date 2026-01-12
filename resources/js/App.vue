<template>
    <div id="app" :data-theme="currentTheme">
        <router-view />
        <NotificationCenter />
        <LoadingOverlay :active="isLoading" />
        
        <!-- Connection Status Indicator -->
        <div class="connection-status-container">
            <ConnectionStatus />
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/store/modules/app'
import { webSocketService } from '@/services/websocket'
import { setupGlobalErrorHandling } from '@/services/errorHandler'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'
import ConnectionStatus from '@/components/common/ConnectionStatus.vue'

const appStore = useAppStore()
const { currentTheme, isLoading } = storeToRefs(appStore)

onMounted(() => {
    try {
        // Initialize app
        appStore.initializeApp()
        
        // Setup global error handling (but don't let it crash the app)
        try {
            setupGlobalErrorHandling()
        } catch (error) {
            console.warn('Global error handling setup failed:', error)
        }
        
        // Initialize WebSocket connection after app is ready (with error handling)
        setTimeout(() => {
            try {
                webSocketService.initialize()
            } catch (error) {
                console.warn('WebSocket initialization failed:', error)
                // Don't let WebSocket errors crash the app
            }
        }, 1000)
    } catch (error) {
        console.error('App initialization error:', error)
        // Don't let initialization errors crash the app - just log them
    }
})

onUnmounted(() => {
    // Clean up WebSocket connection
    webSocketService.disconnect()
})
</script>

<style>
/* Global styles */
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    line-height: 1.6;
}

#app {
    min-height: 100vh;
    transition: background-color 0.3s ease, color 0.3s ease;
}

/* Enhanced Theme Variables */
:root {
    /* Brand Colors */
    --primary-color: #3b82f6;
    --primary-hover: #2563eb;
    --primary-light: #60a5fa;
    --secondary-color: #6366f1;
    --accent-color: #8b5cf6;
    
    /* Status Colors */
    --success-color: #10b981;
    --success-light: #34d399;
    --warning-color: #f59e0b;
    --warning-light: #fbbf24;
    --error-color: #ef4444;
    --error-light: #f87171;
    --info-color: #06b6d4;
    --info-light: #22d3ee;
    
    /* Neutral Colors - will be overridden by theme */
    --bg-color: #ffffff;
    --card-bg: #ffffff;
    --hover-bg: #f8fafc;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-tertiary: #9ca3af;
    --border-color: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    
    /* Interactive States */
    --hover-color: rgba(59, 130, 246, 0.1);
    --active-color: rgba(59, 130, 246, 0.2);
    --focus-color: rgba(59, 130, 246, 0.3);
    
    /* Spacing and Layout */
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-2xl: 3rem;
    
    /* Border Radius */
    --radius-sm: 0.25rem;
    --radius-md: 0.375rem;
    --radius-lg: 0.5rem;
    --radius-xl: 0.75rem;
    
    /* Transitions */
    --transition-fast: 0.15s ease;
    --transition-normal: 0.2s ease;
    --transition-slow: 0.3s ease;
}

/* Light Theme */
[data-theme="light"] {
    --bg-color: #ffffff;
    --card-bg: #ffffff;
    --hover-bg: #f8fafc;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-tertiary: #9ca3af;
    --border-color: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --hover-color: rgba(59, 130, 246, 0.1);
    --active-color: rgba(59, 130, 246, 0.2);
    --focus-color: rgba(59, 130, 246, 0.3);
}

/* Dark Theme */
[data-theme="dark"] {
    --bg-color: #0f172a;
    --card-bg: #1e293b;
    --hover-bg: #334155;
    --text-primary: #f8fafc;
    --text-secondary: #cbd5e1;
    --text-tertiary: #94a3b8;
    --border-color: #475569;
    --border-light: #64748b;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
    --hover-color: rgba(59, 130, 246, 0.2);
    --active-color: rgba(59, 130, 246, 0.3);
    --focus-color: rgba(59, 130, 246, 0.4);
}

/* Apply theme to body */
body {
    background-color: var(--bg-color);
    color: var(--text-primary);
    transition: background-color var(--transition-slow), color var(--transition-slow);
}

/* Enhanced Utility Classes */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-sm) var(--spacing-md);
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all var(--transition-normal);
    position: relative;
    overflow: hidden;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background-color: var(--card-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
    background-color: var(--hover-color);
    border-color: var(--primary-color);
}

.btn-ghost {
    background-color: transparent;
    color: var(--text-secondary);
}

.btn-ghost:hover:not(:disabled) {
    background-color: var(--hover-color);
    color: var(--text-primary);
}

.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: var(--spacing-lg);
    transition: all var(--transition-normal);
}

.card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.card-header {
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--border-light);
    margin-bottom: var(--spacing-md);
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: var(--spacing-xs) 0 0 0;
}

/* Text Utilities */
.text-primary { color: var(--primary-color); }
.text-secondary { color: var(--secondary-color); }
.text-success { color: var(--success-color); }
.text-warning { color: var(--warning-color); }
.text-error { color: var(--error-color); }
.text-info { color: var(--info-color); }
.text-muted { color: var(--text-tertiary); }

/* Background Utilities */
.bg-primary { background-color: var(--primary-color); }
.bg-secondary { background-color: var(--card-bg); }
.bg-success { background-color: var(--success-color); }
.bg-warning { background-color: var(--warning-color); }
.bg-error { background-color: var(--error-color); }
.bg-info { background-color: var(--info-color); }

/* Border Utilities */
.border { border: 1px solid var(--border-color); }
.border-light { border: 1px solid var(--border-light); }
.border-primary { border-color: var(--primary-color); }

/* Shadow Utilities */
.shadow-sm { box-shadow: var(--shadow-sm); }
.shadow { box-shadow: var(--shadow-md); }
.shadow-lg { box-shadow: var(--shadow-lg); }
.shadow-xl { box-shadow: var(--shadow-lg); }

/* Layout Utilities */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--spacing-md);
}

.flex { display: flex; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
.justify-between { justify-content: space-between; }
.gap-sm { gap: var(--spacing-sm); }
.gap-md { gap: var(--spacing-md); }
.gap-lg { gap: var(--spacing-lg); }

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 0 var(--spacing-sm);
    }
    
    .card {
        padding: var(--spacing-md);
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    :root {
        --color-border: #000000;
        --color-shadow: rgba(0, 0, 0, 0.5);
    }
    
    [data-theme="dark"] {
        --color-border: #ffffff;
        --color-text: #ffffff;
        --color-bg: #000000;
    }
}

/* Focus Visible Support */
.btn:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Form Error Highlighting */
.field-error-highlight {
    animation: errorHighlight 0.5s ease-in-out;
    border-color: var(--error-color) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

@keyframes errorHighlight {
    0% { 
        transform: translateX(0); 
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3);
    }
    25% { 
        transform: translateX(-5px); 
        box-shadow: 0 0 0 5px rgba(239, 68, 68, 0.2);
    }
    50% { 
        transform: translateX(5px); 
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    75% { 
        transform: translateX(-3px); 
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.05);
    }
    100% { 
        transform: translateX(0); 
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

/* Form Field Error States */
.form-field--error input,
.form-field--error select,
.form-field--error textarea {
    border-color: var(--error-color);
    background-color: rgba(239, 68, 68, 0.05);
}

.form-field--error input:focus,
.form-field--error select:focus,
.form-field--error textarea:focus {
    outline-color: var(--error-color);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.form-error-message {
    color: var(--error-color);
    font-size: 0.875rem;
    margin-top: var(--spacing-xs);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.form-error-message::before {
    content: '⚠';
    font-size: 1rem;
}

/* Connection Status Container */
.connection-status-container {
    position: fixed;
    top: var(--spacing-md);
    right: var(--spacing-md);
    z-index: 1000;
    pointer-events: auto;
}

@media (max-width: 768px) {
    .connection-status-container {
        top: var(--spacing-sm);
        right: var(--spacing-sm);
    }
}
</style>
