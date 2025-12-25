<template>
    <div class="settings-dashboard">
        <div class="page-header">
            <h1 class="page-title">System Settings</h1>
            <div class="page-actions">
                <button class="btn btn-secondary" @click="resetForm">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn btn-primary" @click="saveSettings" :disabled="saving">
                    <i class="fas fa-save" :class="{ 'fa-spin': saving }"></i>
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <div v-if="requiresRestart" class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Some settings require a system restart to take effect.
            <button class="btn-alert" @click="restartSystem">Restart Now</button>
        </div>

        <div v-if="notifications.length > 0" class="notifications-section">
            <div v-for="notification in notifications" :key="notification.type" class="notification" :class="notification.type">
                <i :class="notification.icon"></i>
                <span>{{ notification.message }}</span>
            </div>
        </div>

        <div class="settings-container">
            <div class="settings-sidebar">
                <nav class="settings-nav">
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        @click="activeCategory = category.id"
                        :class="{ active: activeCategory === category.id }"
                        class="nav-button"
                    >
                        <i :class="category.icon"></i>
                        <span>{{ category.name }}</span>
                    </button>
                </nav>

                <div class="sidebar-footer">
                    <div class="settings-info">
                        <div class="info-item">
                            <span class="info-label">Last Modified:</span>
                            <span class="info-value">{{ lastModified }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Changes:</span>
                            <span class="info-value badge">{{ changeCount }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-content">
                <div v-if="loading" class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Loading settings...</p>
                </div>

                <div v-else class="category-content">
                    <!-- General Settings -->
                    <div v-if="activeCategory === 'general'" class="settings-section">
                        <h3 class="section-title">
                            <i class="fas fa-cog"></i>
                            General Settings
                        </h3>

                        <div class="settings-form">
                            <div class="form-group">
                                <label for="app_name">Application Name</label>
                                <input
                                    type="text"
                                    id="app_name"
                                    v-model="settings.general.app_name"
                                    class="form-control"
                                >
                                <small class="form-text">The name displayed throughout the application</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="app_url">Application URL</label>
                                    <input
                                        type="url"
                                        id="app_url"
                                        v-model="settings.general.app_url"
                                        class="form-control"
                                    >
                                    <small class="form-text">Base URL of your application</small>
                                </div>

                                <div class="form-group">
                                    <label for="timezone">Timezone</label>
                                    <select id="timezone" v-model="settings.general.timezone" class="form-control">
                                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="locale">Default Language</label>
                                    <select id="locale" v-model="settings.general.locale" class="form-control">
                                        <option value="en">English</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="de">German</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="max_login_attempts">Max Login Attempts</label>
                                    <input
                                        type="number"
                                        id="max_login_attempts"
                                        v-model="settings.general.max_login_attempts"
                                        min="1"
                                        max="10"
                                        class="form-control"
                                    >
                                    <small class="form-text">Maximum failed login attempts before lockout</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="support_email">Support Email</label>
                                <input
                                    type="email"
                                    id="support_email"
                                    v-model="settings.general.support_email"
                                    class="form-control"
                                >
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact_phone">Contact Phone</label>
                                    <input
                                        type="tel"
                                        id="contact_phone"
                                        v-model="settings.general.contact_phone"
                                        class="form-control"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="session_lifetime">Session Lifetime (minutes)</label>
                                    <input
                                        type="number"
                                        id="session_lifetime"
                                        v-model="settings.general.session_lifetime"
                                        min="15"
                                        max="1440"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="terms_url">Terms & Conditions URL</label>
                                <input
                                    type="url"
                                    id="terms_url"
                                    v-model="settings.general.terms_url"
                                    class="form-control"
                                >
                            </div>

                            <div class="form-group">
                                <label for="privacy_url">Privacy Policy URL</label>
                                <input
                                    type="url"
                                    id="privacy_url"
                                    v-model="settings.general.privacy_url"
                                    class="form-control"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Tenancy Settings -->
                    <div v-else-if="activeCategory === 'tenancy'" class="settings-section">
                        <h3 class="section-title">
                            <i class="fas fa-database"></i>
                            Tenancy Settings
                        </h3>

                        <div class="settings-form">
                            <div class="form-group">
                                <label for="central_domains">Central Domains</label>
                                <textarea
                                    id="central_domains"
                                    v-model="centralDomainsText"
                                    class="form-control"
                                    rows="3"
                                    placeholder="example.com&#10;admin.example.com"
                                ></textarea>
                                <small class="form-text">One domain per line. These are the central/landing domains.</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tenancy.auto_create_tenant_database"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Auto-create tenant databases</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="settings.tenancy.tenant_signup_enabled"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Enable tenant signup</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="tenant_database_prefix">Database Prefix</label>
                                    <input
                                        type="text"
                                        id="tenant_database_prefix"
                                        v-model="settings.tenancy.tenant_database_prefix"
                                        class="form-control"
                                    >
                                    <small class="form-text">Prefix for tenant database names</small>
                                </div>

                                <div class="form-group">
                                    <label for="max_tenants_per_user">Max Tenants per User</label>
                                    <input
                                        type="number"
                                        id="max_tenants_per_user"
                                        v-model="settings.tenancy.max_tenants_per_user"
                                        min="1"
                                        max="100"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.tenancy.tenant_approval_required"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Require approval for new tenants</span>
                                </label>
                                <small class="form-text">When enabled, new tenants must be approved by an admin</small>
                            </div>

                            <div class="form-group">
                                <label for="default_tenant_plan">Default Tenant Plan</label>
                                <select id="default_tenant_plan" v-model="settings.tenancy.default_tenant_plan" class="form-control">
                                    <option value="basic">Basic</option>
                                    <option value="premium">Premium</option>
                                    <option value="enterprise">Enterprise</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Settings -->
                    <div v-else-if="activeCategory === 'billing'" class="settings-section">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Billing & Payments
                        </h3>

                        <div class="settings-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select id="currency" v-model="settings.billing.currency" class="form-control">
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="GBP">GBP - British Pound</option>
                                        <option value="JPY">JPY - Japanese Yen</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="tax_rate">Tax Rate (%)</label>
                                    <input
                                        type="number"
                                        id="tax_rate"
                                        v-model="settings.billing.tax_rate"
                                        min="0"
                                        max="50"
                                        step="0.1"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.billing.tax_inclusive"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Prices include tax</span>
                                </label>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="trial_days">Trial Days</label>
                                    <input
                                        type="number"
                                        id="trial_days"
                                        v-model="settings.billing.trial_days"
                                        min="0"
                                        max="365"
                                        class="form-control"
                                    >
                                    <small class="form-text">Free trial period for new tenants</small>
                                </div>

                                <div class="form-group">
                                    <label for="grace_period_days">Grace Period Days</label>
                                    <input
                                        type="number"
                                        id="grace_period_days"
                                        v-model="settings.billing.grace_period_days"
                                        min="0"
                                        max="30"
                                        class="form-control"
                                    >
                                    <small class="form-text">Days before suspending for non-payment</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.billing.auto_suspend_after_grace"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Auto-suspend after grace period</span>
                                </label>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="payment_gateway">Payment Gateway</label>
                                    <select id="payment_gateway" v-model="settings.billing.payment_gateway" class="form-control">
                                        <option value="stripe">Stripe</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="manual">Manual</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="invoice_prefix">Invoice Prefix</label>
                                    <input
                                        type="text"
                                        id="invoice_prefix"
                                        v-model="settings.billing.invoice_prefix"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <!-- Stripe Settings -->
                            <div v-if="settings.billing.payment_gateway === 'stripe'" class="gateway-settings">
                                <h4 class="gateway-title">Stripe Settings</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="stripe_public_key">Public Key</label>
                                        <input
                                            type="text"
                                            id="stripe_public_key"
                                            v-model="settings.billing.stripe_public_key"
                                            class="form-control"
                                            placeholder="pk_live_..."
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="stripe_secret_key">Secret Key</label>
                                        <input
                                            type="password"
                                            id="stripe_secret_key"
                                            v-model="settings.billing.stripe_secret_key"
                                            class="form-control"
                                            placeholder="sk_live_..."
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- PayPal Settings -->
                            <div v-else-if="settings.billing.payment_gateway === 'paypal'" class="gateway-settings">
                                <h4 class="gateway-title">PayPal Settings</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="paypal_client_id">Client ID</label>
                                        <input
                                            type="text"
                                            id="paypal_client_id"
                                            v-model="settings.billing.paypal_client_id"
                                            class="form-control"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="paypal_secret">Secret</label>
                                        <input
                                            type="password"
                                            id="paypal_secret"
                                            v-model="settings.billing.paypal_secret"
                                            class="form-control"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.billing.send_invoice_emails"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Send invoice emails</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.billing.send_payment_receipts"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Send payment receipts</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div v-else-if="activeCategory === 'security'" class="settings-section">
                        <h3 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Security Settings
                        </h3>

                        <div class="settings-form">
                            <h4 class="subsection-title">Password Policy</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password_min_length">Minimum Length</label>
                                    <input
                                        type="number"
                                        id="password_min_length"
                                        v-model="settings.security.password_min_length"
                                        min="6"
                                        max="32"
                                        class="form-control"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="password_expiry_days">Expiry Days</label>
                                    <input
                                        type="number"
                                        id="password_expiry_days"
                                        v-model="settings.security.password_expiry_days"
                                        min="0"
                                        max="365"
                                        class="form-control"
                                    >
                                    <small class="form-text">0 = never expires</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="passwordRequirements.numbers"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Require numbers</span>
                                    </label>

                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="passwordRequirements.symbols"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Require symbols</span>
                                    </label>

                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="passwordRequirements.mixedCase"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Require mixed case</span>
                                    </label>
                                </div>
                            </div>

                            <h4 class="subsection-title">Session & Login</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_sessions_per_user">Max Sessions per User</label>
                                    <input
                                        type="number"
                                        id="max_sessions_per_user"
                                        v-model="settings.security.max_sessions_per_user"
                                        min="1"
                                        max="20"
                                        class="form-control"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="max_requests_per_minute">Max Requests per Minute</label>
                                    <input
                                        type="number"
                                        id="max_requests_per_minute"
                                        v-model="settings.security.max_requests_per_minute"
                                        min="10"
                                        max="1000"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.security.two_factor_enabled"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Enable Two-Factor Authentication</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.security.enable_login_alerts"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Enable login alerts</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input
                                        type="checkbox"
                                        v-model="settings.security.rate_limiting_enabled"
                                        class="checkbox-input"
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Enable rate limiting</span>
                                </label>
                            </div>

                            <h4 class="subsection-title">IP Restrictions</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ip_whitelist">IP Whitelist</label>
                                    <textarea
                                        id="ip_whitelist"
                                        v-model="ipWhitelistText"
                                        class="form-control"
                                        rows="3"
                                        placeholder="192.168.1.1&#10;10.0.0.0/24"
                                    ></textarea>
                                    <small class="form-text">One IP or CIDR per line. Leave empty to allow all.</small>
                                </div>

                                <div class="form-group">
                                    <label for="ip_blacklist">IP Blacklist</label>
                                    <textarea
                                        id="ip_blacklist"
                                        v-model="ipBlacklistText"
                                        class="form-control"
                                        rows="3"
                                        placeholder="123.456.789.0"
                                    ></textarea>
                                    <small class="form-text">One IP or CIDR per line.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div v-else-if="activeCategory === 'email'" class="settings-section">
                        <h3 class="section-title">
                            <i class="fas fa-envelope"></i>
                            Email Settings
                        </h3>

                        <div class="settings-form">
                            <div class="form-group">
                                <label for="mail_driver">Mail Driver</label>
                                <select id="mail_driver" v-model="settings.email.mail_driver" class="form-control">
                                    <option value="smtp">SMTP</option>
                                    <option value="mailgun">Mailgun</option>
                                    <option value="ses">Amazon SES</option>
                                    <option value="postmark">Postmark</option>
                                </select>
                            </div>

                            <!-- SMTP Settings -->
                            <div v-if="settings.email.mail_driver === 'smtp'" class="driver-settings">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="mail_host">SMTP Host</label>
                                        <input
                                            type="text"
                                            id="mail_host"
                                            v-model="settings.email.mail_host"
                                            class="form-control"
                                            placeholder="smtp.gmail.com"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="mail_port">SMTP Port</label>
                                        <input
                                            type="number"
                                            id="mail_port"
                                            v-model="settings.email.mail_port"
                                            class="form-control"
                                            placeholder="587"
                                        >
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="mail_username">SMTP Username</label>
                                        <input
                                            type="text"
                                            id="mail_username"
                                            v-model="settings.email.mail_username"
                                            class="form-control"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="mail_password">SMTP Password</label>
                                        <input
                                            type="password"
                                            id="mail_password"
                                            v-model="settings.email.mail_password"
                                            class="form-control"
                                            placeholder="Leave empty to keep current"
                                        >
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="mail_encryption">Encryption</label>
                                    <select id="mail_encryption" v-model="settings.email.mail_encryption" class="form-control">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Mailgun Settings -->
                            <div v-else-if="settings.email.mail_driver === 'mailgun'" class="driver-settings">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="mailgun_domain">Mailgun Domain</label>
                                        <input
                                            type="text"
                                            id="mailgun_domain"
                                            v-model="settings.email.mailgun_domain"
                                            class="form-control"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="mailgun_secret">Mailgun Secret</label>
                                        <input
                                            type="password"
                                            id="mailgun_secret"
                                            v-model="settings.email.mailgun_secret"
                                            class="form-control"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Amazon SES Settings -->
                            <div v-else-if="settings.email.mail_driver === 'ses'" class="driver-settings">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ses_key">SES Key</label>
                                        <input
                                            type="text"
                                            id="ses_key"
                                            v-model="settings.email.ses_key"
                                            class="form-control"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="ses_secret">SES Secret</label>
                                        <input
                                            type="password"
                                            id="ses_secret"
                                            v-model="settings.email.ses_secret"
                                            class="form-control"
                                        >
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="ses_region">SES Region</label>
                                    <input
                                        type="text"
                                        id="ses_region"
                                        v-model="settings.email.ses_region"
                                        class="form-control"
                                        placeholder="us-east-1"
                                    >
                                </div>
                            </div>

                            <!-- Postmark Settings -->
                            <div v-else-if="settings.email.mail_driver === 'postmark'" class="driver-settings">
                                <div class="form-group">
                                    <label for="postmark_token">Postmark Token</label>
                                    <input
                                        type="password"
                                        id="postmark_token"
                                        v-model="settings.email.postmark_token"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="mail_from_address">From Address</label>
                                    <input
                                        type="email"
                                        id="mail_from_address"
                                        v-model="settings.email.mail_from_address"
                                        class="form-control"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="mail_from_name">From Name</label>
                                    <input
                                        type="text"
                                        id="mail_from_name"
                                        v-model="settings.email.mail_from_name"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <h4 class="subsection-title">Email Preferences</h4>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="settings.email.send_welcome_emails"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Send welcome emails</span>
                                    </label>

                                    <label class="checkbox-label">
                                        <input
                                            type="checkbox"
                                            v-model="settings.email.send_notification_emails"
                                            class="checkbox-input"
                                        >
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text">Send notification emails</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="test_email_address">Test Email Address</label>
                                <input
                                    type="email"
                                    id="test_email_address"
                                    v-model="settings.email.test_email_address"
                                    class="form-control"
                                    placeholder="test@example.com"
                                >
                                <small class="form-text">Used for testing email configuration</small>
                            </div>

                            <button type="button" class="btn btn-outline" @click="testEmail">
                                <i class="fas fa-paper-plane"></i> Send Test Email
                            </button>
                        </div>
                    </div>

                    <!-- More categories can be added similarly -->

                    <div v-else class="category-placeholder">
                        <div class="placeholder-icon">
                            <i class="fas fa-cog fa-spin"></i>
                        </div>
                        <h3>Settings for {{ activeCategory }} will be available soon</h3>
                        <p>This section is under development.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'

export default {
    name: 'SettingsDashboard',

    data() {
        return {
            activeCategory: 'general',
            categories: [
                { id: 'general', name: 'General', icon: 'fas fa-cog' },
                { id: 'tenancy', name: 'Tenancy', icon: 'fas fa-database' },
                { id: 'billing', name: 'Billing', icon: 'fas fa-credit-card' },
                { id: 'security', name: 'Security', icon: 'fas fa-shield-alt' },
                { id: 'email', name: 'Email', icon: 'fas fa-envelope' },
                { id: 'storage', name: 'Storage', icon: 'fas fa-hdd' },
                { id: 'api', name: 'API', icon: 'fas fa-code' },
                { id: 'maintenance', name: 'Maintenance', icon: 'fas fa-tools' },
                { id: 'notifications', name: 'Notifications', icon: 'fas fa-bell' },
                { id: 'appearance', name: 'Appearance', icon: 'fas fa-palette' }
            ],
            settings: {},
            originalSettings: {},
            saving: false,
            loading: true,
            passwordRequirements: {
                numbers: true,
                symbols: true,
                mixedCase: true
            },
            timezones: [
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Europe/London',
                'Europe/Paris',
                'Asia/Tokyo',
                'Australia/Sydney'
            ],
            notifications: [],
            requiresRestart: false,
            changeCount: 0
        }
    },

    computed: {
        ...mapState(['user']),

        centralDomainsText: {
            get() {
                return this.settings.tenancy?.central_domains?.join('\n') || ''
            },
            set(value) {
                if (!this.settings.tenancy) this.settings.tenancy = {}
                this.settings.tenancy.central_domains = value.split('\n').filter(d => d.trim())
            }
        },

        ipWhitelistText: {
            get() {
                return this.settings.security?.ip_whitelist?.join('\n') || ''
            },
            set(value) {
                if (!this.settings.security) this.settings.security = {}
                this.settings.security.ip_whitelist = value.split('\n').filter(ip => ip.trim())
            }
        },

        ipBlacklistText: {
            get() {
                return this.settings.security?.ip_blacklist?.join('\n') || ''
            },
            set(value) {
                if (!this.settings.security) this.settings.security = {}
                this.settings.security.ip_blacklist = value.split('\n').filter(ip => ip.trim())
            }
        },

        lastModified() {
            // Return formatted last modified date
            return 'Today, 14:30'
        }
    },

    created() {
        this.loadSettings()
    },

    watch: {
        settings: {
            deep: true,
            handler(newVal, oldVal) {
                this.calculateChanges()
            }
        }
    },

    methods: {
        ...mapActions(['fetchSettings', 'updateSettings', 'testEmailConfiguration']),

        async loadSettings() {
            this.loading = true
            try {
                const response = await this.fetchSettings()
                this.settings = JSON.parse(JSON.stringify(response.data.settings))
                this.originalSettings = JSON.parse(JSON.stringify(response.data.settings))

                // Initialize password requirements from metadata
                if (this.settings.security) {
                    this.passwordRequirements.numbers = this.settings.security.password_require_numbers !== false
                    this.passwordRequirements.symbols = this.settings.security.password_require_symbols !== false
                    this.passwordRequirements.mixedCase = this.settings.security.password_require_mixed_case !== false
                }
            } catch (error) {
                this.$toast.error('Failed to load settings')
            } finally {
                this.loading = false
            }
        },

        calculateChanges() {
            let changes = 0

            // Compare settings with original
            const compare = (obj1, obj2, path = '') => {
                for (const key in obj1) {
                    const currentPath = path ? `${path}.${key}` : key

                    if (typeof obj1[key] === 'object' && obj1[key] !== null && typeof obj2[key] === 'object' && obj2[key] !== null) {
                        changes += compare(obj1[key], obj2[key], currentPath)
                    } else if (JSON.stringify(obj1[key]) !== JSON.stringify(obj2[key])) {
                        changes++
                    }
                }
                return changes
            }

            this.changeCount = compare(this.settings, this.originalSettings)
        },

        async saveSettings() {
            this.saving = true

            try {
                // Update password requirements in settings
                if (this.settings.security) {
                    this.settings.security.password_require_numbers = this.passwordRequirements.numbers
                    this.settings.security.password_require_symbols = this.passwordRequirements.symbols
                    this.settings.security.password_require_mixed_case = this.passwordRequirements.mixedCase
                }

                const response = await this.updateSettings(this.settings)

                // Update original settings
                this.originalSettings = JSON.parse(JSON.stringify(this.settings))
                this.changeCount = 0

                // Handle response notifications
                this.notifications = response.data.notifications || []
                this.requiresRestart = response.data.requires_restart || false

                this.$toast.success('Settings saved successfully')
            } catch (error) {
                if (error.response?.status === 422) {
                    this.$toast.error('Please fix the validation errors')
                } else {
                    this.$toast.error(error.response?.data?.message || 'Failed to save settings')
                }
            } finally {
                this.saving = false
            }
        },

        resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                this.settings = JSON.parse(JSON.stringify(this.originalSettings))
                this.notifications = []
                this.requiresRestart = false
                this.changeCount = 0
                this.$toast.info('Settings reset to original values')
            }
        },

        async testEmail() {
            if (!this.settings.email.test_email_address) {
                this.$toast.error('Please enter a test email address')
                return
            }

            try {
                await this.testEmailConfiguration(this.settings.email.test_email_address)
                this.$toast.success('Test email sent successfully')
            } catch (error) {
                this.$toast.error('Failed to send test email')
            }
        },

        restartSystem() {
            if (confirm('Are you sure you want to restart the system? This may cause temporary downtime.')) {
                // Call restart API
                this.$toast.info('System restart initiated')
            }
        }
    }
}
</script>

