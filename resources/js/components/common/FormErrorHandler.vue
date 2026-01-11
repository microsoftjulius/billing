<template>
  <div v-if="hasErrors" class="form-error-handler">
    <div class="error-summary" :class="{ 'error-summary--expanded': showDetails }">
      <div class="error-summary__header" @click="toggleDetails">
        <div class="error-summary__icon">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="error-summary__content">
          <h4 class="error-summary__title">{{ title }}</h4>
          <p class="error-summary__message">{{ message }}</p>
        </div>
        <button 
          v-if="Object.keys(errors).length > 1"
          type="button" 
          class="error-summary__toggle"
          :aria-expanded="showDetails"
          aria-label="Toggle error details"
        >
          <svg 
            width="16" 
            height="16" 
            viewBox="0 0 16 16" 
            fill="currentColor"
            :class="{ 'rotate-180': showDetails }"
            class="transition-transform duration-200"
          >
            <path fill-rule="evenodd" d="M4.293 6.293a1 1 0 011.414 0L8 8.586l2.293-2.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
        <button 
          type="button" 
          class="error-summary__close"
          @click.stop="clearErrors"
          aria-label="Dismiss errors"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M4.646 4.646a.5.5 0 01.708 0L8 7.293l2.646-2.647a.5.5 0 01.708.708L8.707 8l2.647 2.646a.5.5 0 01-.708.708L8 8.707l-2.646 2.647a.5.5 0 01-.708-.708L7.293 8 4.646 5.354a.5.5 0 010-.708z"/>
          </svg>
        </button>
      </div>
      
      <transition name="error-details">
        <div v-if="showDetails && Object.keys(errors).length > 1" class="error-details">
          <ul class="error-list">
            <li 
              v-for="(fieldErrors, field) in errors" 
              :key="field"
              class="error-item"
            >
              <strong class="error-field">{{ formatFieldName(field) }}:</strong>
              <ul class="error-messages">
                <li 
                  v-for="(error, index) in fieldErrors" 
                  :key="index"
                  class="error-message"
                >
                  {{ error }}
                </li>
              </ul>
            </li>
          </ul>
          
          <div v-if="suggestions.length > 0" class="error-suggestions">
            <h5 class="suggestions-title">Suggestions:</h5>
            <ul class="suggestions-list">
              <li 
                v-for="(suggestion, index) in suggestions" 
                :key="index"
                class="suggestion-item"
              >
                {{ suggestion }}
              </li>
            </ul>
          </div>
        </div>
      </transition>
    </div>
    
    <!-- Field-specific error indicators -->
    <div v-if="showFieldErrors" class="field-errors">
      <div 
        v-for="(fieldErrors, field) in errors" 
        :key="field"
        class="field-error"
        :data-field="field"
      >
        <div class="field-error__label">{{ formatFieldName(field) }}</div>
        <ul class="field-error__messages">
          <li 
            v-for="(error, index) in fieldErrors" 
            :key="index"
            class="field-error__message"
          >
            {{ error }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';

interface Props {
  errors: Record<string, string[]>;
  title?: string;
  message?: string;
  showFieldErrors?: boolean;
  autoSuggest?: boolean;
  autoDismiss?: boolean;
  dismissDelay?: number;
}

interface Emits {
  (e: 'clear'): void;
  (e: 'field-focus', field: string): void;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Form Validation Error',
  message: 'Please correct the following errors:',
  showFieldErrors: false,
  autoSuggest: true,
  autoDismiss: false,
  dismissDelay: 10000
});

const emit = defineEmits<Emits>();

const showDetails = ref(false);
const dismissTimer = ref<NodeJS.Timeout | null>(null);

const hasErrors = computed(() => Object.keys(props.errors).length > 0);

const suggestions = computed(() => {
  if (!props.autoSuggest) return [];
  
  const suggestions: string[] = [];
  
  Object.entries(props.errors).forEach(([field, fieldErrors]) => {
    fieldErrors.forEach(error => {
      if (error.includes('required')) {
        suggestions.push(`Make sure to fill in the ${formatFieldName(field)} field.`);
      } else if (error.includes('email')) {
        suggestions.push('Check that email addresses are in the correct format (e.g., user@example.com).');
      } else if (error.includes('password')) {
        suggestions.push('Ensure passwords meet the security requirements.');
      } else if (error.includes('unique')) {
        suggestions.push(`The ${formatFieldName(field)} you entered is already in use. Try a different one.`);
      } else if (error.includes('min')) {
        suggestions.push(`Make sure ${formatFieldName(field)} meets the minimum length requirement.`);
      } else if (error.includes('max')) {
        suggestions.push(`Make sure ${formatFieldName(field)} doesn't exceed the maximum length.`);
      }
    });
  });
  
  // Remove duplicates
  return [...new Set(suggestions)];
});

const formatFieldName = (field: string): string => {
  return field
    .replace(/_/g, ' ')
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, str => str.toUpperCase())
    .trim();
};

const toggleDetails = () => {
  showDetails.value = !showDetails.value;
};

const clearErrors = () => {
  emit('clear');
};

