import api from '@/api'

const state = {
    settings: {},
    categories: [],
    validationRules: {},
    dependencies: {},
    loading: false,
    saving: false,
    lastModified: null
}

const mutations = {
    SET_SETTINGS(state, settings) {
        state.settings = settings
    },

    SET_CATEGORIES(state, categories) {
        state.categories = categories
    },

    SET_VALIDATION_RULES(state, rules) {
        state.validationRules = rules
    },

    SET_DEPENDENCIES(state, dependencies) {
        state.dependencies = dependencies
    },

    SET_LAST_MODIFIED(state, timestamp) {
        state.lastModified = timestamp
    },

    SET_LOADING(state, loading) {
        state.loading = loading
    },

    SET_SAVING(state, saving) {
        state.saving = saving
    },

    UPDATE_SETTING(state, { category, key, value }) {
        if (state.settings[category]) {
            state.settings[category][key] = value
        }
    }
}

const actions = {
    async fetchSettings({ commit }) {
        commit('SET_LOADING', true)
        try {
            const response = await api.get('/settings')
            commit('SET_SETTINGS', response.data.data.settings)
            commit('SET_CATEGORIES', response.data.data.categories)
            commit('SET_VALIDATION_RULES', response.data.data.validation_rules)
            commit('SET_DEPENDENCIES', response.data.data.dependencies)
            commit('SET_LAST_MODIFIED', response.data.meta.last_modified)
            return response.data
        } catch (error) {
            throw error
        } finally {
            commit('SET_LOADING', false)
        }
    },

    async updateSettings({ commit }, settings) {
        commit('SET_SAVING', true)
        try {
            const response = await api.put('/settings', settings)
            commit('SET_SETTINGS', response.data.updated_settings)
            commit('SET_LAST_MODIFIED', new Date().toISOString())
            return response.data
        } catch (error) {
            throw error
        } finally {
            commit('SET_SAVING', false)
        }
    },

    async testEmailConfiguration({ commit }, email) {
        try {
            const response = await api.post('/settings/test-email', { email })
            return response.data
        } catch (error) {
            throw error
        }
    },

    async restartSystem({ commit }) {
        try {
            const response = await api.post('/settings/restart')
            return response.data
        } catch (error) {
            throw error
        }
    }
}

const getters = {
    getSetting: (state) => (category, key) => {
        return state.settings[category]?.[key]
    },

    getCategorySettings: (state) => (category) => {
        return state.settings[category] || {}
    },

    allSettings: (state) => {
        return state.settings
    },

    isLoading: (state) => {
        return state.loading
    },

    isSaving: (state) => {
        return state.saving
    }
}

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters
}
