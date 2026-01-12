import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import MikroTikConfiguration from '../components/MikroTikConfiguration.vue'

// Mock API calls
const mockApi = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn()
}

vi.mock('../api/index.ts', () => ({
  default: mockApi
}))

describe('MikroTik API Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('communicates with MikroTik API for device statistics', async () => {
    const mockStatistics = {
      device_id: '1',
      status: 'online',
      statistics: {
        uptime: 86400,
        cpu_usage: 15,
        memory_usage: 45,
        interfaces: [
          { name: 'ether1', rx_bytes: 1024000, tx_bytes: 512000 },
          { name: 'ether2', rx_bytes: 2048000, tx_bytes: 1024000 }
        ]
      }
    }

    mockApi.get.mockResolvedValue({ data: mockStatistics })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.$nextTick()

    // Verify API call for statistics
    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/statistics')

    // Verify statistics are displayed
    expect(wrapper.vm.deviceStatistics).toEqual(mockStatistics.statistics)
  })

  it('handles API connection errors gracefully', async () => {
    const errorResponse = {
      device_id: '1',
      status: 'offline',
      error: 'Connection timeout'
    }

    mockApi.get.mockResolvedValue({ data: errorResponse })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.$nextTick()

    // Verify error handling
    expect(wrapper.vm.connectionError).toBe('Connection timeout')
    expect(wrapper.vm.deviceStatus).toBe('offline')
  })

  it('monitors device status in real-time', async () => {
    const mockMonitorData = {
      device_id: '1',
      status: 'online',
      uptime: 86400,
      last_seen: '2024-01-12T10:00:00Z',
      real_time_data: {
        active_connections: 25,
        bandwidth_usage: {
          download: 1500000,
          upload: 750000
        }
      }
    }

    mockApi.get.mockResolvedValue({ data: mockMonitorData })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // Simulate real-time monitoring
    await wrapper.vm.startMonitoring()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/monitor')
    expect(wrapper.vm.realTimeData).toEqual(mockMonitorData.real_time_data)
  })

  it('tests connectivity to MikroTik devices', async () => {
    const connectivityResponse = {
      success: true,
      message: 'Connection successful',
      connection_time: 150,
      device_info: {
        model: 'RB750Gr3',
        version: '7.1.5',
        identity: 'MikroTik'
      }
    }

    mockApi.post.mockResolvedValue({ data: connectivityResponse })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.testConnectivity()

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/router/1/test-connectivity')
    expect(wrapper.vm.connectivityResult).toEqual(connectivityResponse)
  })

  it('manages device interfaces through API', async () => {
    const mockInterfaces = {
      interfaces: [
        { name: 'ether1', status: 'enabled', type: 'ethernet' },
        { name: 'ether2', status: 'disabled', type: 'ethernet' },
        { name: 'wlan1', status: 'enabled', type: 'wireless' }
      ]
    }

    mockApi.get.mockResolvedValue({ data: mockInterfaces })
    mockApi.put.mockResolvedValue({ data: { success: true } })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // Load interfaces
    await wrapper.vm.loadInterfaces()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/interfaces')
    expect(wrapper.vm.interfaces).toEqual(mockInterfaces.interfaces)

    // Update interface
    await wrapper.vm.updateInterface('ether1', { status: 'disabled' })

    expect(mockApi.put).toHaveBeenCalledWith('/api/v1/router/1/interfaces/ether1', {
      status: 'disabled'
    })
  })

  it('manages device users through API', async () => {
    const mockUsers = {
      users: [
        { name: 'admin', profile: 'full', active: true },
        { name: 'user1', profile: 'default', active: true },
        { name: 'guest', profile: 'guest', active: false }
      ]
    }

    mockApi.get.mockResolvedValue({ data: mockUsers })
    mockApi.post.mockResolvedValue({ data: { success: true, user_id: 'new-user-id' } })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // Load users
    await wrapper.vm.loadUsers()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/users')
    expect(wrapper.vm.users).toEqual(mockUsers.users)

    // Add new user
    const newUser = {
      name: 'testuser',
      password: 'password123',
      profile: 'default'
    }

    await wrapper.vm.addUser(newUser)

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/router/1/users', newUser)
  })

  it('retrieves and displays device logs', async () => {
    const mockLogs = {
      logs: [
        {
          time: '2024-01-12T10:00:00Z',
          topics: 'system,info',
          message: 'System started'
        },
        {
          time: '2024-01-12T10:01:00Z',
          topics: 'interface,info',
          message: 'Interface ether1 link up'
        }
      ]
    }

    mockApi.get.mockResolvedValue({ data: mockLogs })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.loadLogs()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/logs')
    expect(wrapper.vm.deviceLogs).toEqual(mockLogs.logs)
  })

  it('creates and manages device backups', async () => {
    const mockBackups = {
      backups: [
        {
          id: 'backup-1',
          name: 'daily-backup-2024-01-12',
          created_at: '2024-01-12T10:00:00Z',
          size: 1024000
        }
      ]
    }

    const createBackupResponse = {
      backup_id: 'backup-2',
      message: 'Backup created successfully'
    }

    mockApi.get.mockResolvedValue({ data: mockBackups })
    mockApi.post.mockResolvedValue({ data: createBackupResponse })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // Load existing backups
    await wrapper.vm.loadBackups()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/router/1/backups')
    expect(wrapper.vm.backups).toEqual(mockBackups.backups)

    // Create new backup
    await wrapper.vm.createBackup('test-backup')

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/router/1/backup', {
      name: 'test-backup'
    })
  })

  it('handles API rate limiting gracefully', async () => {
    // Mock rate limiting response
    mockApi.get
      .mockResolvedValueOnce({ data: { status: 'online' } })
      .mockResolvedValueOnce({ data: { status: 'online' } })
      .mockRejectedValueOnce({ response: { status: 429, data: { message: 'Rate limit exceeded' } } })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // Make multiple requests
    await wrapper.vm.loadStatistics()
    await wrapper.vm.loadStatistics()
    
    try {
      await wrapper.vm.loadStatistics()
    } catch (error) {
      expect(error.response.status).toBe(429)
      expect(wrapper.vm.rateLimitError).toBe('Rate limit exceeded')
    }
  })

  it('caches API responses for performance optimization', async () => {
    const mockData = { status: 'online', uptime: 86400 }
    
    mockApi.get.mockResolvedValue({ data: mockData })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    // First request
    await wrapper.vm.loadStatistics()
    
    // Second request should use cache
    await wrapper.vm.loadStatistics()

    // API should only be called once due to caching
    expect(mockApi.get).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.cachedData).toEqual(mockData)
  })

  it('clears cache when requested', async () => {
    mockApi.delete.mockResolvedValue({ data: { message: 'Cache cleared successfully' } })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.clearCache()

    expect(mockApi.delete).toHaveBeenCalledWith('/api/v1/router/1/cache')
    expect(wrapper.vm.cacheCleared).toBe(true)
  })

  it('handles configuration changes through API interface', async () => {
    const configUpdate = {
      system_name: 'Updated Router',
      interfaces: ['ether1', 'ether2', 'ether3']
    }

    mockApi.put.mockResolvedValue({ data: { success: true } })

    const wrapper = mount(MikroTikConfiguration, {
      props: { deviceId: '1' }
    })

    await wrapper.vm.updateConfiguration(configUpdate)

    expect(mockApi.put).toHaveBeenCalledWith('/api/v1/router-management/1', {
      configuration: configUpdate
    })
  })

  it('maintains connection pooling for multiple devices', async () => {
    const devices = ['1', '2', '3']
    
    mockApi.get.mockResolvedValue({ data: { status: 'online' } })

    // Simulate multiple device connections
    const promises = devices.map(deviceId => {
      const wrapper = mount(MikroTikConfiguration, {
        props: { deviceId }
      })
      return wrapper.vm.loadStatistics()
    })

    await Promise.all(promises)

    // Verify all requests were handled
    expect(mockApi.get).toHaveBeenCalledTimes(3)
    devices.forEach(deviceId => {
      expect(mockApi.get).toHaveBeenCalledWith(`/api/v1/router/${deviceId}/statistics`)
    })
  })
})