import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import RouterManagement from '../components/RouterManagement.vue'
import { useModal } from '../composables/useModal'

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

describe('Router Management Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('integrates router addition modal with database synchronization', async () => {
    const mockRouters = [
      {
        id: '1',
        name: 'Test Router',
        ip_address: '192.168.1.1',
        status: 'online',
        location: { region: 'Central', district: 'Kampala' }
      }
    ]

    mockApi.get.mockResolvedValue({ data: { data: mockRouters } })
    mockApi.post.mockResolvedValue({
      data: {
        data: {
          id: '2',
          name: 'New Router',
          ip_address: '192.168.1.2',
          status: 'offline'
        }
      }
    })

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Verify initial routers are loaded
    expect(mockApi.get).toHaveBeenCalledWith('/api/routers')
    
    // Simulate adding a new router through modal
    const addButton = wrapper.find('[data-testid="add-router-btn"]')
    await addButton.trigger('click')

    // Verify modal is opened
    expect(wrapper.vm.showModal).toBe(true)

    // Fill router form data
    const routerForm = {
      name: 'New Router',
      ip_address: '192.168.1.2',
      api_port: 8728,
      username: 'admin',
      password: 'password123',
      location: {
        region: 'Western',
        district: 'Mbarara'
      }
    }

    // Simulate form submission
    await wrapper.vm.handleSubmit(routerForm)

    // Verify API call was made
    expect(mockApi.post).toHaveBeenCalledWith('/api/routers', routerForm)

    // Verify modal is closed and data is refreshed
    expect(wrapper.vm.showModal).toBe(false)
    expect(mockApi.get).toHaveBeenCalledTimes(2) // Initial load + refresh after add
  })

  it('handles router configuration and monitoring integration', async () => {
    const mockRouter = {
      id: '1',
      name: 'Test Router',
      ip_address: '192.168.1.1',
      status: 'online',
      configuration: {
        interfaces: ['ether1', 'ether2'],
        users: ['admin', 'user1']
      },
      statistics: {
        uptime: 86400,
        cpu_usage: 15,
        memory_usage: 45
      }
    }

    mockApi.get.mockResolvedValueOnce({ data: { data: [mockRouter] } })
    mockApi.get.mockResolvedValueOnce({ data: { data: mockRouter } })

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Simulate clicking on router for configuration
    const configButton = wrapper.find('[data-testid="config-router-1"]')
    await configButton.trigger('click')

    // Verify router details are fetched
    expect(mockApi.get).toHaveBeenCalledWith('/api/routers/1')

    // Verify configuration modal shows router data
    expect(wrapper.vm.selectedRouter).toEqual(mockRouter)
  })

  it('validates router connection during addition process', async () => {
    const connectionTestResponse = {
      success: true,
      message: 'Connection successful',
      connection_time: 150
    }

    mockApi.post.mockResolvedValue({ data: connectionTestResponse })

    const wrapper = mount(RouterManagement)
    
    const routerData = {
      name: 'Test Router',
      ip_address: '192.168.1.1',
      api_port: 8728,
      username: 'admin',
      password: 'password123'
    }

    // Test connection validation
    await wrapper.vm.testConnection(routerData)

    expect(mockApi.post).toHaveBeenCalledWith('/api/routers/test-connection', routerData)
    expect(wrapper.vm.connectionStatus).toEqual(connectionTestResponse)
  })

  it('handles router editing and database updates', async () => {
    const existingRouter = {
      id: '1',
      name: 'Original Router',
      ip_address: '192.168.1.1',
      location: { region: 'Central' }
    }

    const updatedRouter = {
      ...existingRouter,
      name: 'Updated Router',
      ip_address: '192.168.1.2'
    }

    mockApi.get.mockResolvedValue({ data: { data: [existingRouter] } })
    mockApi.put.mockResolvedValue({ data: { data: updatedRouter } })

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Simulate editing router
    await wrapper.vm.editRouter(existingRouter)
    
    // Verify edit modal is opened with router data
    expect(wrapper.vm.showModal).toBe(true)
    expect(wrapper.vm.editingRouter).toEqual(existingRouter)

    // Submit updated data
    await wrapper.vm.handleSubmit(updatedRouter)

    // Verify update API call
    expect(mockApi.put).toHaveBeenCalledWith('/api/routers/1', updatedRouter)
  })

  it('handles router deletion with confirmation', async () => {
    const router = {
      id: '1',
      name: 'Router to Delete',
      ip_address: '192.168.1.1'
    }

    mockApi.get.mockResolvedValue({ data: { data: [router] } })
    mockApi.delete.mockResolvedValue({ data: { message: 'Router deleted successfully' } })

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Simulate delete action
    await wrapper.vm.deleteRouter(router.id)

    // Verify delete API call
    expect(mockApi.delete).toHaveBeenCalledWith('/api/routers/1')

    // Verify data refresh after deletion
    expect(mockApi.get).toHaveBeenCalledTimes(2) // Initial load + refresh after delete
  })

  it('displays router status and configuration in management interface', async () => {
    const mockRouters = [
      {
        id: '1',
        name: 'Router 1',
        ip_address: '192.168.1.1',
        status: 'online',
        location: { region: 'Central', district: 'Kampala' },
        uptime: 86400,
        last_seen: '2024-01-12T10:00:00Z'
      },
      {
        id: '2',
        name: 'Router 2',
        ip_address: '192.168.1.2',
        status: 'offline',
        location: { region: 'Western', district: 'Mbarara' },
        last_seen: '2024-01-11T15:30:00Z'
      }
    ]

    mockApi.get.mockResolvedValue({ data: { data: mockRouters } })

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Verify routers are displayed
    expect(wrapper.findAll('[data-testid^="router-row-"]')).toHaveLength(2)

    // Verify status indicators
    const onlineRouter = wrapper.find('[data-testid="router-row-1"]')
    expect(onlineRouter.find('.status-online')).toBeTruthy()

    const offlineRouter = wrapper.find('[data-testid="router-row-2"]')
    expect(offlineRouter.find('.status-offline')).toBeTruthy()

    // Verify location information is displayed
    expect(onlineRouter.text()).toContain('Central - Kampala')
    expect(offlineRouter.text()).toContain('Western - Mbarara')
  })

  it('synchronizes modal interface with database state', async () => {
    const routerData = {
      name: 'Sync Test Router',
      ip_address: '192.168.1.100',
      api_port: 8728,
      username: 'admin',
      password: 'password123',
      location: {
        region: 'Northern',
        district: 'Gulu'
      }
    }

    const createdRouter = {
      id: '3',
      ...routerData,
      status: 'offline',
      created_at: '2024-01-12T10:00:00Z'
    }

    mockApi.get.mockResolvedValueOnce({ data: { data: [] } }) // Initial empty state
    mockApi.post.mockResolvedValue({ data: { data: createdRouter } })
    mockApi.get.mockResolvedValueOnce({ data: { data: [createdRouter] } }) // After creation

    const wrapper = mount(RouterManagement)
    await wrapper.vm.$nextTick()

    // Add router through modal
    await wrapper.vm.handleSubmit(routerData)

    // Verify synchronization
    expect(mockApi.post).toHaveBeenCalledWith('/api/routers', routerData)
    expect(wrapper.vm.routers).toContainEqual(createdRouter)
  })
})