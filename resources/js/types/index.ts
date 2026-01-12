// Global types for the application

export interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'user';
  token?: string;
  tenantId?: string;
  plan?: string;
  created_at: string;
  updated_at: string;
}

export interface Customer {
  id: string;
  name: string;
  email?: string;
  phone: string;
  location?: {
    region: string;
    district: string;
    coordinates?: {
      lat: number;
      lng: number;
    };
  };
  service_plan_id?: string;
  status: 'active' | 'suspended' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface Voucher {
  id: string;
  code: string;
  customer_id?: string;
  amount: number;
  duration_hours: number;
  status: 'unused' | 'active' | 'expired' | 'suspended';
  activated_at?: string;
  expires_at?: string;
  mikrotik_device_id?: string;
  sms_sent_at?: string;
  created_at: string;
  updated_at: string;
}

export interface Payment {
  id: string;
  customer_id: string;
  voucher_id?: string;
  gateway_id: string;
  amount: number;
  currency: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'refunded';
  gateway_transaction_id?: string;
  gateway_reference?: string;
  callback_data?: Record<string, any>;
  processed_at?: string;
  created_at: string;
  updated_at: string;
}

export interface MikroTikDevice {
  id: string;
  name: string;
  ip_address: string;
  location: {
    region: string;
    district: string;
    coordinates?: {
      lat: number;
      lng: number;
    };
  };
  api_port: number;
  username: string;
  status: 'online' | 'offline' | 'error';
  last_seen?: string;
  uptime_seconds: number;
  created_at: string;
  updated_at: string;
}

export interface PaymentGateway {
  id: string;
  name: string;
  provider: 'collectug' | 'other';
  is_active: boolean;
  configuration: Record<string, any>;
  created_at: string;
  updated_at: string;
}

export interface SmsConfiguration {
  id: string;
  provider: 'ugsms';
  sender_id?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface SmsLog {
  id: string;
  customer_id?: string;
  recipient: string;
  phone_number?: string; // Alias for recipient for backward compatibility
  content: string;
  message?: string; // Alias for content for backward compatibility
  sender_id?: string;
  message_id?: string;
  status: 'pending' | 'sent' | 'delivered' | 'failed';
  delivery_status?: 'pending' | 'delivered' | 'failed';
  cost?: number;
  currency?: string;
  provider: string;
  provider_response?: Record<string, any>;
  metadata?: Record<string, any>;
  sent_at?: string;
  delivered_at?: string;
  failed_at?: string;
  created_at: string;
  updated_at: string;
}

export interface Notification {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message: string;
  duration?: number;
  actions?: Array<{
    label: string;
    action: () => void;
  }>;
}

export interface ApiResponse<T = any> {
  data: T;
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
  message?: string;
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  error_code?: string;
}

// Theme types
export type Theme = 'light' | 'dark' | 'system';

// Route meta types
export interface RouteMeta {
  requiresAuth?: boolean;
  requiresGuest?: boolean;
  title?: string;
  breadcrumb?: string[];
}