/**
 * Composable for handling form validation errors
 */

import { ref, computed, watch } from 'vue';
import { AxiosError } from 'axios';
import { errorHandler } from '@/services/errorHandler';
import type { ApiError } from '@/types';

export interface FormErrorState {
  errors: Record<string, string[]>;
  hasErrors: boolean;
  isSubmitting: boolean;
}

export interface FormErrorOptions {
  autoFocus?: boolean;
  autoClear?: boolean;
  clearDelay?: number;
  showNotifications?: boolean;
}

export const useFormErrorHandler = (options: FormErrorOptions = {}) => {
  const {
    autoFocus = true,
    autoClear = false,
    clearDelay = 5000,
    showNotifications = true
  } = options;

  // State
  const errors = ref<Record<string, string[]>>({});
  const isSubmitting = ref(false);
  const clearTimer = ref<NodeJS.Timeout | null>(null);

  // Computed
  const hasErrors = computed(() => Object.keys(errors.value).length > 0);
  const errorCount = computed(() => Object.keys(errors.value).length);
  const firstError = computed(() => {
    const firstField = Object.keys(errors.value)[0];
    return firstField ? errors.value[firstField][0] : null;
  });

  // Methods
  const setErrors = (newErrors: Record<string, string[]>) => {
    errors.value = newErrors;
    
    if (autoFocus && hasErrors.value) {
      focusFirstErrorField();
    }
    
    if (autoClear && hasErrors.value) {
      scheduleAutoClear();
    }
  };

  const setFieldError = (field: string, fieldErrors: string[]) => {
    errors.value = {
      ...errors.value,
      [field]: fieldErrors
    };
  };

  const clearErrors = () => {
    errors.value = {};
    clearAutoClearTimer();
  };

  const clearFieldError = (field: string) => {
    const newErrors = { ...errors.value };
    delete newErrors[field];
    errors.value = newErrors;
  };

  const hasFieldError = (field: string): boolean => {
    return field in errors.value && errors.value[field].length > 0;
  };

  const getFieldErrors = (field: string): string[] => {
    return errors.value[field] || [];
  };

  const getFirstFieldError = (field: string): string | null => {
    const fieldErrors = getFieldErrors(field);
    return fieldErrors.length > 0 ? fieldErrors[0] : null;
  };

  const handleApiError = (error: AxiosError<ApiError>) => {
    if (error.response?.status === 422 && error.response.data.errors) {
      // Validation errors
      setErrors(error.response.data.errors);
      
      if (showNotifications) {
        errorHandler.handleFormError(error.response.data.errors, {
          showNotification: false // We'll handle it ourselves
        });
      }
    } else {
      // Other API errors
      errorHandler.handleApiError(error, {
        context: {
          component: 'Form',
          action: 'submit'
        }
      });
    }
  };

  const handleSubmit = async <T>(
    submitFn: () => Promise<T>,
    options: {
      onSuccess?: (result: T) => void;
      onError?: (error: any) => void;
      clearOnSuccess?: boolean;
    } = {}
  ): Promise<T | null> => {
    const { onSuccess, onError, clearOnSuccess = true } = options;
    
    try {
      isSubmitting.value = true;
      clearErrors(); // Clear previous errors
      
      const result = await submitFn();
      
      if (clearOnSuccess) {
        clearErrors();
      }
      
      onSuccess?.(result);
      return result;
    } catch (error) {
      handleApiError(error as AxiosError<ApiError>);
      onError?.(error);
      return null;
    } finally {
      isSubmitting.value = false;
    }
  };

  const focusFirstErrorField = () => {
    if (!hasErrors.value) return;
    
    const firstField = Object.keys(errors.value)[0];
    focusField(firstField);
  };

  const focusField = (field: string) => {
    // Try multiple selectors to find the field
    const selectors = [
      `[name="${field}"]`,
      `#${field}`,
      `[data-field="${field}"]`,
      `[aria-label*="${field}"]`,
      `.field-${field} input`,
      `.field-${field} select`,
      `.field-${field} textarea`
    ];

    for (const selector of selectors) {
      const element = document.querySelector(selector) as HTMLElement;
      if (element) {
        element.focus();
        element.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center' 
        });
        
        // Add error highlight
        element.classList.add('field-error-highlight');
        setTimeout(() => {
          element.classList.remove('field-error-highlight');
        }, 3000);
        
        break;
      }
    }
  };

  const scheduleAutoClear = () => {
    clearAutoClearTimer();
    clearTimer.value = setTimeout(() => {
      clearErrors();
    }, clearDelay);
  };

  const clearAutoClearTimer = () => {
    if (clearTimer.value) {
      clearTimeout(clearTimer.value);
      clearTimer.value = null;
    }
  };

  const validateField = (
    field: string, 
    value: any, 
    rules: ValidationRule[]
  ): string[] => {
    const fieldErrors: string[] = [];
    
    for (const rule of rules) {
      const error = rule.validate(value, field);
      if (error) {
        fieldErrors.push(error);
      }
    }
    
    if (fieldErrors.length > 0) {
      setFieldError(field, fieldErrors);
    } else {
      clearFieldError(field);
    }
    
    return fieldErrors;
  };

  const validateForm = (
    formData: Record<string, any>,
    validationRules: Record<string, ValidationRule[]>
  ): boolean => {
    const allErrors: Record<string, string[]> = {};
    
    Object.entries(validationRules).forEach(([field, rules]) => {
      const value = formData[field];
      const fieldErrors = validateField(field, value, rules);
      
      if (fieldErrors.length > 0) {
        allErrors[field] = fieldErrors;
      }
    });
    
    setErrors(allErrors);
    return !hasErrors.value;
  };

  // Watch for errors and handle auto-clear
  watch(hasErrors, (newValue) => {
    if (!newValue) {
      clearAutoClearTimer();
    }
  });

  // Cleanup on unmount
  const cleanup = () => {
    clearAutoClearTimer();
  };

  return {
    // State
    errors: computed(() => errors.value),
    hasErrors,
    errorCount,
    firstError,
    isSubmitting: computed(() => isSubmitting.value),
    
    // Methods
    setErrors,
    setFieldError,
    clearErrors,
    clearFieldError,
    hasFieldError,
    getFieldErrors,
    getFirstFieldError,
    handleApiError,
    handleSubmit,
    focusField,
    focusFirstErrorField,
    validateField,
    validateForm,
    cleanup
  };
};

