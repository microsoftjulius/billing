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
    currentUser: null,
    stats: {
        total_users: 0,
        active_users: 0,
        admins: 0,
        staff: 0
    }
}

const mutations = {
    SET_USERS(state, { data, meta }) {
        state.items = data
        state.pagination = {
            current_page: meta.current_page || 1,
            last_page: meta.last_page || 1,
            per_page: meta.per_page || 20,
            total: meta.total || 0,
            from: meta.from || 0,
            to: meta.to || 0
        }
        state.stats = meta.stats || state.stats
    },

    SET_USER(state, user) {
        state.currentUser = user
    },

    ADD_USER(state, user) {
        state.items.unshift(user)
    },

    UPDATE_USER(state, updatedUser) {
        const index = state.items.findIndex(u => u.uuid === updatedUser.uuid)
        if (index !== -1) {
            state.items.splice(index, 1, updatedUser)
        }
    },

    REMOVE_USER(state, userUuid) {
        state.items = state.items.filter(u => u.uuid !== userUuid)
    },

    SET_LOADING(state, loading) {
        state.loading = loading
    }
}

const actions = {
    async fetchUsers({ commit }, filters = {}) {
        commit('SET_LOADING', true)
        try {
            const response = await api.get('/users', { params: filters })
            commit('SET_USERS', response.data)
            return response.data
        } catch (error) {
            throw error
        } finally {
            commit('SET_LOADING', false)
        }
    },

    async fetchUserById({ commit }, userUuid) {
        try {
            const response = await api.get(`/users/${userUuid}`)
            commit('SET_USER', response.data.data)
            return response.data.data
        } catch (error) {
            throw error
        }
    },

    async createUser({ commit }, userData) {
        try {
            const response = await api.post('/users', userData)
            commit('ADD_USER', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async updateUser({ commit }, { uuid, data }) {
        try {
            const response = await api.put(`/users/${uuid}`, data)
            commit('UPDATE_USER', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async deleteUser({ commit }, userUuid) {
        try {
            await api.delete(`/users/${userUuid}`)
            commit('REMOVE_USER', userUuid)
        } catch (error) {
            throw error
        }
    },

    async suspendUser({ commit }, { uuid, reason, duration_days }) {
        try {
            const response = await api.post(`/users/${uuid}/suspend`, {
                reason,
                duration_days
            })
            commit('UPDATE_USER', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async activateUser({ commit }, userUuid) {
        try {
            const response = await api.post(`/users/${userUuid}/activate`)
            commit('UPDATE_USER', response.data.data)
            return response.data
        } catch (error) {
            throw error
        }
    },

    async updateUserPassword({ commit }, { uuid, current_password, new_password }) {
        try {
            const response = await api.post(`/users/${uuid}/update-password`, {
                current_password,
                new_password
            })
            return response.data
        } catch (error) {
            throw error
        }
    },

    async fetchUserActivity({ commit }, userUuid) {
        try {
            const response = await api.get(`/users/${userUuid}/activity`)
            return response.data
        } catch (error) {
            throw error
        }
    }
}

const getters = {
    getUserByUuid: (state) => (uuid) => {
        return state.items.find(user => user.uuid === uuid)
    },

    activeUsers: (state) => {
        return state.items.filter(user => user.is_active)
    },

    adminUsers: (state) => {
        return state.items.filter(user => user.role === 'admin')
    },

    staffUsers: (state) => {
        return state.items.filter(user => user.role === 'staff')
    }
}

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters
}
