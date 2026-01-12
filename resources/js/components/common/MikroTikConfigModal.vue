<template>
    <FormModal
        :show="show"
        @update:show="$emit('update:show', $event)"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        :loading="loading"
        :errors="errors"
        :can-submit="isFormValid"
        submit-text="Apply Configuration"
        submit-button-variant="primary"
        @submit="handleSubmit"
        @cancel="handleCancel"
        @closed="resetForm"
        size="xl"
    >
        <div class="mikrotik-config-form">
            <!-- Configuration Type -->
            <div class="form-section">
                <h4 class="section-title">Configuration Type</h4>
                
                <div class="config-type-selector">
                    <label 
                        v-for="type in configTypes" 
                        :key="type.value"
                        class="config-type-option"
                        :class="{ active: form.config_type === type.value }"
                    >
                        <input
                            type="radio"
                            :value="type.value"
                            v-model="form.config_type"
                            class="config-type-radio"
                            @change="handleConfigTypeChange"
                        />
                        <div class="config-type-content">
                            <i :class="type.icon"></i>
                            <div>
                                <strong>{{ type.label }}</strong>
                                <p>{{ type.description }}</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Interface Configuration -->
            <div v-if="form.config_type === 'interface'" class="form-section">
                <h4 class="section-title">Interface Configuration</h4>
                
                <div class="form-group">
                    <label for="interface-name">Interface Name *</label>
                    <select
                        id="interface-name"
                        v-model="form.interface_name"
                        class="form-select"
                        :class="{ 'error': errors.interface_name }"
                        required
                    >
                        <option value="">Select Interface</option>
                        <option 
                            v-for="interface in availableInterfaces" 
                            :key="interface.name" 
                            :value="interface.name"
                        >
                            {{ interface.name }} ({{ interface.type }})
                        </option>
                    </select>
                    <span v-if="errors.interface_name" class="error-message">{{ errors.interface_name[0] }}</span>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="interface-enabled">Status</label>
                        <select
                            id="interface-enabled"
                            v-model="form.interface_enabled"
                            class="form-select"
                        >
                            <option value="true">Enabled</option>
                            <option value="false">Disabled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="interface-mtu">MTU</label>
                        <input
                            id="interface-mtu"
                            v-model.number="form.interface_mtu"
                            type="number"
                            min="68"
                            max="9000"
                            class="form-input"
                            placeholder="1500"
                        />
                    </div>

                    <div class="form-group">
                        <label for="interface-comment">Comment</label>
                        <input
                            id="interface-comment"
                            v-model="form.interface_comment"
                            type="text"
                            class="form-input"
                            placeholder="Interface description"
                        />
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <div v-if="form.config_type === 'user'" class="form-section">
                <h4 class="section-title">User Management</h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user-action">Action *</label>
                        <select
                            id="user-action"
                            v-model="form.user_action"
                            class="form-select"
                            :class="{ 'error': errors.user_action }"
                            required
                        >
                            <option value="">Select Action</option>
                            <option value="add">Add User</option>
                            <option value="modify">Modify User</option>
                            <option value="remove">Remove User</option>
                            <option value="enable">Enable User</option>
                            <option value="disable">Disable User</option>
                        </select>
                        <span v-if="errors.user_action" class="error-message">{{ errors.user_action[0] }}</span>
                    </div>

                    <div v-if="['modify', 'remove', 'enable', 'disable'].includes(form.user_action)" class="form-group">
                        <label for="existing-user">Existing User *</label>
                        <select
                            id="existing-user"
                            v-model="form.existing_username"
                            class="form-select"
                            :class="{ 'error': errors.existing_username }"
                            required
                        >
                            <option value="">Select User</option>
                            <option 
                                v-for="user in availableUsers" 
                                :key="user.username" 
                                :value="user.username"
                            >
                                {{ user.username }} ({{ user.profile }})
                            </option>
                        </select>
                        <span v-if="errors.existing_username" class="error-message">{{ errors.existing_username[0] }}</span>
                    </div>
                </div>

                <div v-if="['add', 'modify'].includes(form.user_action)" class="user-details">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input
                                id="username"
                                v-model="form.username"
                                type="text"
                                class="form-input"
                                :class="{ 'error': errors.username }"
                                :readonly="form.user_action === 'modify'"
                                placeholder="Enter username"
                                required
                            />
                            <span v-if="errors.username" class="error-message">{{ errors.username[0] }}</span>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="form-input"
                                :class="{ 'error': errors.password }"
                                placeholder="Enter password"
                                required
                            />
                            <span v-if="errors.password" class="error-message">{{ errors.password[0] }}</span>
                        </div>

                        <div class="form-group">
                            <label for="profile">Profile *</label>
                            <select
                                id="profile"
                                v-model="form.profile"
                                class="form-select"
                                :class="{ 'error': errors.profile }"
                                required
                            >
                                <option value="">Select Profile</option>
                                <option 
                                    v-for="profile in availableProfiles" 
                                    :key="profile" 
                                    :value="profile"
                                >
                                    {{ profile }}
                                </option>
                            </select>
                            <span v-if="errors.profile" class="error-message">{{ errors.profile[0] }}</span>
                        </div>

                        <div class="form-group">
                            <label for="user-comment">Comment</label>
                            <input
                                id="user-comment"
                                v-model="form.user_comment"
                                type="text"
                                class="form-input"
                                placeholder="User description"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Configuration -->
            <div v-if="form.config_type === 'system'" class="form-section">
                <h4 class="section-title">System Configuration</h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="system-identity">System Identity</label>
                        <input
                            id="system-identity"
                            v-model="form.system_identity"
                            type="text"
                            class="form-input"
                            placeholder="Router name"
                        />
                    </div>

                    <div class="form-group">
                        <label for="system-clock">Time Zone</label>
                        <select
                            id="system-clock"
                            v-model="form.system_timezone"
                            class="form-select"
                        >
                            <option value="">Select Timezone</option>
                            <option value="Africa/Kampala">Africa/Kampala (EAT)</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Firewall Configuration -->
            <div v-if="form.config_type === 'firewall'" class="form-section">
                <h4 class="section-title">Firewall Configuration</h4>
                
                <div class="form-group">
                    <label for="firewall-action">Action *</label>
                    <select
                        id="firewall-action"
                        v-model="form.firewall_action"
                        class="form-select"
                        :class="{ 'error': errors.firewall_action }"
                        required
                    >
                        <option value="">Select Action</option>
                        <option value="add_rule">Add Rule</option>
                        <option value="modify_rule">Modify Rule</option>
                        <option value="remove_rule">Remove Rule</option>
                        <option value="enable_rule">Enable Rule</option>
                        <option value="disable_rule">Disable Rule</option>
                    </select>
                    <span v-if="errors.firewall_action" class="error-message">{{ errors.firewall_action[0] }}</span>
                </div>

                <div v-if="form.firewall_action === 'add_rule'" class="firewall-rule">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="rule-chain">Chain *</label>
                            <select
                                id="rule-chain"
                                v-model="form.rule_chain"
                                class="form-select"
                                required
                            >
                                <option value="">Select Chain</option>
                                <option value="input">Input</option>
                                <option value="forward">Forward</option>
                                <option value="output">Output</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="rule-action">Rule Action *</label>
                            <select
                                id="rule-action"
                                v-model="form.rule_action"
                                class="form-select"
                                required
                            >
                                <option value="">Select Action</option>
                                <option value="accept">Accept</option>
                                <option value="drop">Drop</option>
                                <option value="reject">Reject</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="rule-protocol">Protocol</label>
                            <select
                                id="rule-protocol"
                                v-model="form.rule_protocol"
                                class="form-select"
                            >
                                <option value="">Any</option>
                                <option value="tcp">TCP</option>
                                <option value="udp">UDP</option>
                                <option value="icmp">ICMP</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="rule-src-address">Source Address</label>
                            <input
                                id="rule-src-address"
                                v-model="form.rule_src_address"
                                type="text"
                                class="form-input"
                                placeholder="0.0.0.0/0"
                            />
                        </div>

                        <div class="form-group">
                            <label for="rule-dst-address">Destination Address</label>
                            <input
                                id="rule-dst-address"
                                v-model="form.rule_dst_address"
                                type="text"
                                class="form-input"
                                placeholder="0.0.0.0/0"
                            />
                        </div>

                        <div class="form-group">
                            <label for="rule-dst-port">Destination Port</label>
                            <input
                                id="rule-dst-port"
                                v-model="form.rule_dst_port"
                                type="text"
                                class="form-input"
                                placeholder="80,443,22"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rule-comment">Rule Comment</label>
                        <input
                            id="rule-comment"
                            v-model="form.rule_comment"
                            type="text"
                            class="form-input"
                            placeholder="Rule description"
                        />
                    </div>
                </div>
            </div>

            <!-- Configuration Notes -->
            <div class="form-section">
                <h4 class="section-title">Configuration Notes</h4>
                
                <div class="form-group">
                    <label for="config-notes">Notes</label>
                    <textarea
                        id="config-notes"
                        v-model="form.notes"
                        class="form-textarea"
                        rows="3"
                        placeholder="Additional notes about this configuration change..."
                    ></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.backup_before_change"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Create backup before applying changes</span>
                        </label>
                        
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                v-model="form.test_connectivity"
                                class="checkbox-input"
                            />
                            <span class="checkbox-text">Test connectivity after changes</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuration Preview -->
            <div v-if="configPreview" class="form-section">
                <h4 class="section-title">Configuration Preview</h4>
                
                <div class="config-preview">
                    <pre><code>{{ configPreview }}</code></pre>
                </div>
            </div>

            <!-- Warning Notice -->
            <div class="form-section">
                <div class="warning-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Important:</strong>
                        <p>Configuration changes will be applied immediately to the MikroTik device. Incorrect configurations may cause connectivity issues. Ensure you have alternative access to the device if needed.</p>
                    </div>
                </div>
            </div>
        </div>

        <template #additional-actions>
            <button
                type="button"
                @click="generatePreview"
                class="btn btn-secondary"
                :disabled="loading || !form.config_type"
            >
                <i class="fas fa-eye"></i>
                Preview
            </button>
        </template>
    </FormModal>
