import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { User, Notification, Theme } from '@/types';

export const useAppStore = defineStore('app', () => {
  // State
  const user = ref<User | null>(null);
  const theme = ref<Theme>('system');
  const notifications = ref<Notification[]>([]);
  const isLoading = ref(false);
  const sidebarCollapsed = ref(false);
  const isInitialized = ref(false);

  // Getters
  const isAuthenticated = computed(() => !!user.value);
  const currentTheme = computed(() => {
    if (theme.value === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return theme.value;
  });

  // Actions
  const setUser = (userData: User | null) => {
    user.value = userData;
  };

  const logout = () => {
    user.value = null;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    
    // Clear axios authorization header if available
    if (typeof window !== 'undefined' && (window as any).axios) {
      delete (window as any).axios.defaults.headers.common['Authorization'];
    }
    
    addInfoNotification('You have been logged out successfully', 'Logged Out');
  };

  const setTheme = (newTheme: Theme) => {
    theme.value = newTheme;
    localStorage.setItem('theme', newTheme);
    
    // Apply theme to document with improved system detection
    const root = document.documentElement;
    let appliedTheme: string;
    
    if (newTheme === 'system') {
      appliedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } else {
      appliedTheme = newTheme;
    }
    
    root.setAttribute('data-theme', appliedTheme);
    
    // Also set a class for additional styling hooks
    root.className = root.className.replace(/theme-\w+/g, '');
    root.classList.add(`theme-${appliedTheme}`);
    
    // Dispatch custom event for theme change
    window.dispatchEvent(new CustomEvent('themeChanged', {
      detail: { theme: newTheme, appliedTheme }
    }));
  };

  const toggleTheme = () => {
    const themes: Theme[] = ['light', 'dark', 'system'];
    const currentIndex = themes.indexOf(theme.value);
    const nextTheme = themes[(currentIndex + 1) % themes.length];
    setTheme(nextTheme);
  };

  const addNotification = (notification: Omit<Notification, 'id'>) => {
    const id = Date.now().toString() + Math.random().toString(36).substr(2, 9);
    const newNotification: Notification = {
      ...notification,
      id,
      duration: notification.duration ?? 5000,
    };
    
    notifications.value.push(newNotification);

    // Auto remove notification after duration
    if (newNotification.duration && newNotification.duration > 0) {
      setTimeout(() => {
        removeNotification(id);
      }, newNotification.duration);
    }

    // Limit total notifications to prevent UI overflow
    if (notifications.value.length > 10) {
      notifications.value = notifications.value.slice(-10);
    }
  };

  const addSuccessNotification = (message: string, title?: string, duration?: number) => {
    addNotification({
      type: 'success',
      title: title || 'Success',
      message,
      duration
    });
  };

  const addErrorNotification = (message: string, title?: string, duration?: number, actions?: Notification['actions']) => {
    addNotification({
      type: 'error',
      title: title || 'Error',
      message,
      duration: duration ?? 8000,
      actions
    });
  };

  const addWarningNotification = (message: string, title?: string, duration?: number) => {
    addNotification({
      type: 'warning',
      title: title || 'Warning',
      message,
      duration: duration ?? 6000
    });
  };

  const addInfoNotification = (message: string, title?: string, duration?: number) => {
    addNotification({
      type: 'info',
      title: title || 'Information',
      message,
      duration
    });
  };

  const removeNotification = (id: string) => {
    const index = notifications.value.findIndex(n => n.id === id);
    if (index > -1) {
      notifications.value.splice(index, 1);
    }
  };

  const clearNotifications = () => {
    notifications.value = [];
  };

  const setLoading = (loading: boolean) => {
    isLoading.value = loading;
  };

  const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem('sidebar-collapsed', sidebarCollapsed.value.toString());
  };

  const initializeApp = () => {
    // Prevent multiple initializations
    if (isInitialized.value) {
      return;
    }
    
    try {
      // Initialize theme from localStorage with fallback
      const savedTheme = localStorage.getItem('theme') as Theme;
      if (savedTheme && ['light', 'dark', 'system'].includes(savedTheme)) {
        setTheme(savedTheme);
      } else {
        // Default to system theme if no preference is saved
        setTheme('system');
      }

      // Initialize sidebar state
      const savedSidebarState = localStorage.getItem('sidebar-collapsed');
      if (savedSidebarState) {
        sidebarCollapsed.value = savedSidebarState === 'true';
      }

      // Check for existing authentication
      const savedToken = localStorage.getItem('auth_token');
      const savedUser = localStorage.getItem('user');
      
      if (savedToken && savedUser) {
        try {
          const userData = JSON.parse(savedUser);
          setUser(userData);
          
          // Set axios default authorization header if axios is available
          if (typeof window !== 'undefined' && (window as any).axios) {
            (window as any).axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
          }
        } catch (error) {
          console.warn('Failed to parse saved user data:', error);
          // Clear invalid stored data
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user');
        }
      }

      // Listen for system theme changes with improved handling
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      const handleSystemThemeChange = (e: MediaQueryListEvent) => {
        if (theme.value === 'system') {
          // Re-apply system theme when system preference changes
          const root = document.documentElement;
          const appliedTheme = e.matches ? 'dark' : 'light';
          root.setAttribute('data-theme', appliedTheme);
          root.className = root.className.replace(/theme-\w+/g, '');
          root.classList.add(`theme-${appliedTheme}`);
          
          // Dispatch theme change event
          window.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: 'system', appliedTheme }
          }));
        }
      };
      
      // Use the newer addEventListener method with fallback
      if (mediaQuery.addEventListener) {
        mediaQuery.addEventListener('change', handleSystemThemeChange);
      } else {
        // Fallback for older browsers
        mediaQuery.addListener(handleSystemThemeChange);
      }
      
      // Mark as initialized
      isInitialized.value = true;
    } catch (error) {
      console.error('Failed to initialize app:', error);
      // Mark as initialized even if there was an error to prevent infinite loops
      isInitialized.value = true;
    }
  };

  return {
    // State
    user,
    theme,
    notifications,
    isLoading,
    sidebarCollapsed,
    isInitialized,
    
    // Getters
    isAuthenticated,
    currentTheme,
    
    // Actions
    setUser,
    logout,
    setTheme,
    toggleTheme,
    addNotification,
    addSuccessNotification,
    addErrorNotification,
    addWarningNotification,
    addInfoNotification,
    removeNotification,
    clearNotifications,
    setLoading,
    toggleSidebar,
    initializeApp,
  };
});