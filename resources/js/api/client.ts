import axios, { AxiosInstance } from 'axios';

// Create axios instance
const apiClient: AxiosInstance = axios.create({
  baseURL: '/',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// Request interceptor
apiClient.interceptors.request.use(
  (config) => {
    console.log('API Request:', {
      method: config.method?.toUpperCase(),
      url: config.url,
      baseURL: config.baseURL,
      headers: config.headers
    });

    // Add auth token if available
    const authToken = localStorage.getItem('auth_token');
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
      console.log('Added auth token:', authToken.substring(0, 20) + '...');
    } else {
      console.log('No auth token found');
    }

    return config;
  },
  (error) => {
    console.error('API Request Error:', error);
    return Promise.reject(error);
  }
);

// Response interceptor
apiClient.interceptors.response.use(
  (response) => {
    console.log('API Response:', {
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
      dataType: typeof response.data,
      dataPreview: typeof response.data === 'string' 
        ? response.data.substring(0, 100) + '...'
        : response.data
    });
    return response;
  },
  (error) => {
    console.error('API Response Error:', {
      status: error.response?.status,
      statusText: error.response?.statusText,
      data: error.response?.data,
      message: error.message
    });
    return Promise.reject(error);
  }
);

export default apiClient;