<style scoped>
.settings-dashboard {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 8px;
    margin-bottom: 24px;
    border: 1px solid #fbbf24;
}

.alert i {
    font-size: 1.25rem;
}

.btn-alert {
    margin-left: auto;
    padding: 6px 12px;
    background: #f59e0b;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.2s ease;
}

.btn-alert:hover {
    background: #d97706;
}

.notifications-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
}

.notification {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 0.875rem;
}

.notification.info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.notification.warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.notification.danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.settings-container {
    display: flex;
    gap: 24px;
    min-height: 600px;
}

.settings-sidebar {
    width: 280px;
    display: flex;
    flex-direction: column;
}

.settings-nav {
    background: #f8fafc;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.nav-button {
    width: 100%;
    padding: 16px 20px;
    background: none;
    border: none;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nav-button:hover {
    background: #f1f5f9;
    color: #475569;
}

.nav-button.active {
    background: #3b82f6;
    color: white;
    border-left: 4px solid white;
}

.nav-button i {
    width: 20px;
    font-size: 1.125rem;
}

.nav-button:last-child {
    border-bottom: none;
}

.sidebar-footer {
    margin-top: auto;
    padding-top: 24px;
}

.settings-info {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    border: 1px solid #e2e8f0;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-label {
    color: #64748b;
    font-size: 0.875rem;
}

.info-value {
    color: #1e293b;
    font-weight: 500;
    font-size: 0.875rem;
}

.settings-content {
    flex: 1;
    background: #f8fafc;
    border-radius: 8px;
    padding: 32px;
    border: 1px solid #e2e8f0;
    overflow-y: auto;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    color: #64748b;
}

.loading-state p {
    margin-top: 16px;
}

.category-content {
    max-width: 800px;
}

.settings-section {
    margin-bottom: 40px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 24px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.settings-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #475569;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-text {
    display: block;
    margin-top: 4px;
    font-size: 0.875rem;
    color: #6b7280;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
}

.checkbox-input {
    display: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.checkbox-input:checked + .checkbox-custom {
    background: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 10px;
    font-weight: bold;
}

.checkbox-text {
    color: #374151;
    font-size: 0.95rem;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.subsection-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #374151;
    margin: 32px 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.gateway-settings {
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin: 20px 0;
}

.gateway-title {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 16px;
}

.driver-settings {
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
}

.btn-outline {
    padding: 10px 20px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #374151;
}

.category-placeholder {
    text-align: center;
    padding: 80px 20px;
    color: #64748b;
}

.placeholder-icon {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.category-placeholder h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 12px;
}

.category-placeholder p {
    font-size: 1rem;
    color: #94a3b8;
}
</style>