const focusField = (field: string) => {
  emit('field-focus', field);
  
  // Try to focus the field element
  const fieldElement = document.querySelector(`[name="${field}"], #${field}, [data-field="${field}"]`) as HTMLElement;
  if (fieldElement) {
    fieldElement.focus();
    fieldElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
};

// Auto-dismiss functionality
watch(hasErrors, (newValue) => {
  if (dismissTimer.value) {
    clearTimeout(dismissTimer.value);
    dismissTimer.value = null;
  }
  
  if (newValue && props.autoDismiss) {
    dismissTimer.value = setTimeout(() => {
      clearErrors();
    }, props.dismissDelay);
  }
});

// Auto-expand details if there are multiple field errors
watch(hasErrors, (newValue) => {
  if (newValue && Object.keys(props.errors).length > 1) {
    showDetails.value = true;
  }
});
</script>

<style scoped>
.form-error-handler {
  margin-bottom: 1rem;
}

.error-summary {
  background: var(--color-error-bg, #fef2f2);
  border: 1px solid var(--color-error-border, #fecaca);
  border-radius: 0.5rem;
  overflow: hidden;
}

.error-summary__header {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.error-summary__header:hover {
  background: var(--color-error-bg-hover, #fee2e2);
}

.error-summary__icon {
  flex-shrink: 0;
  color: var(--color-error, #dc2626);
  margin-top: 0.125rem;
}

.error-summary__content {
  flex: 1;
  min-width: 0;
}

.error-summary__title {
  margin: 0 0 0.25rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-error, #dc2626);
}

.error-summary__message {
  margin: 0;
  font-size: 0.875rem;
  color: var(--color-error-text, #991b1b);
  line-height: 1.4;
}

.error-summary__toggle {
  flex-shrink: 0;
  background: none;
  border: none;
  color: var(--color-error, #dc2626);
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.25rem;
  transition: all 0.2s ease;
}

.error-summary__toggle:hover {
  background: var(--color-error-bg-hover, #fee2e2);
}

.error-summary__close {
  flex-shrink: 0;
  background: none;
  border: none;
  color: var(--color-error, #dc2626);
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.25rem;
  transition: all 0.2s ease;
}

.error-summary__close:hover {
  background: var(--color-error-bg-hover, #fee2e2);
}

.error-details {
  border-top: 1px solid var(--color-error-border, #fecaca);
  padding: 1rem;
  background: var(--color-error-bg-light, #fef7f7);
}

.error-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.error-item {
  margin-bottom: 0.75rem;
}

.error-item:last-child {
  margin-bottom: 0;
}

.error-field {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-error, #dc2626);
  margin-bottom: 0.25rem;
  cursor: pointer;
}

.error-field:hover {
  text-decoration: underline;
}

.error-messages {
  list-style: none;
  margin: 0;
  padding: 0 0 0 1rem;
}

.error-message {
  font-size: 0.875rem;
  color: var(--color-error-text, #991b1b);
  line-height: 1.4;
  margin-bottom: 0.25rem;
  position: relative;
}

.error-message:before {
  content: '•';
  position: absolute;
  left: -0.75rem;
  color: var(--color-error, #dc2626);
}

.error-suggestions {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-error-border-light, #fed7d7);
}

.suggestions-title {
  margin: 0 0 0.5rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-error, #dc2626);
}

.suggestions-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.suggestion-item {
  font-size: 0.875rem;
  color: var(--color-error-text, #991b1b);
  line-height: 1.4;
  margin-bottom: 0.5rem;
  padding-left: 1rem;
  position: relative;
}

.suggestion-item:before {
  content: '💡';
  position: absolute;
  left: 0;
  top: 0;
}

.field-errors {
  margin-top: 1rem;
}

.field-error {
  margin-bottom: 0.75rem;
  padding: 0.75rem;
  background: var(--color-error-bg-light, #fef7f7);
  border: 1px solid var(--color-error-border-light, #fed7d7);
  border-radius: 0.375rem;
}

.field-error__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-error, #dc2626);
  margin-bottom: 0.25rem;
  cursor: pointer;
}

.field-error__label:hover {
  text-decoration: underline;
}

.field-error__messages {
  list-style: none;
  margin: 0;
  padding: 0;
}

.field-error__message {
  font-size: 0.875rem;
  color: var(--color-error-text, #991b1b);
  line-height: 1.4;
  margin-bottom: 0.25rem;
}

/* Transitions */
.error-details-enter-active,
.error-details-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.error-details-enter-from,
.error-details-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.error-details-enter-to,
.error-details-leave-from {
  opacity: 1;
  max-height: 500px;
}

.rotate-180 {
  transform: rotate(180deg);
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
  .error-summary {
    --color-error-bg: #1f1415;
    --color-error-bg-hover: #2d1b1c;
    --color-error-bg-light: #1a1314;
    --color-error-border: #4c1d1d;
    --color-error-border-light: #3d1a1a;
    --color-error: #f87171;
    --color-error-text: #fca5a5;
  }
}

[data-theme="dark"] .error-summary {
  --color-error-bg: #1f1415;
  --color-error-bg-hover: #2d1b1c;
  --color-error-bg-light: #1a1314;
  --color-error-border: #4c1d1d;
  --color-error-border-light: #3d1a1a;
  --color-error: #f87171;
  --color-error-text: #fca5a5;
}
</style>