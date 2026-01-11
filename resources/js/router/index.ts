import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAppStore } from '@/store/modules/app'

import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAppStore } from '@/store/modules/app'

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
                component: () => import('@/components/CustomerManagement.vue'),
                meta: { 
                    title: 'Customer Management',
                    breadcrumb: ['Dashboard', 'Customers']
                }
            },
            {
                path: 'vouchers',
                name: 'vouchers',
                component: () => import('@/components/VoucherManagement.vue'),
                meta: { 
                    title: 'Voucher Management',
                    breadcrumb: ['Dashboard', 'Vouchers']
                }
            },
            {
                path: 'payments',
                name: 'payments',
                component: () => import('@/components/Payment/PaymentGatewayManagement.vue'),
                meta: { 
                    title: 'Payment Management',
                    breadcrumb: ['Dashboard', 'Payments']
                }
            },
            {
                path: 'mikrotik',
                name: 'mikrotik',
                component: () => import('@/components/MikroTikMonitor.vue'),
                meta: { 
                    title: 'MikroTik Monitoring',
                    breadcrumb: ['Dashboard', 'MikroTik']
                }
            },
            {
                path: 'sms',
                name: 'sms',
                component: () => import('@/components/SmsConfiguration.vue'),
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
router.beforeEach((to, _from, next) => {
    const requiresAuth = to.matched.some(record => record.meta?.requiresAuth)
    const requiresGuest = to.matched.some(record => record.meta?.requiresGuest)
    const appStore = useAppStore()
    const isAuthenticated = appStore.isAuthenticated

    if (requiresAuth && !isAuthenticated) {
        next('/login')
    } else if (requiresGuest && isAuthenticated) {
        next('/app/dashboard')
    } else {
        next()
    }
})

export default router
