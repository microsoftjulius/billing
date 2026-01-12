# Billing System Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Installation & Setup](#installation--setup)
4. [MikroTik Router Management](#mikrotik-router-management)
5. [Adding MikroTik Routers](#adding-mikrotik-routers)
6. [Voucher Management](#voucher-management)
7. [API Documentation](#api-documentation)
8. [Frontend Components](#frontend-components)
9. [Database Schema](#database-schema)
10. [Configuration](#configuration)
11. [Troubleshooting](#troubleshooting)

## System Overview

This is a comprehensive billing system built with Laravel (backend) and Vue.js (frontend) designed for managing internet vouchers and MikroTik routers. The system provides:

- **Multi-tenant architecture** with tenant isolation
- **MikroTik router management** with real-time monitoring
- **Voucher generation and management** with various profiles
- **Payment gateway integration** for automated billing
- **SMS notifications** for voucher delivery
- **Real-time dashboard** with analytics
- **Customer management** with communication history
- **Configuration backup and restore** for routers

### Key Features

- 🌐 **Multi-tenant Support**: Complete tenant isolation with subdomain/header-based routing
- 🔧 **MikroTik Integration**: Full RouterOS API integration for hotspot management
- 🎫 **Voucher System**: Flexible voucher generation with multiple profiles and validity periods
- 💳 **Payment Processing**: Integrated payment gateways with automatic voucher activation
- 📱 **SMS Integration**: Automated SMS delivery of voucher credentials
- 📊 **Analytics Dashboard**: Real-time statistics and reporting
- 🔒 **Security**: Encrypted credentials, API rate limiting, and secure authentication
- 🎨 **Modern UI**: Dark theme, responsive design, and intuitive user experience

## Architecture

### Backend (Laravel 12)
- **Framework**: Laravel 12 with PHP 8.2+
- **Database**: MySQL/PostgreSQL with UUID primary keys
- **Authentication**: Laravel Sanctum for API authentication
- **Multi-tenancy**: Stancl/Tenancy package for tenant isolation
- **Queue System**: Redis/Database queues for background processing
- **Real-time**: Laravel Reverb for WebSocket connections
- **MikroTik API**: RouterOS API PHP library for router communication

### Frontend (Vue.js 3)
- **Framework**: Vue.js 3 with Composition API
- **Build Tool**: Vite for fast development and building
- **State Management**: Pinia for centralized state management
- **Routing**: Vue Router 4 for SPA navigation
- **Styling**: TailwindCSS 4 with custom CSS variables for theming
- **TypeScript**: Full TypeScript support for type safety
- **Testing**: Vitest for unit and integration testing

### Key Dependencies

#### Backend
```json
{
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.2",
  "stancl/tenancy": "*",
  "evilfreelancer/routeros-api-php": "^1.6",
  "laravel/reverb": "^1.7"
}
```

#### Frontend
```json
{
  "vue": "^3.5.26",
  "vue-router": "^4.6.4",
  "pinia": "^3.0.4",
  "tailwindcss": "^4.0.0",
  "typescript": "^5.9.3",
  "vite": "^7.0.7"
}
```

## Installation & Setup

### Prerequisites
- PHP 8.2 or higher
- Node.js 18 or higher
- MySQL 8.0 or PostgreSQL 13+
- Redis (optional, for caching and queues)
- Composer
- NPM or Yarn

### Step 1: Clone and Install Dependencies

```bash
# Clone the repository
git clone <repository-url>
cd billing-system

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### Step 2: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 3: Database Setup

Configure your database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_system
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations:

```bash
# Run database migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed
```

### Step 4: Build Frontend Assets

```bash
# Development build
npm run dev

# Production build
npm run build
```

### Step 5: Start the Application

```bash
# Start Laravel development server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:work

# Start WebSocket server (in separate terminal)
php artisan reverb:start
```

### Step 6: Configure Multi-tenancy (Optional)

If using multi-tenancy with subdomains:

```bash
# Create central domains
php artisan tenants:install

# Create a tenant
php artisan tenants:create example.com
```

## MikroTik Router Management

### Overview

The system integrates with MikroTik routers using the RouterOS API to provide:

- **Real-time monitoring** of router status and statistics
- **Hotspot user management** for voucher-based internet access
- **Interface management** for network configuration
- **Configuration backup and restore** for disaster recovery
- **System log monitoring** for troubleshooting

### MikroTik Router Requirements

#### Hardware Requirements
- MikroTik router with RouterOS 6.40 or higher
- Minimum 64MB RAM (128MB+ recommended)
- API service enabled on the router

#### RouterOS Configuration

Before adding a router to the system, ensure the following configuration on your MikroTik device:

```routeros
# Enable API service
/ip service enable api

# Set API port (default 8728)
/ip service set api port=8728

# Create API user (recommended to create dedicated user)
/user add name=api-user password=secure-password group=full

# Configure hotspot (if not already configured)
/ip hotspot setup
```

#### Network Requirements
- Router must be accessible from the billing system server
- API port (default 8728) must be open
- Stable network connection between billing system and router

### Router Status Monitoring

The system continuously monitors router status:

- **Online**: Router is reachable and responding
- **Offline**: Router is not reachable
- **Error**: Router is reachable but has configuration issues

### Supported Operations

#### Device Management
- Add/edit/delete routers
- Test connectivity
- Monitor uptime and performance
- View system information

#### Hotspot Management
- Create/modify/delete hotspot users
- Monitor active sessions
- View user statistics
- Bulk user operations

#### Configuration Management
- Create configuration backups
- Restore from backups
- View configuration history
- Export/import configurations

#### Interface Management
- View interface status
- Enable/disable interfaces
- Monitor traffic statistics
- Configure interface settings

## Adding MikroTik Routers

### Method 1: Using the Web Interface

#### Step 1: Access Router Management

1. Log in to the billing system
2. Navigate to **Router Management** or **MikroTik** section
3. Click **"Add Router"** button

#### Step 2: Fill Router Information

**Basic Information:**
- **Router Name**: Descriptive name for the router (e.g., "Main Office Router")
- **IP Address**: Router's IP address (e.g., 192.168.1.1)
- **API Port**: RouterOS API port (default: 8728)

**Authentication:**
- **Username**: RouterOS username with API access
- **Password**: Password for the RouterOS user

**Location Information:**
- **Region**: Geographic region (e.g., "Central Region")
- **District**: District or city (e.g., "Kampala")
- **Coordinates** (optional): GPS coordinates for mapping

#### Step 3: Test Connection

1. Click **"Test Connection"** button
2. System will verify:
   - Network connectivity to the router
   - API service availability
   - Authentication credentials
   - RouterOS version compatibility

#### Step 4: Save Router

If connection test passes:
1. Click **"Add Router"** to save
2. Router will be added with "Online" status
3. System will begin monitoring the router

### Method 2: Using the API

#### Endpoint: `POST /api/v1/router-management`

**Request Body:**
```json
{
  "name": "Main Office Router",
  "ip_address": "192.168.1.1",
  "api_port": 8728,
  "username": "api-user",
  "password": "secure-password",
  "location": {
    "region": "Central Region",
    "district": "Kampala",
    "coordinates": {
      "lat": 0.3476,
      "lng": 32.5825
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Router created successfully",
  "data": {
    "id": "uuid-here",
    "name": "Main Office Router",
    "ip_address": "192.168.1.1",
    "status": "online",
    "created_at": "2026-01-12T10:30:00Z"
  }
}
```

### Method 3: Bulk Import (CSV)

Create a CSV file with router information:

```csv
name,ip_address,api_port,username,password,region,district,lat,lng
"Router 1","192.168.1.1",8728,"admin","password123","Central","Kampala",0.3476,32.5825
"Router 2","192.168.2.1",8728,"admin","password123","Western","Mbarara",-0.6067,30.6583
```

Import using the web interface or API endpoint.

### Router Configuration Best Practices

#### Security
```routeros
# Create dedicated API user with limited permissions
/user group add name=api-group policy=api,read,write,policy,test

# Create API user
/user add name=billing-api password=strong-random-password group=api-group

# Restrict API access to specific IP
/ip service set api address=your-billing-server-ip/32

# Use non-standard API port
/ip service set api port=8729
```

#### Hotspot Setup
```routeros
# Basic hotspot setup
/ip hotspot setup

# Configure user profiles
/ip hotspot user profile add name=1GB-DAILY rate-limit=2M/2M session-timeout=1d

# Set up login page customization
/ip hotspot walled-garden ip add dst-host=your-billing-domain.com
```

#### Backup Configuration
```routeros
# Enable automatic backups
/system backup save name=daily-backup

# Schedule regular backups
/system scheduler add name=daily-backup interval=1d on-event="/system backup save name=daily-backup"
```

### Troubleshooting Router Connection

#### Common Issues and Solutions

**1. Connection Timeout**
```
Error: Connection timeout
```
**Solutions:**
- Verify router IP address and network connectivity
- Check if API service is enabled: `/ip service print`
- Verify API port is correct and not blocked by firewall
- Test connectivity: `ping router-ip-address`

**2. Authentication Failed**
```
Error: Authentication failed
```
**Solutions:**
- Verify username and password are correct
- Check user permissions: `/user print detail`
- Ensure user has API access: `/user group print`
- Try connecting with Winbox to verify credentials

**3. API Service Disabled**
```
Error: Connection refused
```
**Solutions:**
- Enable API service: `/ip service enable api`
- Check API service status: `/ip service print`
- Verify API port: `/ip service set api port=8728`

**4. Insufficient Permissions**
```
Error: Access denied
```
**Solutions:**
- Check user group permissions: `/user group print`
- Add required policies to user group
- Use admin user for testing, then create dedicated API user

#### Connection Test Details

The system performs comprehensive connection testing:

1. **Network Connectivity**: TCP connection to API port
2. **API Handshake**: RouterOS API protocol negotiation
3. **Authentication**: Username/password verification
4. **Permission Check**: Verify required API access
5. **System Information**: Retrieve router identity and version

### Router Monitoring and Maintenance

#### Automatic Monitoring

The system automatically monitors:
- **Connection Status**: Every 30 seconds
- **System Statistics**: CPU, memory, uptime
- **Interface Statistics**: Traffic, errors, status
- **Active Users**: Hotspot sessions and usage
- **System Logs**: Error and warning messages

#### Manual Operations

**Test Connection:**
```bash
# Via web interface: Click "Test Connection" button
# Via API: POST /api/v1/router-management/test-connection
```

**Refresh Statistics:**
```bash
# Via web interface: Click "Refresh" button
# Via API: GET /api/v1/router-management/{id}/statistics
```

**Create Backup:**
```bash
# Via web interface: Router details → Backup → Create
# Via API: POST /api/v1/router-management/{id}/backup
```

#### Performance Optimization

**API Rate Limiting:**
- Maximum 100 requests per minute per router
- Automatic retry with exponential backoff
- Connection pooling for efficiency

**Caching:**
- Statistics cached for 30 seconds
- Interface data cached for 30 seconds
- User data cached for 30 seconds

**Error Handling:**
- Automatic retry on temporary failures
- Graceful degradation when router is offline
- Detailed error logging for troubleshooting

## Voucher Management

### Voucher Profiles

The system supports multiple voucher profiles:

#### Standard Profiles
- **1GB-DAILY**: 1GB data limit, 24-hour validity
- **5GB-WEEKLY**: 5GB data limit, 7-day validity
- **20GB-MONTHLY**: 20GB data limit, 30-day validity
- **UNLIMITED-DAILY**: Unlimited data, 24-hour validity
- **UNLIMITED-WEEKLY**: Unlimited data, 7-day validity
- **UNLIMITED-MONTHLY**: Unlimited data, 30-day validity

#### Custom Profiles
Create custom profiles with:
- Custom data limits (MB/GB)
- Custom validity periods (hours/days)
- Custom pricing
- Custom speed limits
- Custom user restrictions

### Voucher Generation

#### Bulk Generation
```json
{
  "quantity": 100,
  "profile": "1GB-DAILY",
  "validity_hours": 24,
  "price": 5000,
  "data_limit_mb": 1024
}
```

#### Advanced Generation
```json
{
  "profile": "CUSTOM",
  "validity_hours": 48,
  "price": 10000,
  "data_limit_mb": 2048,
  "currency": "UGX",
  "code_prefix": "BIL",
  "auto_activate": true,
  "send_sms": true,
  "customer_name": "John Doe",
  "customer_phone": "+256700123456"
}
```

### Voucher Operations

#### Available Actions
- **View Details**: Complete voucher information
- **Resend SMS**: Send voucher details via SMS
- **Transfer**: Move voucher to different customer
- **Refund**: Process voucher refund
- **Disable**: Deactivate voucher before expiry

#### Voucher Status
- **Active**: Voucher is valid and can be used
- **Expired**: Voucher validity period has ended
- **Disabled**: Voucher has been manually disabled
- **Used**: Voucher has been consumed (data limit reached)

## API Documentation

### Authentication

All API requests require authentication using Laravel Sanctum tokens:

```http
Authorization: Bearer your-api-token
```

### Router Management Endpoints

#### List Routers
```http
GET /api/v1/router-management
```

**Query Parameters:**
- `search`: Search by name, IP, or location
- `status`: Filter by status (online/offline/error)
- `sort_by`: Sort field (name/ip_address/status/created_at)
- `sort_order`: Sort direction (asc/desc)
- `per_page`: Results per page (max 100)

#### Create Router
```http
POST /api/v1/router-management
```

#### Update Router
```http
PUT /api/v1/router-management/{id}
```

#### Delete Router
```http
DELETE /api/v1/router-management/{id}
```

#### Test Connection
```http
POST /api/v1/router-management/test-connection
```

### Voucher Management Endpoints

#### List Vouchers
```http
GET /api/v1/vouchers
```

#### Generate Vouchers
```http
POST /api/v1/vouchers/batch-generate
```

#### Voucher Details
```http
GET /api/v1/vouchers/{code}
```

#### Voucher Actions
```http
POST /api/v1/vouchers/{code}/resend-sms
POST /api/v1/vouchers/{code}/transfer
POST /api/v1/vouchers/{code}/refund
POST /api/v1/vouchers/{code}/disable
```

### MikroTik API Integration

#### Get Device Statistics
```http
GET /api/v1/mikrotik/{id}/statistics
```

#### Get Interfaces
```http
GET /api/v1/mikrotik/{id}/interfaces
```

#### Get Hotspot Users
```http
GET /api/v1/mikrotik/{id}/users
```

#### Create Hotspot User
```http
POST /api/v1/mikrotik/{id}/users
```

## Frontend Components

### Key Components

#### Router Management
- **RouterManagement.vue**: Main router management interface
- **RouterAddModal.vue**: Modal for adding new routers
- **MikroTikConfiguration.vue**: Router configuration interface
- **MikroTikMonitor.vue**: Real-time monitoring dashboard

#### Voucher Management
- **VoucherManagement.vue**: Main voucher interface
- **VoucherRefundModal.vue**: Voucher refund processing
- **VoucherTransferModal.vue**: Voucher transfer interface

#### Common Components
- **DataTable.vue**: Advanced data table with sorting, filtering, pagination
- **Modal.vue**: Reusable modal component
- **ConnectionStatus.vue**: Real-time connection status indicator
- **NotificationCenter.vue**: Toast notifications

### State Management

#### Stores (Pinia)
- **app.ts**: Global application state
- **realtime.ts**: WebSocket and real-time updates
- **auth.ts**: Authentication state
- **router.ts**: Router management state
- **voucher.ts**: Voucher management state

### Routing

#### Main Routes
- `/`: Landing page
- `/login`: Authentication
- `/app/dashboard`: Main dashboard
- `/app/routers`: Router management
- `/app/vouchers`: Voucher management
- `/app/customers`: Customer management
- `/app/payments`: Payment management
- `/app/settings`: System settings

## Database Schema

### Core Tables

#### mikrotik_devices
```sql
CREATE TABLE mikrotik_devices (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ip_address INET NOT NULL,
    location JSON NOT NULL,
    api_port INTEGER DEFAULT 8728,
    username VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    last_seen TIMESTAMP NULL,
    uptime_seconds BIGINT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### mikrotik_users
```sql
CREATE TABLE mikrotik_users (
    id UUID PRIMARY KEY,
    device_id UUID REFERENCES mikrotik_devices(id),
    username VARCHAR(100) NOT NULL,
    password VARCHAR(100) NOT NULL,
    profile VARCHAR(100) NOT NULL,
    voucher_id UUID REFERENCES vouchers(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### mikrotik_config_history
```sql
CREATE TABLE mikrotik_config_history (
    id UUID PRIMARY KEY,
    device_id UUID REFERENCES mikrotik_devices(id),
    configuration_data JSON NOT NULL,
    change_type ENUM('backup', 'restore', 'update'),
    changed_by UUID REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### vouchers
```sql
CREATE TABLE vouchers (
    id UUID PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(50),
    profile VARCHAR(100) NOT NULL,
    validity_hours INTEGER NOT NULL,
    data_limit_mb INTEGER,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'UGX',
    status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
    customer_id UUID REFERENCES customers(id),
    mikrotik_device_id UUID REFERENCES mikrotik_devices(id),
    activated_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Relationships

- **MikroTikDevice** → **MikroTikUser** (One-to-Many)
- **MikroTikDevice** → **MikroTikConfigHistory** (One-to-Many)
- **MikroTikDevice** → **Voucher** (One-to-Many)
- **Voucher** → **Customer** (Many-to-One)
- **Voucher** → **MikroTikUser** (One-to-One)

## Configuration

### Environment Variables

#### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_system
DB_USERNAME=username
DB_PASSWORD=password
```

#### Redis (Optional)
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Queue Configuration
```env
QUEUE_CONNECTION=redis
```

#### Broadcasting (WebSockets)
```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
```

#### SMS Configuration
```env
SMS_DRIVER=twilio
SMS_FROM=+1234567890
TWILIO_SID=your-twilio-sid
TWILIO_TOKEN=your-twilio-token
```

#### Payment Gateway
```env
PAYMENT_GATEWAY=stripe
STRIPE_KEY=your-stripe-key
STRIPE_SECRET=your-stripe-secret
```

### MikroTik API Configuration

#### Rate Limiting
```php
// config/mikrotik.php
return [
    'rate_limit' => [
        'max_requests' => 100, // per minute
        'timeout' => 10, // seconds
        'retries' => 3,
    ],
    'cache_ttl' => 30, // seconds
];
```

#### Connection Pool
```php
'connection_pool' => [
    'max_connections' => 10,
    'idle_timeout' => 300, // seconds
    'connection_timeout' => 10, // seconds
],
```

## Troubleshooting

### Common Issues

#### 1. Router Connection Issues

**Problem**: Router shows as offline
**Solutions**:
1. Check network connectivity
2. Verify API service is enabled
3. Check firewall settings
4. Verify credentials

**Problem**: API timeout errors
**Solutions**:
1. Increase timeout in configuration
2. Check router CPU usage
3. Reduce API request frequency
4. Check network latency

#### 2. Voucher Generation Issues

**Problem**: Vouchers not created on router
**Solutions**:
1. Check router connection status
2. Verify hotspot configuration
3. Check user profile exists
4. Review system logs

#### 3. Frontend Issues

**Problem**: Components not loading
**Solutions**:
1. Check browser console for errors
2. Verify API endpoints are accessible
3. Check authentication token
4. Clear browser cache

#### 4. Database Issues

**Problem**: Migration errors
**Solutions**:
1. Check database permissions
2. Verify database connection
3. Check for conflicting migrations
4. Review database logs

### Logging and Debugging

#### Laravel Logs
```bash
# View logs
tail -f storage/logs/laravel.log

# Clear logs
php artisan log:clear
```

#### MikroTik API Debugging
```php
// Enable debug mode in .env
MIKROTIK_DEBUG=true

// Check logs for API calls
Log::info('MikroTik API call', [
    'device' => $device->name,
    'operation' => 'testConnection',
    'result' => $result
]);
```

#### Frontend Debugging
```javascript
// Enable debug mode
localStorage.setItem('debug', 'true');

// Check console for API calls
console.log('API Response:', response);
```

### Performance Optimization

#### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_vouchers_status ON vouchers(status);
CREATE INDEX idx_vouchers_expires_at ON vouchers(expires_at);
CREATE INDEX idx_mikrotik_devices_status ON mikrotik_devices(status);
```

#### Caching Strategy
```php
// Cache frequently accessed data
Cache::remember('router_statistics_' . $device->id, 30, function () use ($device) {
    return $this->apiService->getDeviceStatistics($device);
});
```

#### Queue Optimization
```bash
# Run multiple queue workers
php artisan queue:work --queue=high,default --tries=3
```

### Monitoring and Alerts

#### System Health Checks
```bash
# Check system status
php artisan system:health

# Monitor queue status
php artisan queue:monitor

# Check router connectivity
php artisan mikrotik:monitor
```

#### Automated Alerts
```php
// Set up alerts for critical issues
if ($device->status === 'offline') {
    Notification::send($admins, new RouterOfflineNotification($device));
}
```

---

## Support and Maintenance

### Regular Maintenance Tasks

1. **Daily**:
   - Monitor system logs
   - Check router connectivity
   - Review voucher generation

2. **Weekly**:
   - Database backup
   - Performance review
   - Security updates

3. **Monthly**:
   - System updates
   - Configuration backup
   - Capacity planning

### Getting Help

- **Documentation**: This comprehensive guide
- **Logs**: Check Laravel and system logs
- **API Testing**: Use Postman or similar tools
- **Community**: Laravel and Vue.js communities

### Version Information

- **System Version**: 1.0.0
- **Laravel**: 12.0
- **Vue.js**: 3.5.26
- **PHP**: 8.2+
- **Node.js**: 18+

---

*This documentation is maintained and updated regularly. For the latest version, check the project repository.*