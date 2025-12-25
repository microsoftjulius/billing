<template>
  <aside class="sidebar">
    <div class="sidebar-header">
      <h3 class="logo">Central Admin</h3>
      <button class="sidebar-toggle" @click="$emit('toggle')">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar">
        <img :src="user.avatar" :alt="user.name" v-if="user.avatar" />
        <span v-else>{{ user.name?.charAt(0) }}</span>
      </div>
      <div class="user-info">
        <strong>{{ user.name }}</strong>
        <small>{{ user.email }}</small>
        <span class="user-role">{{ user.role }}</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li>
          <router-link to="/dashboard" class="nav-link" active-class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </router-link>
        </li>

        <li class="nav-section">
          <span class="section-title">Tenant Management</span>
          <ul>
            <li>
              <router-link to="/tenants" class="nav-link" active-class="active">
                <i class="fas fa-building"></i>
                <span>All Tenants</span>
              </router-link>
            </li>
            <li>
              <router-link to="/tenants/create" class="nav-link" active-class="active">
                <i class="fas fa-plus-circle"></i>
                <span>Create Tenant</span>
              </router-link>
            </li>
            <li>
              <router-link to="/tenants/suspended" class="nav-link" active-class="active">
                <i class="fas fa-ban"></i>
                <span>Suspended Tenants</span>
              </router-link>
            </li>
          </ul>
        </li>

        <li class="nav-section">
          <span class="section-title">User Management</span>
          <ul>
            <li>
              <router-link to="/users" class="nav-link" active-class="active">
                <i class="fas fa-users"></i>
                <span>All Users</span>
              </router-link>
            </li>
            <li>
              <router-link to="/users/create" class="nav-link" active-class="active">
                <i class="fas fa-user-plus"></i>
                <span>Create User</span>
              </router-link>
            </li>
            <li>
              <router-link to="/users/roles" class="nav-link" active-class="active">
                <i class="fas fa-user-tag"></i>
                <span>Roles & Permissions</span>
              </router-link>
            </li>
          </ul>
        </li>

        <li class="nav-section">
          <span class="section-title">Reports & Analytics</span>
          <ul>
            <li>
              <router-link to="/reports/tenants" class="nav-link" active-class="active">
                <i class="fas fa-chart-bar"></i>
                <span>Tenants Report</span>
              </router-link>
            </li>
            <li>
              <router-link to="/reports/revenue" class="nav-link" active-class="active">
                <i class="fas fa-dollar-sign"></i>
                <span>Revenue Report</span>
              </router-link>
            </li>
            <li>
              <router-link to="/reports/usage" class="nav-link" active-class="active">
                <i class="fas fa-chart-line"></i>
                <span>Usage Report</span>
              </router-link>
            </li>
          </ul>
        </li>

        <li class="nav-section">
          <span class="section-title">System</span>
          <ul>
            <li>
              <router-link to="/settings" class="nav-link" active-class="active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
              </router-link>
            </li>
            <li>
              <router-link to="/logs" class="nav-link" active-class="active">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
              </router-link>
            </li>
            <li>
              <router-link to="/system/health" class="nav-link" active-class="active">
                <i class="fas fa-heartbeat"></i>
                <span>System Health</span>
              </router-link>
            </li>
            <li>
              <router-link to="/backups" class="nav-link" active-class="active">
                <i class="fas fa-database"></i>
                <span>Backups</span>
              </router-link>
            </li>
          </ul>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <button class="btn-logout" @click="logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </button>
    </div>
  </aside>
</template>

<script>
import { mapState, mapActions } from 'vuex'

export default {
  name: 'AppSidebar',
  computed: {
    ...mapState(['user'])
  },
  methods: {
    ...mapActions(['logoutUser']),
    async logout() {
      await this.logoutUser()
      this.$router.push('/login')
    }
  }
}
</script>

<style scoped>
.sidebar {
  width: 280px;
  background: #1e293b;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
}

.sidebar-header {
  padding: 20px;
  border-bottom: 1px solid #334155;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.logo {
  margin: 0;
  color: #fff;
  font-size: 1.5rem;
}

.sidebar-user {
  padding: 20px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #334155;
}

.user-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-right: 12px;
  overflow: hidden;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  flex: 1;
}

.user-info strong {
  display: block;
  color: white;
  font-size: 1rem;
}

.user-info small {
  color: #94a3b8;
  font-size: 0.875rem;
}

.user-role {
  display: inline-block;
  background: #3b82f6;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  margin-top: 4px;
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 20px 0;
}

.nav-section {
  margin-bottom: 20px;
}

.section-title {
  display: block;
  padding: 0 20px 10px;
  color: #94a3b8;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #cbd5e1;
  text-decoration: none;
  transition: all 0.2s ease;
}

.nav-link:hover {
  background: rgba(59, 130, 246, 0.1);
  color: white;
}

.nav-link.active {
  background: #3b82f6;
  color: white;
  border-left: 4px solid white;
}

.nav-link i {
  width: 24px;
  margin-right: 12px;
  font-size: 1.125rem;
}

.sidebar-footer {
  padding: 20px;
  border-top: 1px solid #334155;
}

.btn-logout {
  width: 100%;
  padding: 12px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-logout:hover {
  background: #b91c1c;
}

.btn-logout i {
  margin-right: 8px;
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: -280px;
    top: 0;
    bottom: 0;
    z-index: 1000;
  }

  .sidebar.open {
    left: 0;
  }
}
</style>
