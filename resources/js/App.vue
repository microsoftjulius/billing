<template>
    <div id="app">
        <AppHeader />
        <div class="app-container">
            <AppSidebar v-if="showSidebar" />
            <main class="main-content" :class="{ 'full-width': !showSidebar }">
                <router-view />
            </main>
        </div>
        <AppFooter />
        <NotificationCenter />
        <ConfirmDialog />
        <LoadingOverlay :active="globalLoading" />
    </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'

export default {
    name: 'App',
    components: {
        AppHeader,
        AppSidebar,
        AppFooter,
        NotificationCenter,
        ConfirmDialog,
        LoadingOverlay
    },
    computed: {
        ...mapState(['globalLoading', 'user']),
        showSidebar() {
            return this.user && this.$route.meta.requiresAuth
        }
    },
    created() {
        this.initializeApp()
    },
    methods: {
        ...mapActions(['checkAuth', 'loadSettings'])
    }
}
</script>

<style>
.app-container {
    display: flex;
    min-height: calc(100vh - 120px);
}

.main-content {
    flex: 1;
    padding: 24px;
    background: #f5f7fa;
    overflow-y: auto;
}

.main-content.full-width {
    margin-left: 0;
}

@media (max-width: 768px) {
    .main-content {
        padding: 16px;
    }
}
</style>
