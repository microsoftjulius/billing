import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/store/modules/app'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

describe('Theme System Unit Tests', () => {
  let pinia: any

  beforeEach(() => {
    // Setup Pinia
    pinia = createPinia()
    setActivePinia(pinia)

    // Mock localStorage
    const mockLocalStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn(),
      clear: vi.fn()
    }
    Object.defineProperty(window, 'localStorage', {
      value: mockLocalStorage,
      writable: true
    })

    // Mock matchMedia
    Object.defineProperty(window, 'matchMedia', {
      value: vi.fn().mockImplementation((query) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn()
      })),
      writable: true
    })

    // Mock document.documentElement
    Object.defineProperty(document, 'documentElement', {
      value: {
        setAttribute: vi.fn(),
        classList: {
          add: vi.fn(),
          remove: vi.fn(),
          replace: vi.fn()
        },
        className: ''
      },
      writable: true
    })

    // Mock window.dispatchEvent
    Object.defineProperty(window, 'dispatchEvent', {
      value: vi.fn(),
      writable: true
    })
  })

  it('should render ThemeToggle component', () => {
    const wrapper = mount(ThemeToggle, {
      global: {
        plugins: [pinia]
      }
    })

    expect(wrapper.find('.theme-toggle').exists()).toBe(true)
    expect(wrapper.find('.theme-toggle-btn').exists()).toBe(true)
  })

  it('should toggle theme when button is clicked', async () => {
    const store = useAppStore()
    const wrapper = mount(ThemeToggle, {
      global: {
        plugins: [pinia]
      }
    })

    const initialTheme = store.theme
    const button = wrapper.find('.theme-toggle-btn')
    
    await button.trigger('click')
    
    expect(store.theme).not.toBe(initialTheme)
  })

  it('should set theme and persist to localStorage', () => {
    const store = useAppStore()
    
    store.setTheme('dark')
    
    expect(store.theme).toBe('dark')
    expect(localStorage.setItem).toHaveBeenCalledWith('theme', 'dark')
  })

  it('should apply theme to document element', () => {
    const store = useAppStore()
    
    store.setTheme('light')
    
    expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-theme', 'light')
  })

  it('should cycle through themes correctly', () => {
    const store = useAppStore()
    
    store.setTheme('light')
    expect(store.theme).toBe('light')
    
    store.toggleTheme()
    expect(store.theme).toBe('dark')
    
    store.toggleTheme()
    expect(store.theme).toBe('system')
    
    store.toggleTheme()
    expect(store.theme).toBe('light')
  })

  it('should show correct theme labels', async () => {
    const wrapper = mount(ThemeToggle, {
      global: {
        plugins: [pinia]
      }
    })

    const store = useAppStore()
    
    store.setTheme('light')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.theme-label').text()).toBe('Light')
    
    store.setTheme('dark')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.theme-label').text()).toBe('Dark')
    
    store.setTheme('system')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.theme-label').text()).toBe('System')
  })
})