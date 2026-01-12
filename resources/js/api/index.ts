import axios, { AxiosInstance, AxiosResponse, AxiosError } from 'axios';
import { ApiResponse, ApiError } from '@/types';
import { errorHandler } from '@/services/errorHandler';

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add auth token if available
    const authToken = localStorage.getItem('auth_token');
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }

    // Add CSRF token only for non-API routes or if specifically needed
    // For API routes with Bearer token, CSRF is typically not required
    if (!config.url?.startsWith('/api/v1/') || !authToken) {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
      }
    }

    // Add request metadata for error tracking (using any to avoid TypeScript issues)
    (config as any).metadata = {
      startTime: Date.now(),
      url: config.url,
      method: config.method?.toUpperCase()
    };

    return config;
  },
  (error) => {
    errorHandler.handleApiError(error, {
      context: {
        component: 'API',
        action: 'request_setup'
      }
    });
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response: AxiosResponse<ApiResponse>) => {
    // Log successful requests in development
    if (import.meta.env.DEV) {
      const duration = Date.now() - (response.config as any).metadata?.startTime;
      console.log(`API Success: ${response.config.method?.toUpperCase()} ${response.config.url} (${duration}ms)`);
    }
    return response;
  },
  (error: AxiosError<ApiError>) => {
    // Use enhanced error handler
    errorHandler.handleApiError(error, {
      context: {
        component: 'API',
        action: error.config?.method?.toUpperCase() || 'unknown',
        additionalData: {
          url: error.config?.url,
          duration: (error.config as any)?.metadata?.startTime 
            ? Date.now() - (error.config as any).metadata.startTime 
            : undefined
        }
      }
    });

    return Promise.reject(error);
  }
);

export { api };
export default api;