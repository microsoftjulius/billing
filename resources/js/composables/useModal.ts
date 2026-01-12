import { ref, computed } from 'vue'

export interface ModalState {
  show: boolean
  loading: boolean
  data: any
}

export interface ModalOptions {
  persistent?: boolean
  closeOnBackdrop?: boolean
  closeOnEsc?: boolean
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'full'
  variant?: 'default' | 'danger' | 'warning' | 'success' | 'info'
}

export function useModal(initialData: any = null, options: ModalOptions = {}) {
  const state = ref<ModalState>({
    show: false,
    loading: false,
    data: initialData
  })

  const modalOptions = ref<ModalOptions>({
    persistent: false,
    closeOnBackdrop: true,
    closeOnEsc: true,
    size: 'md',
    variant: 'default',
    ...options
  })

  // Computed properties
  const isOpen = computed(() => state.value.show)
  const isLoading = computed(() => state.value.loading)
  const modalData = computed(() => state.value.data)

  // Methods
  const open = (data: any = null) => {
    if (data !== null) {
      state.value.data = data
    }
    state.value.show = true
  }

  const close = () => {
    if (!state.value.loading && !modalOptions.value.persistent) {
      state.value.show = false
      state.value.data = initialData
    }
  }

  const setLoading = (loading: boolean) => {
    state.value.loading = loading
  }

  const setData = (data: any) => {
    state.value.data = data
  }

  const updateOptions = (newOptions: Partial<ModalOptions>) => {
    modalOptions.value = { ...modalOptions.value, ...newOptions }
  }

  const toggle = (data: any = null) => {
    if (state.value.show) {
      close()
    } else {
      open(data)
    }
  }

  const reset = () => {
    state.value = {
      show: false,
      loading: false,
      data: initialData
    }
  }

  return {
    // State
    state: computed(() => state.value),
    isOpen,
    isLoading,
    modalData,
    modalOptions: computed(() => modalOptions.value),

    // Methods
    open,
    close,
    toggle,
    setLoading,
    setData,
    updateOptions,
    reset
  }
}

// Specialized modal composables
export function useConfirmModal() {
  const modal = useModal()

  const confirm = (
    message: string,
    title: string = 'Confirm Action',
    options: {
      type?: 'info' | 'warning' | 'danger' | 'success'
      confirmText?: string
      cancelText?: string
      description?: string
    } = {}
  ): Promise<boolean> => {
    return new Promise((resolve) => {
      const modalData = {
        message,
        title,
        type: options.type || 'warning',
        confirmText: options.confirmText || 'Confirm',
        cancelText: options.cancelText || 'Cancel',
        description: options.description || '',
        onConfirm: () => {
          modal.close()
          resolve(true)
        },
        onCancel: () => {
          modal.close()
          resolve(false)
        }
      }

      modal.open(modalData)
    })
  }

  const confirmDanger = (
    message: string,
    title: string = 'Dangerous Action',
    options: Omit<Parameters<typeof confirm>[2], 'type'> = {}
  ) => {
    return confirm(message, title, { ...options, type: 'danger' })
  }

  const confirmDelete = (
    itemName: string,
    options: Omit<Parameters<typeof confirm>[2], 'type'> = {}
  ) => {
    return confirm(
      `Are you sure you want to delete "${itemName}"?`,
      'Delete Confirmation',
      {
        ...options,
        type: 'danger',
        confirmText: 'Delete',
        description: 'This action cannot be undone.'
      }
    )
  }

  return {
    ...modal,
    confirm,
    confirmDanger,
    confirmDelete
  }
}

export function useFormModal<T = any>(initialFormData: T) {
  const modal = useModal(initialFormData)
  const errors = ref<Record<string, string[]>>({})

  const openForm = (data: T | null = null) => {
    errors.value = {}
    modal.open(data || initialFormData)
  }

  const setErrors = (newErrors: Record<string, string[]>) => {
    errors.value = newErrors
  }

  const clearErrors = () => {
    errors.value = {}
  }

  const hasErrors = computed(() => Object.keys(errors.value).length > 0)

  const getFieldError = (field: string) => {
    return errors.value[field]?.[0] || ''
  }

  const hasFieldError = (field: string) => {
    return Boolean(errors.value[field]?.length)
  }

  return {
    ...modal,
    errors: computed(() => errors.value),
    hasErrors,
    openForm,
    setErrors,
    clearErrors,
    getFieldError,
    hasFieldError
  }
}

// Modal manager for handling multiple modals
export function useModalManager() {
  const modals = ref<Map<string, ReturnType<typeof useModal>>>(new Map())

  const register = (name: string, modal: ReturnType<typeof useModal>) => {
    modals.value.set(name, modal)
  }

  const unregister = (name: string) => {
    modals.value.delete(name)
  }

  const get = (name: string) => {
    return modals.value.get(name)
  }

  const open = (name: string, data?: any) => {
    const modal = modals.value.get(name)
    if (modal) {
      modal.open(data)
    }
  }

  const close = (name: string) => {
    const modal = modals.value.get(name)
    if (modal) {
      modal.close()
    }
  }

  const closeAll = () => {
    modals.value.forEach(modal => modal.close())
  }

  const isAnyOpen = computed(() => {
    return Array.from(modals.value.values()).some(modal => modal.isOpen)
  })

  return {
    register,
    unregister,
    get,
    open,
    close,
    closeAll,
    isAnyOpen
  }
}