</template>

<script>
import FormModal from './FormModal.vue'

export default {
    name: 'MikroTikConfigModal',
    components: { FormModal },
    emits: ['update:show', 'configured'],
    props: {
        show: {
            type: Boolean,
            required: true
        },
        device: {
            type: Object,
            default: null
        },
        availableInterfaces: {
            type: Array,
            default: () => []
        },
        availableUsers: {
            type: Array,
            default: () => []
        },
        availableProfiles: {
            type: Array,
            default: () => ['default', 'read-only', 'write', 'full']
        }
    },
    data() {
        return {
            loading: false,
            errors: {},
            configPreview: '',
            form: {
                config_type: '',
                
                // Interface config
                interface_name: '',
                interface_enabled: 'true',
                interface_mtu: null,
                interface_comment: '',
                
                // User config
                user_action: '',
                existing_username: '',
                username: '',
                password: '',
                profile: '',
                user_comment: '',
                
                // System config
                system_identity: '',
                system_timezone: '',
                
                // Firewall config
                firewall_action: '',
                rule_chain: '',
                rule_action: '',
                rule_protocol: '',
                rule_src_address: '',
                rule_dst_address: '',
                rule_dst_port: '',
                rule_comment: '',
                
                // General
                notes: '',
                backup_before_change: true,
                test_connectivity: true
            },
            configTypes: [
                {
                    value: 'interface',
                    label: 'Interface Configuration',
                    description: 'Configure network interfaces',
                    icon: 'fas fa-network-wired'
                },
                {
                    value: 'user',
                    label: 'User Management',
                    description: 'Add, modify, or remove users',
                    icon: 'fas fa-users'
                },
                {
                    value: 'system',
                    label: 'System Settings',
                    description: 'Configure system parameters',
                    icon: 'fas fa-cog'
                },
                {
                    value: 'firewall',
                    label: 'Firewall Rules',
                    description: 'Manage firewall configuration',
                    icon: 'fas fa-shield-alt'
                }
            ]
        }
    },
    computed: {
        modalTitle() {
            return this.device ? `Configure ${this.device.name}` : 'MikroTik Configuration'
        },

        modalSubtitle() {
            return this.device ? `IP: ${this.device.ip_address} | Location: ${this.device.location?.region}` : ''
        },

        isFormValid() {
            if (!this.form.config_type) return false

            switch (this.form.config_type) {
                case 'interface':
                    return this.form.interface_name
                case 'user':
                    return this.form.user_action && this.validateUserAction()
                case 'system':
                    return true // System config is optional
                case 'firewall':
                    return this.form.firewall_action && this.validateFirewallAction()
                default:
                    return false
            }
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.resetForm()
                this.loadDeviceData()
            }
        }
    },
    methods: {
        resetForm() {
            this.form = {
                config_type: '',
                interface_name: '',
                interface_enabled: 'true',
                interface_mtu: null,
                interface_comment: '',
                user_action: '',
                existing_username: '',
                username: '',
                password: '',
                profile: '',
                user_comment: '',
                system_identity: '',
                system_timezone: '',
                firewall_action: '',
                rule_chain: '',
                rule_action: '',
                rule_protocol: '',
                rule_src_address: '',
                rule_dst_address: '',
                rule_dst_port: '',
                rule_comment: '',
                notes: '',
                backup_before_change: true,
                test_connectivity: true
            }
            this.errors = {}
            this.configPreview = ''
        },

        async loadDeviceData() {
            if (!this.device) return

            try {
                // Load current device configuration if needed
                const response = await this.$http.get(`/api/mikrotik/${this.device.id}/config`)
                
                if (response.data.system?.identity) {
                    this.form.system_identity = response.data.system.identity
                }
            } catch (error) {
                console.error('Error loading device data:', error)
            }
        },

        handleConfigTypeChange() {
            // Reset type-specific fields when config type changes
            this.configPreview = ''
        },

        validateUserAction() {
            if (['add'].includes(this.form.user_action)) {
                return this.form.username && this.form.password && this.form.profile
            }
            if (['modify', 'remove', 'enable', 'disable'].includes(this.form.user_action)) {
                return this.form.existing_username
            }
            return false
        },

        validateFirewallAction() {
            if (this.form.firewall_action === 'add_rule') {
                return this.form.rule_chain && this.form.rule_action
            }
            return true
        },

        generatePreview() {
            let preview = ''

            switch (this.form.config_type) {
                case 'interface':
                    preview = this.generateInterfacePreview()
                    break
                case 'user':
                    preview = this.generateUserPreview()
                    break
                case 'system':
                    preview = this.generateSystemPreview()
                    break
                case 'firewall':
                    preview = this.generateFirewallPreview()
                    break
            }

            this.configPreview = preview
        },

        generateInterfacePreview() {
            let commands = []
            
            if (this.form.interface_name) {
                let cmd = `/interface set [find name="${this.form.interface_name}"]`
                
                if (this.form.interface_enabled !== null) {
                    cmd += ` disabled=${this.form.interface_enabled === 'false' ? 'yes' : 'no'}`
                }
                
                if (this.form.interface_mtu) {
                    cmd += ` mtu=${this.form.interface_mtu}`
                }
                
                if (this.form.interface_comment) {
                    cmd += ` comment="${this.form.interface_comment}"`
                }
                
                commands.push(cmd)
            }
            
            return commands.join('\n')
        },

        generateUserPreview() {
            let commands = []
            
            switch (this.form.user_action) {
                case 'add':
                    let addCmd = `/ppp secret add name="${this.form.username}" password="${this.form.password}" profile="${this.form.profile}"`
                    if (this.form.user_comment) {
                        addCmd += ` comment="${this.form.user_comment}"`
                    }
                    commands.push(addCmd)
                    break
                    
                case 'modify':
                    let modCmd = `/ppp secret set [find name="${this.form.existing_username}"]`
                    if (this.form.password) modCmd += ` password="${this.form.password}"`
                    if (this.form.profile) modCmd += ` profile="${this.form.profile}"`
                    if (this.form.user_comment) modCmd += ` comment="${this.form.user_comment}"`
                    commands.push(modCmd)
                    break
                    
                case 'remove':
                    commands.push(`/ppp secret remove [find name="${this.form.existing_username}"]`)
                    break
                    
                case 'enable':
                    commands.push(`/ppp secret enable [find name="${this.form.existing_username}"]`)
                    break
                    
                case 'disable':
                    commands.push(`/ppp secret disable [find name="${this.form.existing_username}"]`)
                    break
            }
            
            return commands.join('\n')
        },

        generateSystemPreview() {
            let commands = []
            
            if (this.form.system_identity) {
                commands.push(`/system identity set name="${this.form.system_identity}"`)
            }
            
            if (this.form.system_timezone) {
                commands.push(`/system clock set time-zone-name="${this.form.system_timezone}"`)
            }
            
            return commands.join('\n')
        },

        generateFirewallPreview() {
            let commands = []
            
            if (this.form.firewall_action === 'add_rule') {
                let cmd = `/ip firewall filter add chain="${this.form.rule_chain}" action="${this.form.rule_action}"`
                
                if (this.form.rule_protocol) cmd += ` protocol="${this.form.rule_protocol}"`
                if (this.form.rule_src_address) cmd += ` src-address="${this.form.rule_src_address}"`
                if (this.form.rule_dst_address) cmd += ` dst-address="${this.form.rule_dst_address}"`
                if (this.form.rule_dst_port) cmd += ` dst-port="${this.form.rule_dst_port}"`
                if (this.form.rule_comment) cmd += ` comment="${this.form.rule_comment}"`
                
                commands.push(cmd)
            }
            
            return commands.join('\n')
        },

        async handleSubmit() {
            this.loading = true
            this.errors = {}

            try {
                const response = await this.$http.post(`/api/mikrotik/${this.device.id}/configure`, this.form)
                
                this.$emit('configured', response.data.data)
                this.$emit('update:show', false)
                
                this.$toast.success('Configuration applied successfully')
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                } else {
                    this.$toast.error('Failed to apply configuration')
                }
            } finally {
                this.loading = false
            }
        },

        handleCancel() {
            this.$emit('update:show', false)
        }
    }
}
</script>

