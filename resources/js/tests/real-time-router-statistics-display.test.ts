import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import MikroTikConfiguration from '@/components/MikroTikConfiguration.vue';
import { useRealtimeStore } from '@/store/modules/realtime';
import { useAppStore } from '@/store/modules/app';
import axios from 'axios';

// Mock axios
vi.mock('axios');
const mockedAxios = vi.mocked(axios);

// Mock router
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/mikrotik-config', name: 'mikrotik-config', component: { template: '<div></div>' } }
  ]
});

describe('Real-time Router Statistics Display', () => {
  let pinia: any;
  let realtimeStore: any;
  let appStore: any;

  beforeEach(() => {
    pinia = createPinia();
    setActivePinia(pinia);
    realtimeStore = useRealtimeStore();
    appStore = useAppStore();
    
    // Mock store methods
    realtimeStore.updateMikroTikDevices = vi.fn();
    appStore.addNotification = vi.fn();
    
    // Reset axios mocks
    vi.clearAllMocks();
  });

  /**
   * Feature: vue-frontend-enhancement, Property 41: Real-time Router Statistics Display
   * 
   * For any router being monitored, real-time statistics should be retrieved and displayed 
   * in the monitoring interface.
   * 
   * Validates: Requirements 15.1
   */
  it('displays real-time router statistics for any monitored device', async () => {
    // Mock devices data
    const mockDevices = [
      {
        id: 'device-1',
        name: 'Router 1',
        ip_address: '192.168.1.1',
        api_port: 8728,
        status: 'online',
        location: { region: 'Central', district: 'Kampala' }
      },
      {
        id: 'device-2', 
        name: 'Router 2',
        ip_address: '192.168.1.2',
        api_port: 8728,
        status: 'online',
        location: { region: 'Eastern', district: 'Jinja' }
      }
    ];

    // Mock statistics data for different devices
    const mockStatistics = {
      'device-1': {
        cpu_usage: 25,
        memory_usage: 45,
        uptime: 86400,
        version: 'RouterOS 7.1',
        interfaces: [
          { name: 'ether1', running: true, rx_bytes: 1024000, tx_bytes: 512000 }
        ],
        active_users: 15,
        active_sessions: 12,
        bandwidth_usage: 1000000
      },
      'device-2': {
        cpu_usage: 35,
        memory_usage: 60,
        uptime: 172800,
        version: 'RouterOS 7.2',
        interfaces: [
          { name: 'ether1', running: true, rx_bytes: 2048000, tx_bytes: 1024000 }
        ],
        active_users: 25,
        active_sessions: 20,
        bandwidth_usage: 2000000
      }
    };

    // Set up store state
    realtimeStore.mikrotikDevices = mockDevices;

    // Mock API responses
    mockedAxios.get.mockImplementation((url: string) => {
      if (url === '/api/v1/router') {
        return Promise.resolve({ data: { data: mockDevices } });
      }
      
      // Extract device ID from statistics URL
      const statisticsMatch = url.match(/\/api\/v1\/router\/(.+)\/statistics/);
      if (statisticsMatch) {
        const deviceId = statisticsMatch[1];
        return Promise.resolve({ 
          data: { 
            data: mockStatistics[deviceId as keyof typeof mockStatistics] || mockStatistics['device-1']
          } 
        });
      }
      
      return Promise.resolve({ data: { data: [] } });
    });

    const wrapper = mount(MikroTikConfiguration, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ConnectionStatus: true,
          Modal: true
        }
      }
    });

    await wrapper.vm.$nextTick();

    // Test for each device
    for (const device of mockDevices) {
      // Select the device
      const deviceSelect = wrapper.find('select');
      await deviceSelect.setValue(device.id);
      await wrapper.vm.$nextTick();

      // Wait for statistics to load
      await new Promise(resolve => setTimeout(resolve, 100));

      // Verify statistics are displayed
      const statisticsTab = wrapper.find('[data-testid="statistics-tab"]');
      if (statisticsTab.exists()) {
        await statisticsTab.trigger('click');
        await wrapper.vm.$nextTick();
      }

      // Check that statistics API was called for this device
      expect(mockedAxios.get).toHaveBeenCalledWith(`/api/v1/router/${device.id}/statistics`);

      // Verify statistics are rendered in the UI
      const expectedStats = mockStatistics[device.id as keyof typeof mockStatistics];
      if (expectedStats) {
        // Check CPU usage display
        const cpuUsage = wrapper.text();
        expect(cpuUsage).toContain(`${expectedStats.cpu_usage}%`);
        
        // Check memory usage display
        expect(cpuUsage).toContain(`${expectedStats.memory_usage}%`);
        
        // Check active users count
        expect(cpuUsage).toContain(expectedStats.active_users.toString());
        
        // Check version information
        expect(cpuUsage).toContain(expectedStats.version);
      }
    }
  });

  it('updates statistics in real-time for any selected device', async () => {
    const mockDevice = {
      id: 'device-test',
      name: 'Test Router',
      ip_address: '192.168.1.100',
      api_port: 8728,
      status: 'online',
      location: { region: 'Test', district: 'Test' }
    };

    // Initial statistics
    const initialStats = {
      cpu_usage: 20,
      memory_usage: 40,
      uptime: 3600,
      version: 'RouterOS 7.0',
      interfaces: [],
      active_users: 10,
      active_sessions: 8,
      bandwidth_usage: 500000
    };

    // Updated statistics (simulating real-time change)
    const updatedStats = {
      cpu_usage: 30,
      memory_usage: 50,
      uptime: 3660,
      version: 'RouterOS 7.0',
      interfaces: [],
      active_users: 12,
      active_sessions: 10,
      bandwidth_usage: 600000
    };

    realtimeStore.mikrotikDevices = [mockDevice];

    let callCount = 0;
    mockedAxios.get.mockImplementation((url: string) => {
      if (url === '/api/v1/router') {
        return Promise.resolve({ data: { data: [mockDevice] } });
      }
      
      if (url.includes('/statistics')) {
        callCount++;
        const stats = callCount === 1 ? initialStats : updatedStats;
        return Promise.resolve({ data: { data: stats } });
      }
      
      return Promise.resolve({ data: { data: [] } });
    });

    const wrapper = mount(MikroTikConfiguration, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ConnectionStatus: true,
          Modal: true
        }
      }
    });

    await wrapper.vm.$nextTick();

    // Select device
    const deviceSelect = wrapper.find('select');
    await deviceSelect.setValue(mockDevice.id);
    await wrapper.vm.$nextTick();

    // Wait for initial statistics load
    await new Promise(resolve => setTimeout(resolve, 100));

    // Verify initial statistics
    let displayText = wrapper.text();
    expect(displayText).toContain(`${initialStats.cpu_usage}%`);
    expect(displayText).toContain(initialStats.active_users.toString());

    // Simulate real-time update by calling refresh
    const refreshButton = wrapper.find('button[title*="refresh" i], button:has(i.fa-sync-alt)');
    if (refreshButton.exists()) {
      await refreshButton.trigger('click');
      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 100));

      // Verify updated statistics
      displayText = wrapper.text();
      expect(displayText).toContain(`${updatedStats.cpu_usage}%`);
      expect(displayText).toContain(updatedStats.active_users.toString());
    }

    // Verify statistics API was called multiple times
    expect(mockedAxios.get).toHaveBeenCalledWith(`/api/v1/router/${mockDevice.id}/statistics`);
    expect(callCount).toBeGreaterThan(1);
  });

  it('handles statistics loading errors gracefully for any device', async () => {
    const mockDevice = {
      id: 'device-error',
      name: 'Error Router',
      ip_address: '192.168.1.200',
      api_port: 8728,
      status: 'online',
      location: { region: 'Test', district: 'Test' }
    };

    realtimeStore.mikrotikDevices = [mockDevice];

    // Mock API error
    mockedAxios.get.mockImplementation((url: string) => {
      if (url === '/api/v1/router') {
        return Promise.resolve({ data: { data: [mockDevice] } });
      }
      
      if (url.includes('/statistics')) {
        return Promise.reject(new Error('Network error'));
      }
      
      return Promise.resolve({ data: { data: [] } });
    });

    const wrapper = mount(MikroTikConfiguration, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ConnectionStatus: true,
          Modal: true
        }
      }
    });

    await wrapper.vm.$nextTick();

    // Select device
    const deviceSelect = wrapper.find('select');
    await deviceSelect.setValue(mockDevice.id);
    await wrapper.vm.$nextTick();

    // Wait for error to occur
    await new Promise(resolve => setTimeout(resolve, 100));

    // Verify error handling - component should still be functional
    expect(wrapper.exists()).toBe(true);
    
    // Statistics should show default/empty values
    const displayText = wrapper.text();
    expect(displayText).toContain('0%'); // Default CPU usage
    
    // Verify API was attempted
    expect(mockedAxios.get).toHaveBeenCalledWith(`/api/v1/router/${mockDevice.id}/statistics`);
  });

  it('displays comprehensive statistics data for any device type', async () => {
    const testDevices = [
      {
        id: 'router-1',
        name: 'Main Router',
        type: 'main',
        ip_address: '192.168.1.1',
        api_port: 8728,
        status: 'online',
        location: { region: 'Central', district: 'Kampala' }
      },
      {
        id: 'access-point-1',
        name: 'WiFi AP',
        type: 'access_point',
        ip_address: '192.168.1.10',
        api_port: 8728,
        status: 'online',
        location: { region: 'Eastern', district: 'Jinja' }
      }
    ];

    const comprehensiveStats = {
      cpu_usage: 45,
      memory_usage: 65,
      uptime: 259200, // 3 days
      version: 'RouterOS 7.3',
      interfaces: [
        { name: 'ether1', running: true, rx_bytes: 5000000, tx_bytes: 3000000 },
        { name: 'wlan1', running: true, rx_bytes: 2000000, tx_bytes: 1500000 }
      ],
      active_users: 50,
      active_sessions: 45,
      bandwidth_usage: 10000000
    };

    realtimeStore.mikrotikDevices = testDevices;

    mockedAxios.get.mockImplementation((url: string) => {
      if (url === '/api/v1/router') {
        return Promise.resolve({ data: { data: testDevices } });
      }
      
      if (url.includes('/statistics')) {
        return Promise.resolve({ data: { data: comprehensiveStats } });
      }
      
      return Promise.resolve({ data: { data: [] } });
    });

    const wrapper = mount(MikroTikConfiguration, {
      global: {
        plugins: [pinia, router],
        stubs: {
          ConnectionStatus: true,
          Modal: true
        }
      }
    });

    await wrapper.vm.$nextTick();

    // Test each device type
    for (const device of testDevices) {
      // Select device
      const deviceSelect = wrapper.find('select');
      await deviceSelect.setValue(device.id);
      await wrapper.vm.$nextTick();

      // Wait for statistics to load
      await new Promise(resolve => setTimeout(resolve, 100));

      const displayText = wrapper.text();

      // Verify all required statistics components are displayed
      expect(displayText).toContain(`${comprehensiveStats.cpu_usage}%`);
      expect(displayText).toContain(`${comprehensiveStats.memory_usage}%`);
      expect(displayText).toContain(comprehensiveStats.version);
      expect(displayText).toContain(comprehensiveStats.active_users.toString());
      expect(displayText).toContain(comprehensiveStats.active_sessions.toString());

      // Verify interface information is displayed
      for (const iface of comprehensiveStats.interfaces) {
        expect(displayText).toContain(iface.name);
      }

      // Verify uptime formatting (should show days/hours/minutes)
      expect(displayText).toMatch(/\d+d\s+\d+h\s+\d+m/); // Format: "3d 0h 0m"
    }
  });
});