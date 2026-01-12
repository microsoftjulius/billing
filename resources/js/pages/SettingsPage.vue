<template>
  <div class="settings-page">
    <div class="page-header">
      <h1>Settings</h1>
      <p>Manage your application settings and preferences</p>
    </div>

    <div class="settings-content">
      <div class="settings-section">
        <h2>General Settings</h2>
        <p>Configure general application settings here.</p>
        
        <div class="setting-item">
          <label>Application Name</label>
          <input type="text" value="BillingSystem" class="form-input" />
        </div>
        
        <div class="setting-item">
          <label>Default Language</label>
          <select class="form-select">
            <option value="en">English</option>
            <option value="es">Spanish</option>
            <option value="fr">French</option>
          </select>
        </div>
      </div>

      <div class="settings-section">
        <h2>Notification Settings</h2>
        <p>Configure how you receive notifications.</p>
        
        <div class="setting-item">
          <label class="checkbox-label">
            <input type="checkbox" checked />
            <span>Email notifications</span>
          </label>
        </div>
        
        <div class="setting-item">
          <label class="checkbox-label">
            <input type="checkbox" checked />
            <span>SMS notifications</span>
          </label>
        </div>
      </div>

      <div class="settings-section">
        <h2>Security Settings</h2>
        <p>Manage your account security preferences.</p>
        
        <div class="setting-item">
          <label class="checkbox-label">
            <input type="checkbox" />
            <span>Two-factor authentication</span>
          </label>
        </div>
        
        <div class="setting-item">
          <button class="btn btn-primary">Change Password</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Settings page component
</script>

<style scoped>
.settings-page {
  padding: 2rem;
  max-width: 800px;
}

.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.page-header p {
  color: var(--text-secondary);
  font-size: 1rem;
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.settings-section {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.settings-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.settings-section p {
  color: var(--text-secondary);
  margin-bottom: 1.5rem;
}

.setting-item {
  margin-bottom: 1rem;
}

.setting-item:last-child {
  margin-bottom: 0;
}

.setting-item label {
  display: block;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin: 0;
}

.form-input,
.form-select {
  width: 100%;
  max-width: 300px;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--bg-color);
  color: var(--text-primary);
  font-size: 0.875rem;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-hover);
}
</style>