<style scoped>
.mikrotik-config-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-section {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}

.section-title {
    margin: 0 0 16px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 8px;
}

.config-type-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.config-type-option {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: block;
}

.config-type-option:hover {
    border-color: #d1d5db;
    background: #f9fafb;
}

.config-type-option.active {
    border-color: #3b82f6;
    background: #eff6ff;
}

.config-type-radio {
    display: none;
}

.config-type-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.config-type-content i {
    font-size: 1.5rem;
    color: #6b7280;
    margin-top: 4px;
}

.config-type-option.active .config-type-content i {
    color: #3b82f6;
}

.config-type-content strong {
    color: #374151;
    font-size: 1rem;
    margin-bottom: 4px;
    display: block;
}

.config-type-content p {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0;
    line-height: 1.4;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.95rem;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input.error,
.form-select.error,
.form-textarea.error {
    border-color: #ef4444;
}

.form-input:read-only {
    background: #f9fafb;
    color: #6b7280;
}

.error-message {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
}

.user-details,
.firewall-rule {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-input {
    width: 16px;
    height: 16px;
    accent-color: #3b82f6;
}

.checkbox-text {
    font-size: 0.95rem;
    color: #374151;
}

.config-preview {
    background: #1f2937;
    border-radius: 6px;
    padding: 16px;
    overflow-x: auto;
}

.config-preview pre {
    margin: 0;
    color: #f9fafb;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
}

.config-preview code {
    color: #10b981;
}

.warning-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 16px;
}

.warning-notice i {
    color: #d97706;
    font-size: 1.25rem;
    margin-top: 2px;
}

.warning-notice strong {
    color: #92400e;
    font-weight: 600;
}

.warning-notice p {
    margin: 4px 0 0 0;
    color: #92400e;
    font-size: 0.95rem;
    line-height: 1.4;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #4b5563;
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .form-section {
        border-color: #374151;
        background: #1f2937;
    }

    .section-title {
        color: #f9fafb;
        border-color: #374151;
    }

    .config-type-option {
        border-color: #4b5563;
        background: #374151;
    }

    .config-type-option:hover {
        border-color: #6b7280;
        background: #4b5563;
    }

    .config-type-option.active {
        border-color: #3b82f6;
        background: #1e3a8a;
    }

    .config-type-content strong {
        color: #f9fafb;
    }

    .config-type-content p {
        color: #d1d5db;
    }

    .form-group label,
    .checkbox-text {
        color: #f3f4f6;
    }

    .form-input,
    .form-select,
    .form-textarea {
        background: #374151;
        border-color: #4b5563;
        color: #f9fafb;
    }

    .form-input:read-only {
        background: #4b5563;
        color: #9ca3af;
    }

    .user-details,
    .firewall-rule {
        border-color: #4b5563;
    }

    .warning-notice {
        background: #451a03;
        border-color: #92400e;
    }

    .warning-notice i {
        color: #fbbf24;
    }

    .warning-notice strong,
    .warning-notice p {
        color: #fbbf24;
    }
}
</style>