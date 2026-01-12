# MikroTik Router Setup Guide

## Complete Guide to Adding and Configuring MikroTik Routers

### Table of Contents
1. [Prerequisites](#prerequisites)
2. [MikroTik Router Preparation](#mikrotik-router-preparation)
3. [Adding Routers via Web Interface](#adding-routers-via-web-interface)
4. [Adding Routers via API](#adding-routers-via-api)
5. [Bulk Router Import](#bulk-router-import)
6. [Router Configuration Best Practices](#router-configuration-best-practices)
7. [Troubleshooting Guide](#troubleshooting-guide)
8. [Advanced Configuration](#advanced-configuration)

---

## Prerequisites

### System Requirements
- ✅ Billing system installed and running
- ✅ Network connectivity to MikroTik routers
- ✅ Administrative access to MikroTik devices
- ✅ RouterOS 6.40 or higher on MikroTik devices

### Network Requirements
- 🌐 Routers must be accessible from billing system server
- 🔓 API port (default 8728) must be open
- 📡 Stable network connection (recommended: < 100ms latency)
- 🔒 Firewall rules configured to allow API access

---

## MikroTik Router Preparation

### Step 1: Enable API Service

Connect to your MikroTik router via Winbox, SSH, or web interface:

```routeros
# Enable API service
/ip service enable api

# Verify API service is running
/ip service print
```

**Expected Output:**
```
Flags: X - disabled, I - invalid 
 #   NAME     PORT ADDRESS
 0   telnet   23        
 1   ftp      21        
 2   www      80        
 3   ssh      22        
 4   www-ssl  443       
 5   api      8728      
 6   winbox   8291      
 7   api-ssl  8729
```

### Step 2: Configure API Port (Optional)

If you want to use a custom port:

```routeros
# Set custom API port
/ip service set api port=8729

# Verify the change
/ip service print where name=api
```

### Step 3: Create Dedicated API User

**⚠️ Security Best Practice**: Create a dedicated user for API access instead of using admin.

```routeros
# Create API user group with limited permissions
/user group add name=api-group policy=api,read,write,policy,test,password

# Create dedicated API user
/user add name=billing-api password=SecurePassword123! group=api-group

# Verify user creation
/user print where name=billing-api
```

### Step 4: Configure Hotspot (If Not Already Done)

```routeros
# Run hotspot setup wizard
/ip hotspot setup

# Or configure manually:
# 1. Set up IP pool
/ip pool add name=hotspot-pool ranges=192.168.1.100-192.168.1.200

# 2. Create hotspot profile
/ip hotspot profile add name=default-profile hotspot-address=192.168.1.1 dns-name=hotspot.local

# 3. Create hotspot server
/ip hotspot add name=hotspot1 interface=bridge address-pool=hotspot-pool profile=default-profile

# 4. Create user profiles
/ip hotspot user profile add name=1GB-DAILY rate-limit=2M/2M session-timeout=1d
/ip hotspot user profile add name=5GB-WEEKLY rate-limit=5M/5M session-timeout=7d
/ip hotspot user profile add name=UNLIMITED-DAILY session-timeout=1d
```

### Step 5: Test API Access

Test the API connection from your billing system server:

```bash
# Test with telnet (should connect)
telnet router-ip-address 8728

# Test with curl (should get binary response)
curl -v telnet://router-ip-address:8728
```

---

## Adding Routers via Web Interface

### Method 1: Using VoucherManagement Component

1. **Navigate to Voucher Management**
   - Log in to the billing system
   - Go to **Vouchers** section
   - Look for **"Add Router"** button in the header actions

2. **Click "Add Router"**
   - This opens the **RouterAddModal** component
   - Modal has a clean, dark-themed interface

3. **Fill in Basic Information**
   ```
   Router Name: Main Office Router
   IP Address: 192.168.1.1
   Port: 8728
   Location: Office Building, Floor 2
   ```

4. **Enter Authentication Details**
   ```
   Username: billing-api
   Password: SecurePassword123!
   ```

5. **Test Connection**
   - Click **"Test Connection"** button
   - Wait for connection verification
   - ✅ Success: "Connection successful!"
   - ❌ Error: Check troubleshooting section

6. **Save Router**
   - Click **"Add Router"** button
   - Router will be saved and monitoring will begin

### Method 2: Using RouterManagement Component

1. **Navigate to Router Management**
   - Go to **MikroTik** or **Router Management** section
   - This shows the main router management interface

2. **Click "Add Router"**
   - Button is in the header actions
   - Opens the built-in router form modal

3. **Complete the Form**
   
   **Basic Information Tab:**
   ```
   Router Name: Branch Office Router
   IP Address: 10.0.1.1
   API Port: 8728
   Location: Branch Office, Kampala
   ```

   **Authentication Tab:**
   ```
   Username: billing-api
   Password: SecurePassword123!
   ```

   **Location Details:**
   ```
   Region: Central Region
   District: Kampala
   Coordinates (optional):
     Latitude: 0.3476
     Longitude: 32.5825
   ```

4. **Test Connection**
   - Click **"Test Connection"**
   - System performs comprehensive testing:
     - Network connectivity
     - API service availability
     - Authentication verification
     - Permission validation

5. **Save and Monitor**
   - Click **"Add Router"**
   - Router appears in the management table
   - Status should show as "Online"

---

## Adding Routers via API

### Endpoint: POST /api/v1/router-management

#### Request Headers
```http
Content-Type: application/json
Authorization: Bearer your-api-token
Accept: application/json
```

#### Request Body
```json
{
  "name": "Main Office Router",
  "ip_address": "192.168.1.1",
  "api_port": 8728,
  "username": "billing-api",
  "password": "SecurePassword123!",
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

#### Success Response (201 Created)
```json
{
  "success": true,
  "message": "Router created successfully",
  "data": {
    "id": "9c8e7f6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
    "name": "Main Office Router",
    "ip_address": "192.168.1.1",
    "api_port": 8728,
    "status": "online",
    "location": {
      "region": "Central Region",
      "district": "Kampala",
      "coordinates": {
        "lat": 0.3476,
        "lng": 32.5825
      }
    },
    "last_seen": "2026-01-12T10:30:00Z",
    "created_at": "2026-01-12T10:30:00Z",
    "updated_at": "2026-01-12T10:30:00Z"
  }
}
```

#### Error Response (400 Bad Request)
```json
{
  "success": false,
  "message": "Router connection test failed",
  "error": "Connection timeout - unable to reach router at 192.168.1.1:8728"
}
```

#### Validation Error (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "ip_address": ["The ip address field must be a valid IP address."],
    "name": ["The name field is required."]
  }
}
```

### Example using cURL

```bash
curl -X POST http://your-billing-system.com/api/v1/router-management \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-api-token" \
  -d '{
    "name": "Main Office Router",
    "ip_address": "192.168.1.1",
    "api_port": 8728,
    "username": "billing-api",
    "password": "SecurePassword123!",
    "location": {
      "region": "Central Region",
      "district": "Kampala"
    }
  }'
```

### Example using JavaScript/Axios

```javascript
const axios = require('axios');

const addRouter = async () => {
  try {
    const response = await axios.post('http://your-billing-system.com/api/v1/router-management', {
      name: 'Main Office Router',
      ip_address: '192.168.1.1',
      api_port: 8728,
      username: 'billing-api',
      password: 'SecurePassword123!',
      location: {
        region: 'Central Region',
        district: 'Kampala',
        coordinates: {
          lat: 0.3476,
          lng: 32.5825
        }
      }
    }, {
      headers: {
        'Authorization': 'Bearer your-api-token',
        'Content-Type': 'application/json'
      }
    });

    console.log('Router added successfully:', response.data);
  } catch (error) {
    console.error('Error adding router:', error.response.data);
  }
};

addRouter();
```

---

## Bulk Router Import

### Method 1: CSV Import via Web Interface

1. **Prepare CSV File**

Create a file named `routers.csv`:

```csv
name,ip_address,api_port,username,password,region,district,lat,lng
"Main Office Router","192.168.1.1",8728,"billing-api","SecurePassword123!","Central","Kampala",0.3476,32.5825
"Branch Router 1","192.168.2.1",8728,"billing-api","SecurePassword123!","Western","Mbarara",-0.6067,30.6583
"Branch Router 2","192.168.3.1",8728,"billing-api","SecurePassword123!","Eastern","Jinja",0.4314,33.2044
"Branch Router 3","10.0.1.1",8729,"api-user","DifferentPassword456!","Northern","Gulu",2.7796,32.2992
```

2. **Upload via Web Interface**
   - Go to Router Management
   - Click **"Import Routers"** button
   - Select your CSV file
   - Review the preview
   - Click **"Import"** to process

3. **Monitor Import Progress**
   - System will test each router connection
   - Progress bar shows completion status
   - Failed imports will be reported with reasons

### Method 2: Bulk API Import

#### Endpoint: POST /api/v1/router-management/bulk-import

```json
{
  "routers": [
    {
      "name": "Router 1",
      "ip_address": "192.168.1.1",
      "api_port": 8728,
      "username": "billing-api",
      "password": "SecurePassword123!",
      "location": {
        "region": "Central",
        "district": "Kampala"
      }
    },
    {
      "name": "Router 2",
      "ip_address": "192.168.2.1",
      "api_port": 8728,
      "username": "billing-api",
      "password": "SecurePassword123!",
      "location": {
        "region": "Western",
        "district": "Mbarara"
      }
    }
  ],
  "test_connections": true,
  "skip_duplicates": true
}
```

#### Response
```json
{
  "success": true,
  "message": "Bulk import completed",
  "results": {
    "total": 2,
    "successful": 2,
    "failed": 0,
    "skipped": 0
  },
  "details": [
    {
      "name": "Router 1",
      "status": "success",
      "id": "uuid-1"
    },
    {
      "name": "Router 2",
      "status": "success",
      "id": "uuid-2"
    }
  ]
}
```

---

## Router Configuration Best Practices

### Security Configuration

#### 1. Secure API Access
```routeros
# Create dedicated API user group
/user group add name=billing-api-group policy=api,read,write,test

# Create API user with strong password
/user add name=billing-system password=VerySecurePassword123! group=billing-api-group

# Restrict API access to billing system IP only
/ip service set api address=your-billing-server-ip/32

# Use non-standard port for additional security
/ip service set api port=8729

# Enable API over SSL (recommended for production)
/ip service enable api-ssl
/ip service set api-ssl port=8730 certificate=your-certificate
```

#### 2. Firewall Rules
```routeros
# Allow API access only from billing system
/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=billing-server-ip action=accept comment="Billing System API"

# Block API access from other sources
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=drop comment="Block API from others"
```

### Hotspot Configuration

#### 1. User Profiles Setup
```routeros
# Create user profiles matching your voucher system
/ip hotspot user profile add name=1GB-DAILY rate-limit=2M/2M session-timeout=1d idle-timeout=5m
/ip hotspot user profile add name=5GB-WEEKLY rate-limit=5M/5M session-timeout=7d idle-timeout=10m
/ip hotspot user profile add name=20GB-MONTHLY rate-limit=10M/10M session-timeout=30d idle-timeout=15m
/ip hotspot user profile add name=UNLIMITED-DAILY session-timeout=1d idle-timeout=30m
/ip hotspot user profile add name=UNLIMITED-WEEKLY session-timeout=7d idle-timeout=30m
/ip hotspot user profile add name=UNLIMITED-MONTHLY session-timeout=30d idle-timeout=30m

# Set data limits for profiles (if supported)
/ip hotspot user profile set 1GB-DAILY limit-bytes-total=1073741824
/ip hotspot user profile set 5GB-WEEKLY limit-bytes-total=5368709120
/ip hotspot user profile set 20GB-MONTHLY limit-bytes-total=21474836480
```

#### 2. Walled Garden Configuration
```routeros
# Allow access to billing system without authentication
/ip hotspot walled-garden ip add dst-host=your-billing-domain.com comment="Billing System"

# Allow access to payment gateways
/ip hotspot walled-garden ip add dst-host=api.stripe.com comment="Stripe Payment"
/ip hotspot walled-garden ip add dst-host=api.paypal.com comment="PayPal Payment"

# Allow DNS resolution
/ip hotspot walled-garden ip add dst-host=8.8.8.8 comment="Google DNS"
/ip hotspot walled-garden ip add dst-host=1.1.1.1 comment="Cloudflare DNS"
```

#### 3. Login Page Customization
```routeros
# Upload custom login page
/file upload

# Set custom login page
/ip hotspot profile set default html-directory=custom-login

# Configure redirect URL after login
/ip hotspot profile set default login-by=http-chap,http-pap
```

### Network Configuration

#### 1. IP Pool Management
```routeros
# Create IP pools for different user types
/ip pool add name=daily-users ranges=192.168.1.100-192.168.1.150
/ip pool add name=weekly-users ranges=192.168.1.151-192.168.1.200
/ip pool add name=monthly-users ranges=192.168.1.201-192.168.1.250

# Assign pools to profiles
/ip hotspot user profile set 1GB-DAILY address-pool=daily-users
/ip hotspot user profile set 5GB-WEEKLY address-pool=weekly-users
/ip hotspot user profile set 20GB-MONTHLY address-pool=monthly-users
```

#### 2. Quality of Service (QoS)
```routeros
# Create queue trees for traffic management
/queue tree add name=hotspot-download parent=bridge max-limit=100M
/queue tree add name=hotspot-upload parent=bridge max-limit=50M

# Set per-user limits in profiles (already done above)
```

### Monitoring and Logging

#### 1. System Logging
```routeros
# Enable detailed logging
/system logging add topics=hotspot,info action=memory
/system logging add topics=api,info action=memory
/system logging add topics=error action=memory

# Set log buffer size
/system logging set 0 memory-lines=1000
```

#### 2. SNMP Configuration (Optional)
```routeros
# Enable SNMP for monitoring
/snmp set enabled=yes contact="admin@yourdomain.com" location="Your Location"
/snmp community set public address=billing-server-ip/32
```

---

## Troubleshooting Guide

### Connection Issues

#### Problem: "Connection timeout"
```
Error: Connection timeout - unable to reach router at 192.168.1.1:8728
```

**Diagnosis Steps:**
1. **Test Network Connectivity**
   ```bash
   # From billing system server
   ping 192.168.1.1
   telnet 192.168.1.1 8728
   ```

2. **Check Router API Service**
   ```routeros
   # On MikroTik router
   /ip service print
   /ip service enable api
   ```

3. **Verify Firewall Rules**
   ```routeros
   # Check if API port is blocked
   /ip firewall filter print where dst-port=8728
   
   # Temporarily disable firewall for testing
   /ip firewall filter disable [find]
   ```

**Solutions:**
- ✅ Enable API service: `/ip service enable api`
- ✅ Check firewall rules and allow API port
- ✅ Verify network routing between billing system and router
- ✅ Check if router is behind NAT (configure port forwarding)

#### Problem: "Authentication failed"
```
Error: Authentication failed - invalid username or password
```

**Diagnosis Steps:**
1. **Verify Credentials**
   ```routeros
   # Check if user exists
   /user print where name=billing-api
   
   # Test login via Winbox/SSH with same credentials
   ```

2. **Check User Permissions**
   ```routeros
   # Verify user group has API access
   /user group print where name=api-group
   /user group set api-group policy=api,read,write,test
   ```

**Solutions:**
- ✅ Verify username and password are correct
- ✅ Ensure user has API permissions
- ✅ Check if user account is disabled
- ✅ Try with admin user for testing

#### Problem: "Permission denied"
```
Error: Permission denied - insufficient privileges
```

**Diagnosis Steps:**
1. **Check User Group Policies**
   ```routeros
   /user print detail where name=billing-api
   /user group print detail where name=api-group
   ```

**Solutions:**
- ✅ Add required policies to user group: `policy=api,read,write,test`
- ✅ Use admin user temporarily to verify API functionality
- ✅ Create new user with proper permissions

### API Service Issues

#### Problem: "Connection refused"
```
Error: Connection refused
```

**Diagnosis Steps:**
1. **Check API Service Status**
   ```routeros
   /ip service print where name=api
   ```

2. **Verify Port Configuration**
   ```routeros
   /ip service print where name=api
   ```

**Solutions:**
- ✅ Enable API service: `/ip service enable api`
- ✅ Check if correct port is configured
- ✅ Verify no other service is using the port

#### Problem: "SSL/TLS errors" (when using API-SSL)
```
Error: SSL handshake failed
```

**Solutions:**
- ✅ Use regular API port (8728) instead of SSL port (8729)
- ✅ Configure proper SSL certificates on router
- ✅ Update billing system to handle SSL connections

### Performance Issues

#### Problem: "Slow API responses"
```
Warning: API response time > 5 seconds
```

**Diagnosis Steps:**
1. **Check Router CPU Usage**
   ```routeros
   /system resource print
   ```

2. **Monitor Network Latency**
   ```bash
   ping -c 10 192.168.1.1
   ```

**Solutions:**
- ✅ Reduce API request frequency in billing system
- ✅ Upgrade router hardware if CPU usage is high
- ✅ Optimize network connection between systems
- ✅ Enable caching in billing system

### Hotspot Issues

#### Problem: "Users not created on router"
```
Error: Failed to create hotspot user
```

**Diagnosis Steps:**
1. **Check Hotspot Configuration**
   ```routeros
   /ip hotspot print
   /ip hotspot user profile print
   ```

2. **Verify User Profile Exists**
   ```routeros
   /ip hotspot user profile print where name=1GB-DAILY
   ```

**Solutions:**
- ✅ Configure hotspot service on router
- ✅ Create required user profiles
- ✅ Check if username already exists
- ✅ Verify API user has write permissions

#### Problem: "Profile not found"
```
Error: Profile '1GB-DAILY' not found
```

**Solutions:**
- ✅ Create missing user profiles on router
- ✅ Update billing system profile names to match router
- ✅ Use default profile temporarily

### System Integration Issues

#### Problem: "Router shows offline in billing system"
```
Status: Offline (but router is actually online)
```

**Diagnosis Steps:**
1. **Check Monitoring Service**
   ```bash
   # Check if monitoring job is running
   php artisan queue:work --queue=monitoring
   ```

2. **Review System Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep mikrotik
   ```

**Solutions:**
- ✅ Restart monitoring service
- ✅ Check network connectivity
- ✅ Verify API credentials haven't changed
- ✅ Clear router cache in billing system

---

## Advanced Configuration

### High Availability Setup

#### 1. Router Redundancy
```routeros
# Configure VRRP for router redundancy
/interface vrrp add name=vrrp1 interface=ether1 vrid=1 priority=200

# Sync hotspot users between routers
/tool user-manager customer add login=sync-user password=sync-pass
```

#### 2. Load Balancing
```routeros
# Configure load balancing for multiple internet connections
/ip route add dst-address=0.0.0.0/0 gateway=isp1-gateway distance=1 check-gateway=ping
/ip route add dst-address=0.0.0.0/0 gateway=isp2-gateway distance=2 check-gateway=ping
```

### Advanced Monitoring

#### 1. Custom Scripts
```routeros
# Create script to monitor system health
/system script add name=health-check source={
    :local cpu [/system resource get cpu-load];
    :local memory [/system resource get free-memory];
    :if ($cpu > 80) do={
        /log warning "High CPU usage: $cpu%";
    };
    :if ($memory < 10000000) do={
        /log warning "Low memory: $memory bytes";
    };
}

# Schedule script to run every 5 minutes
/system scheduler add name=health-monitor interval=5m on-event=health-check
```

#### 2. External Monitoring Integration
```routeros
# Configure SNMP for external monitoring
/snmp set enabled=yes
/snmp community set public address=monitoring-server-ip/32

# Enable Netflow for traffic analysis
/ip traffic-flow set enabled=yes interfaces=all
/ip traffic-flow target add address=netflow-collector-ip port=2055
```

### Backup and Recovery

#### 1. Automated Backups
```routeros
# Create backup script
/system script add name=daily-backup source={
    /system backup save name=("backup-" . [/system clock get date]);
    /export file=("config-" . [/system clock get date]);
}

# Schedule daily backups
/system scheduler add name=backup-schedule interval=1d on-event=daily-backup start-time=02:00:00
```

#### 2. Configuration Sync
```bash
# Sync configurations to billing system
php artisan mikrotik:backup-all

# Restore configuration from billing system
php artisan mikrotik:restore {router-id} {backup-id}
```

### Custom Integration

#### 1. Webhook Integration
```php
// Custom webhook for router events
Route::post('/webhook/mikrotik/{router}', function (Request $request, $router) {
    $event = $request->input('event');
    $data = $request->input('data');
    
    // Process router event
    event(new RouterEvent($router, $event, $data));
    
    return response()->json(['status' => 'received']);
});
```

#### 2. Custom API Endpoints
```php
// Custom router management endpoints
Route::group(['prefix' => 'api/v1/routers'], function () {
    Route::post('{router}/reboot', [RouterController::class, 'reboot']);
    Route::post('{router}/upgrade', [RouterController::class, 'upgrade']);
    Route::get('{router}/traffic', [RouterController::class, 'getTraffic']);
});
```

---

## Testing and Validation

### Connection Testing Checklist

- [ ] Network connectivity (ping)
- [ ] API port accessibility (telnet)
- [ ] Authentication (login test)
- [ ] Permission verification (API call test)
- [ ] Hotspot functionality (user creation test)
- [ ] Profile compatibility (profile list)
- [ ] Performance testing (response time)

### Automated Testing

```bash
# Run router connectivity tests
php artisan test --filter=RouterConnectivityTest

# Test MikroTik API integration
php artisan test --filter=MikroTikApiTest

# Validate router configuration
php artisan mikrotik:validate-all
```

### Manual Testing Steps

1. **Basic Connectivity**
   ```bash
   ping router-ip
   telnet router-ip 8728
   ```

2. **API Authentication**
   - Test login via billing system
   - Verify user permissions
   - Check API response times

3. **Hotspot Functionality**
   - Create test voucher
   - Verify user creation on router
   - Test internet access with voucher

4. **Monitoring Integration**
   - Check router status in dashboard
   - Verify statistics updates
   - Test alert notifications

---

## Support and Maintenance

### Regular Maintenance Tasks

#### Daily
- [ ] Check router connectivity status
- [ ] Review system logs for errors
- [ ] Monitor API response times
- [ ] Verify voucher creation success rate

#### Weekly
- [ ] Update router firmware (if needed)
- [ ] Review and clean old log files
- [ ] Check backup integrity
- [ ] Performance optimization review

#### Monthly
- [ ] Security audit of API users
- [ ] Configuration backup verification
- [ ] Capacity planning review
- [ ] Documentation updates

### Emergency Procedures

#### Router Offline Emergency
1. Check network connectivity
2. Verify router power and hardware
3. Test API service availability
4. Check firewall and security settings
5. Contact network administrator if needed

#### Mass Router Failure
1. Check billing system server status
2. Verify network infrastructure
3. Test with single router first
4. Implement temporary workarounds
5. Escalate to system administrator

---

## Conclusion

This comprehensive guide covers all aspects of adding and managing MikroTik routers in the billing system. Follow the step-by-step instructions, implement the security best practices, and use the troubleshooting guide to resolve any issues.

For additional support:
- Check system logs for detailed error messages
- Use the built-in connection testing tools
- Refer to MikroTik RouterOS documentation
- Contact system administrator for complex issues

**Remember**: Always test configurations in a development environment before applying to production routers.

---

*Last updated: January 12, 2026*
*Version: 1.0.0*