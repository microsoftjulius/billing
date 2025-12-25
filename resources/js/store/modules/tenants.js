import api from '@/api'

const state = {
    items: [],
    pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: 0,
        to: 0
    },
    loading: false,
    currentTenant: null
}

const mutations = {
    SET_TENANTS(state, { data, meta }) {
        state.items = data
        state.pagination = {
            current_page: meta.current_page || 1,
            last_page: meta.last_page || 1,
            per_page: meta.per_page || 20,
            total: meta.total || 0,
            from: meta.from || 0,
            to: meta.to || 0
        }
    },

    SET_TENANT(state, tenant) {
        state.currentTenant = tenant
    },

    ADD_TENANT(state, tenant) {
        state.items.unshift(tenant)
    },

    UPDATE_TENANT(state, updatedTenant) {
        const index = state.items.findIndex(t => t.id === updatedTenant.id)
        if (index !== -1) {
            state.items.splice(index, 1, updatedTenant)
        }
    },

    REMOVE_TENANT(state, tenantId) {
        state.items = state.items.filter(t => t.id !== tenantId)
    },

    SET_LOADING(state, loading) {
        state.loading = loading
    }
}

const actions = {
    async fetchTenants({ commit }, filters = {}) {
        commit('SET_LOADING', true)
        try {
            const response = await api.get('/tenants', { params: filters })
            commit('SET_TENANTS', response.data)
            return response.data
        } catch (error) {
            throw error
        } finally {
            commit('SET_LOADING', false)
        }
    },

    async fetchTenantById({ commit }, tenantId) {
        try {
            const response = await api.get(`/tenants/${tenantId}`)
            commit('SET_TENANT', response.data.data)
            return response.data.data
        } catch (error) {
            throw error
        }
    },

    async createTenant({ commit }, formData) {
        try {
            const response = await api.post('/tenants', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            })
            commit('ADD_TENANT', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async updateTenant({ commit }, { id, data }) {
        try {
            const response = await api.post(`/tenants/${id}`, data, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                params: { _method: 'PUT' }
            })
            commit('UPDATE_TENANT', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async deleteTenant({ commit }, tenantId) {
        try {
            await api.delete(`/tenants/${tenantId}`)
            commit('REMOVE_TENANT', tenantId)
        } catch (error) {
            throw error
        }
    },

    async suspendTenant({ commit }, { id, reason, duration_days }) {
        try {
            const response = await api.post(`/tenants/${id}/suspend`, {
                reason,
                duration_days
            })
            commit('UPDATE_TENANT', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async activateTenant({ commit }, tenantId) {
        try {
            const response = await api.post(`/tenants/${tenantId}/activate`)
            commit('UPDATE_TENANT', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async updateTenantPlan({ commit }, { id, plan, features }) {
        try {
            const response = await api.post(`/tenants/${id}/update-plan`, {
                plan,
                ...features
            })
            commit('UPDATE_TENANT', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async fetchTenantUsage({ commit }, tenantId) {
        try {
            const response = await api.get(`/tenants/${tenantId}/usage`)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async fetchTenantAnalytics({ commit }, { tenantId, period = 'month' }) {
        try {
            const response = await api.get(`/tenants/${tenantId}/analytics`, {
                params: { period }
            })
            return response.data
        } catch (error) {
            throw error
        }
    }
}

const getters = {
    getTenantById: (state) => (id) => {
        return state.items.find(tenant => tenant.id === id)
    },

    activeTenants: (state) => {
        return state.items.filter(tenant => tenant.is_active)
    },

    suspendedTenants: (state) => {
        return state.items.filter(tenant => !tenant.is_active)
    }
}

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters
}
