<template>
  <div class="theme-toggle">
    <button
      @click="toggleTheme"
      class="theme-toggle-btn"
      :title="getThemeTooltip"
      :aria-label="getThemeTooltip"
    >
      <transition name="icon-fade" mode="out-in">
        <component
          :is="getThemeIcon"
          :key="currentTheme"
          class="theme-icon"
        />
      </transition>
      <span class="theme-label">{{ getThemeLabel }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/store/modules/app'

// Icons as inline SVG components
const SunIcon = {
  template: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="5"/>
      <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
    </svg>
  `
}

const MoonIcon = {
  template: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
  `
}

const SystemIcon = {
  template: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
      <line x1="8" y1="21" x2="16" y2="21"/>
      <line x1="12" y1="17" x2="12" y2="21"/>
    </svg>
  `
}

const appStore = useAppStore()
const { theme, currentTheme } = storeToRefs(appStore)

const toggleTheme = () => {
  appStore.toggleTheme()
}

const getThemeIcon = computed(() => {
  switch (theme.value) {
    case 'light':
      return SunIcon
    case 'dark':
      return MoonIcon
    case 'system':
      return SystemIcon
    default:
      return SystemIcon
  }
})

const getThemeLabel = computed(() => {
  switch (theme.value) {
    case 'light':
      return 'Light'
    case 'dark':
      return 'Dark'
    case 'system':
      return 'System'
    default:
      return 'System'
  }
})

const getThemeTooltip = computed(() => {
  const nextTheme = getNextTheme()
  return `Switch to ${nextTheme} theme`
})

const getNextTheme = () => {
  switch (theme.value) {
    case 'light':
      return 'dark'
    case 'dark':
      return 'system'
    case 'system':
      return 'light'
    default:
      return 'light'
  }
}
</script>

<style scoped>
.theme-toggle {
  display: inline-block;
}

.theme-toggle-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background-color: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
  font-weight: 500;
}

.theme-toggle-btn:hover {
  background-color: var(--hover-bg);
  border-color: var(--primary-color);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.theme-toggle-btn:active {
  transform: translateY(0);
}

.theme-icon {
  width: 1.25rem;
  height: 1.25rem;
  transition: transform 0.2s ease;
}

.theme-toggle-btn:hover .theme-icon {
  transform: rotate(15deg);
}

.theme-label {
  font-size: 0.875rem;
  font-weight: 500;
}

/* Icon transition animations */
.icon-fade-enter-active,
.icon-fade-leave-active {
  transition: all 0.3s ease;
}

.icon-fade-enter-from {
  opacity: 0;
  transform: rotate(-90deg) scale(0.8);
}

.icon-fade-leave-to {
  opacity: 0;
  transform: rotate(90deg) scale(0.8);
}

/* Responsive design */
@media (max-width: 640px) {
  .theme-label {
    display: none;
  }
  
  .theme-toggle-btn {
    padding: 0.5rem;
  }
}

/* Focus styles for accessibility */
.theme-toggle-btn:focus {
  outline: none;
  box-shadow: 0 0 0 2px var(--primary-color);
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .theme-toggle-btn,
  .theme-icon,
  .icon-fade-enter-active,
  .icon-fade-leave-active {
    transition: none;
  }
  
  .theme-toggle-btn:hover .theme-icon {
    transform: none;
  }
}
</style>