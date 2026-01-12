import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAppStore } from '@/store/modules/app'

// Force TypeScript to recompile by adding a comment
const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'home',
        component: () => import('@/pages/LandingPage.vue'),
        meta: { requiresGuest: true }
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { requiresGuest: true }
    },
    {
        path: '/app',
        component: () => import('@/layouts/DashboardLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/app/dashboard'
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('@/components/Dashboard.vue'),
                meta: { 
                    title: 'Dashboard',
                    breadcrumb: ['Dashboard']
                }
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('@/components/CustomersPlaceholder.vue'),
                meta: { 
                    title: 'Customer Management',
                    breadcrumb: ['Dashboard', 'Customers']
                }
            },
            {
                path: 'vouchers',
                name: 'vouchers',
                component: () => import('@/components/VouchersPlaceholder.vue'),
                meta: { 
                    title: 'Voucher Management',
                    breadcrumb: ['Dashboard', 'Vouchers']
                }
            },
            {
                path: 'payments',
                name: 'payments',
                component: () => import('@/components/PaymentsPlaceholder.vue'),
                meta: { 
                    title: 'Payment Management',
                    breadcrumb: ['Dashboard', 'Payments']
                }
            },
            {
                path: 'payment-analytics',
                name: 'payment-analytics',
                component: () => import('@/components/PaymentAnalytics.vue'),
                meta: { 
                    title: 'Payment Analytics',
                    breadcrumb: ['Dashboard', 'Payment Analytics']
                }
            },
            {
                path: 'mikrotik',
                name: 'mikrotik',
                component: () => import('@/components/MikroTikPlaceholder.vue'),
                meta: { 
                    title: 'MikroTik Monitoring',
                    breadcrumb: ['Dashboard', 'MikroTik']
                }
            },
            {
                path: 'mikrotik-config',
                name: 'mikrotik-config',
                component: () => import('@/components/MikroTikConfiguration.vue'),
                meta: { 
                    title: 'MikroTik Configuration',
                    breadcrumb: ['Dashboard', 'MikroTik Configuration']
                }
            },
            {
                path: 'router-management',
                name: 'router-management',
                component: () => import('@/components/RouterManagement.vue'),
                meta: { 
                    title: 'Router Management',
                    breadcrumb: ['Dashboard', 'Router Management']
                }
            },
            {
                path: 'sms',
                name: 'sms',
                component: () => import('@/components/SmsPlaceholder.vue'),
                meta: { 
                    title: 'SMS Configuration',
                    breadcrumb: ['Dashboard', 'SMS']
                }
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('@/pages/SettingsPage.vue'),
                meta: { 
                    title: 'Settings',
                    breadcrumb: ['Dashboard', 'Settings']
                }
            }
        ]
    },
    // Legacy redirects for old URLs
    {
        path: '/dashboard',
        redirect: '/app/dashboard'
    },
    {
        path: '/customers',
        redirect: '/app/customers'
    },
    {
        path: '/vouchers',
        redirect: '/app/vouchers'
    },
    {
        path: '/payments',
        redirect: '/app/payments'
    },
    {
        path: '/mikrotik',
        redirect: '/app/mikrotik'
    },
    {
        path: '/sms',
        redirect: '/app/sms'
    },
    {
        path: '/settings',
        redirect: '/app/settings'
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/app/dashboard'
    }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
    console.log('Router navigation:', { from: from.path, to: to.path })
    
    try {
        const requiresAuth = to.matched.some(record => record.meta?.requiresAuth)
        const requiresGuest = to.matched.some(record => record.meta?.requiresGuest)
        const appStore = useAppStore()
        
        // Ensure app is initialized before checking authentication
        // This is important for page reloads where the store hasn't been initialized yet
        if (!appStore.isInitialized) {
            console.log('Initializing app store...')
            appStore.initializeApp()
        }
        
        // For development: create a demo user if none exists
        if (import.meta.env.DEV && !appStore.user) {
            console.log('Creating demo user for development...')
            const demoUser = {
                id: 'demo-user-1',
                name: 'Demo User',
                email: 'admin@billing.com',
                role: 'admin' as const,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
            }
            appStore.setUser(demoUser)
            localStorage.setItem('auth_token', 'demo-token-123')
            localStorage.setItem('user', JSON.stringify(demoUser))
        }
        
        const isAuthenticated = appStore.isAuthenticated
        console.log('Authentication status:', { isAuthenticated, requiresAuth, requiresGuest })

        if (requiresAuth && !isAuthenticated) {
            console.log('Redirecting to login - authentication required')
            next('/login')
        } else if (requiresGuest && isAuthenticated) {
            console.log('Redirecting to dashboard - already authenticated')
            next('/app/dashboard')
        } else {
            console.log('Navigation allowed to:', to.path)
            next()
        }
    } catch (error) {
        console.warn('Navigation guard error:', error)
        // If there's an error, just proceed with navigation
        next()
    }
})

// After navigation hook for debugging
router.afterEach((to, from) => {
    console.log('Navigation completed:', { from: from.path, to: to.path })
    console.log('Current route meta:', to.meta)
})

export default router
