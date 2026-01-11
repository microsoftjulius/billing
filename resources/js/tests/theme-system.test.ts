import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/store/modules/app'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import fc from 'fast-check'

// Feature: vue-frontend-enhancement, Property 4: Theme System Consistency

describe('Theme System Consistency', () => {
  let pinia: any
  let mockLocalStorage: any

  beforeEach(() => {
    // Setup Pinia
    pinia = createPinia()
    setActivePinia(pinia)

    // Mock localStorage
    mockLocalStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn(),
      clear: vi.fn()
    }
    Object.defineProperty(window, 'localStorage', {
      value: mockLocalStorage,
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

    // Mock matchMedia with consistent behavior
    Object.defineProperty(window, 'matchMedia', {
      value: vi.fn().mockImplementation((query: string) => ({
        matches: false, // Always return false for consistent testing
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
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('should maintain theme persistence across all valid theme values', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('light', 'dark', 'system'),
        (themeValue) => {
          const store = useAppStore()
          
          // Set theme
          store.setTheme(themeValue)
          
          // Verify localStorage persistence
          expect(mockLocalStorage.setItem).toHaveBeenCalledWith('theme', themeValue)
          
          // Verify theme is set in store
          expect(store.theme).toBe(themeValue)
          
          // Verify DOM updates (for non-system themes, should match exactly)
          if (themeValue !== 'system') {
            expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-theme', themeValue)
          } else {
            // For system theme, should apply light (since matchMedia.matches = false)
            expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-theme', 'light')
          }
          
          // Verify theme change event is dispatched
          expect(window.dispatchEvent).toHaveBeenCalledWith(
            expect.objectContaining({
              type: 'themeChanged'
            })
          )
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should restore theme preference from localStorage', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('light', 'dark', 'system'),
        (savedTheme) => {
          // Mock localStorage returning saved theme
          mockLocalStorage.getItem.mockReturnValue(savedTheme)
          
          const store = useAppStore()
          store.initializeApp()
          
          // Verify theme is restored from localStorage
          expect(store.theme).toBe(savedTheme)
          expect(mockLocalStorage.getItem).toHaveBeenCalledWith('theme')
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should display correct theme labels in UI components', async () => {
    // Property-based test implemented as unit test: 
    // For any theme change (light, dark, system), all UI components should consistently apply the new theme styles
    const themes = ['light', 'dark', 'system'] as const
    
    for (const themeValue of themes) {
      // Create a completely fresh Pinia instance for each theme
      const freshPinia = createPinia()
      setActivePinia(freshPinia)
      
      const store = useAppStore()
      
      // Set theme BEFORE mounting component
      store.setTheme(themeValue)
      
      // Mount ThemeToggle component
      const wrapper = mount(ThemeToggle, {
        global: {
          plugins: [freshPinia]
        }
      })
      
      // Wait for component to fully initialize
      await wrapper.vm.$nextTick()
      await new Promise(resolve => setTimeout(resolve, 10))
      
      // Verify theme label matches expected value
      const label = wrapper.find('.theme-label')
      expect(label.exists()).toBe(true)
      
      const actualText = label.text()
      const expectedLabel = themeValue.charAt(0).toUpperCase() + themeValue.slice(1)
      
      expect(actualText).toBe(expectedLabel)
      
      // Clean up
      wrapper.unmount()
    }
  })

  it('should cycle through themes in correct order', () => {
    const store = useAppStore()
    const expectedCycle = ['light', 'dark', 'system', 'light'] // Full cycle
    
    // Start with light theme
    store.setTheme('light')
    
    for (let i = 0; i < expectedCycle.length - 1; i++) {
      const currentTheme = expectedCycle[i]
      const nextTheme = expectedCycle[i + 1]
      
      expect(store.theme).toBe(currentTheme)
      store.toggleTheme()
      expect(store.theme).toBe(nextTheme)
    }
  })

  it('should handle invalid theme values gracefully', () => {
    fc.assert(
      fc.property(
        fc.string().filter(s => !['light', 'dark', 'system'].includes(s)),
        (invalidTheme) => {
          // Mock localStorage returning invalid theme
          mockLocalStorage.getItem.mockReturnValue(invalidTheme)
          
          const store = useAppStore()
          store.initializeApp()
          
          // Should default to 'system' theme for invalid values
          expect(store.theme).toBe('system')
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should maintain theme consistency across component instances', async () => {
    // Property-based test implemented as unit test:
    // For any theme change, all component instances should show the same theme consistently
    const themes = ['light', 'dark', 'system'] as const
    
    for (const themeValue of themes) {
      // Create a completely fresh Pinia instance for each theme
      const freshPinia = createPinia()
      setActivePinia(freshPinia)
      
      const store = useAppStore()
      
      // Set theme BEFORE mounting components
      store.setTheme(themeValue)
      
      // Mount multiple ThemeToggle components
      const wrapper1 = mount(ThemeToggle, {
        global: { plugins: [freshPinia] }
      })
      const wrapper2 = mount(ThemeToggle, {
        global: { plugins: [freshPinia] }
      })
      
      // Wait for components to fully initialize
      await wrapper1.vm.$nextTick()
      await wrapper2.vm.$nextTick()
      await new Promise(resolve => setTimeout(resolve, 10))
      
      // Both components should show the same theme
      const label1 = wrapper1.find('.theme-label')
      const label2 = wrapper2.find('.theme-label')
      
      expect(label1.exists()).toBe(true)
      expect(label2.exists()).toBe(true)
      
      const label1Text = label1.text()
      const label2Text = label2.text()
      
      expect(label1Text).toBe(label2Text)
      
      const expectedLabel = themeValue.charAt(0).toUpperCase() + themeValue.slice(1)
      expect(label1Text).toBe(expectedLabel)
      
      // Clean up wrappers
      wrapper1.unmount()
      wrapper2.unmount()
    }
  })
})