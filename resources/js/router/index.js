import Vue from 'vue'
import VueRouter from 'vue-router'
import store from '@/store'

Vue.use(VueRouter)

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/auth/Login.vue'),
        meta: { requiresGuest: true }
    },

    {
        path: '/',
        redirect: '/dashboard'
    },

    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/views/central/Dashboard.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/tenants',
        name: 'tenants',
        component: () => import('@/views/central/tenants/TenantsList.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/tenants/create',
        name: 'create-tenant',
        component: () => import('@/views/central/tenants/TenantForm.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/tenants/:id',
        name: 'tenant-details',
        component: () => import('@/views/central/tenants/TenantDetails.vue'),
        meta: { requiresAuth: true },
        props: true
    },

    {
        path: '/tenants/:id/edit',
        name: 'edit-tenant',
        component: () => import('@/views/central/tenants/TenantForm.vue'),
        meta: { requiresAuth: true },
        props: true
    },

    {
        path: '/users',
        name: 'users',
        component: () => import('@/views/central/users/UsersList.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/users/create',
        name: 'create-user',
        component: () => import('@/views/central/users/UserForm.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/users/:uuid',
        name: 'user-details',
        component: () => import('@/views/central/users/UserDetails.vue'),
        meta: { requiresAuth: true },
        props: true
    },

    {
        path: '/users/:uuid/edit',
        name: 'edit-user',
        component: () => import('@/views/central/users/UserForm.vue'),
        meta: { requiresAuth: true },
        props: true
    },

    {
        path: '/reports/tenants',
        name: 'reports-tenants',
        component: () => import('@/views/central/reports/ReportsDashboard.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/reports/revenue',
        name: 'reports-revenue',
        component: () => import('@/views/central/reports/ReportsDashboard.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/reports/usage',
        name: 'reports-usage',
        component: () => import('@/views/central/reports/ReportsDashboard.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/central/settings/SettingsDashboard.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '*',
        redirect: '/dashboard'
    }
]

const router = new VueRouter({
    mode: 'history',
    base: process.env.BASE_URL,
    routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
    const requiresGuest = to.matched.some(record => record.meta.requiresGuest)
    const isAuthenticated = store.state.auth.isAuthenticated

    if (requiresAuth && !isAuthenticated) {
        next('/login')
    } else if (requiresGuest && isAuthenticated) {
        next('/dashboard')
    } else {
        next()
    }
})

export default router