// Validation rule interface
export interface ValidationRule {
  validate: (value: any, field: string) => string | null;
}

// Common validation rules
export const validationRules = {
  required: (message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value === null || value === undefined || value === '') {
        return message || `${field} is required`;
      }
      return null;
    }
  }),

  email: (message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        return message || `${field} must be a valid email address`;
      }
      return null;
    }
  }),

  minLength: (min: number, message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && value.length < min) {
        return message || `${field} must be at least ${min} characters`;
      }
      return null;
    }
  }),

  maxLength: (max: number, message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && value.length > max) {
        return message || `${field} must not exceed ${max} characters`;
      }
      return null;
    }
  }),

  pattern: (regex: RegExp, message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && !regex.test(value)) {
        return message || `${field} format is invalid`;
      }
      return null;
    }
  }),

  numeric: (message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && isNaN(Number(value))) {
        return message || `${field} must be a number`;
      }
      return null;
    }
  }),

  min: (min: number, message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value !== null && value !== undefined && Number(value) < min) {
        return message || `${field} must be at least ${min}`;
      }
      return null;
    }
  }),

  max: (max: number, message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value !== null && value !== undefined && Number(value) > max) {
        return message || `${field} must not exceed ${max}`;
      }
      return null;
    }
  }),

  phone: (message?: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (value && !/^[\+]?[1-9][\d]{0,15}$/.test(value.replace(/[\s\-\(\)]/g, ''))) {
        return message || `${field} must be a valid phone number`;
      }
      return null;
    }
  }),

  custom: (validator: (value: any) => boolean, message: string): ValidationRule => ({
    validate: (value: any, field: string) => {
      if (!validator(value)) {
        return message;
      }
      return null;
    }
  })
};