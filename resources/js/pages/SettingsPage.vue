<template>
  <div class="settings-page">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Settings</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
          Manage your system configuration and API integrations
        </p>
      </div>

      <div class="space-y-8">
        <!-- API Key Management Section -->
        <ApiKeyManagement />
        
        <!-- Additional settings sections can be added here -->
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import ApiKeyManagement from '@/components/ApiKeyManagement.vue'
</script>

<style scoped>
.settings-page {
  min-height: 100vh;
  background-color: #f9fafb;
}

.dark .settings-page {
  background-color: #111827;
}
</style>