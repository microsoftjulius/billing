import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import VoucherManagement from '../components/VoucherManagement.vue'
import VoucherTransferModal from '../components/common/VoucherTransferModal.vue'
import VoucherRefundModal from '../components/common/VoucherRefundModal.vue'
import { useVoucherStore } from '../store/modules/voucher'
import { useMikroTikStore } from '../store/modules/mikrotik'
import { useCustomerStore } from '../store/modules/customer'

// Mock API services
vi.mock('../services/api', () => ({
  voucherApi: {
    getVouchers: vi.fn(),
    activateVoucher: vi.fn(),
    transferVoucher: vi.fn(),
    refundVoucher: vi.fn(),
    getAnalytics: vi.fn(),
    generateBulk: vi.fn(),
    cleanupExpired: vi.fn()
  },
  mikrotikApi: {
    getDevices: vi.fn(),
    createUser: vi.fn(),
    getUserStatus: vi.fn(),
    syncUserStatus: vi.fn()
  },
  customerApi: {
    getCustomers: vi.fn(),
    getCustomer: vi.fn()
  }
}))

describe('Voucher System Integration', () => {
  let pinia: any
  let voucherStore: any
  let mikrotikStore: any
  let customerStore: any

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    voucherStore = useVoucherStore()
    mikrotikStore = useMikroTikStore()
    customerStore = useCustomerStore()
  })

  describe('Voucher-MikroTik Integration', () => {
    it('should activate voucher on MikroTik device', async () => {
      // Arrange
      const voucher = {
        id: 1,
        code: 'TEST123',
        status: 'active',
        duration_hours: 24,
        bandwidth_limit: '10M/10M'
      }
      
      const device = {
        id: 1,
        name: 'Router-1',
        ip_address: '192.168.1.1',
        status: 'online'
      }

      voucherStore.vouchers = [voucher]
      mikrotikStore.devices = [device]

      // Act
      const result = await voucherStore.activateVoucherOnMikroTik(voucher.id, device.id)

      // Assert
      expect(result).toBe(true)
      expect(voucherStore.vouchers[0].mikrotik_user_id).toBeDefined()
      expect(mikrotikStore.users).toContainEqual(
        expect.objectContaining({
          username: voucher.code,
          device_id: device.id,
          status: 'active'
        })
      )
    })

    it('should sync voucher status with MikroTik user status', async () => {
      // Arrange
      const voucher = {
        id: 1,
        code: 'TEST123',
        status: 'active',
        mikrotik_user_id: 1
      }
      
      const mikrotikUser = {
        id: 1,
        username: 'TEST123',
        status: 'disconnected',
        voucher_id: 1
      }

      voucherStore.vouchers = [voucher]
      mikrotikStore.users = [mikrotikUser]

      // Act
      await voucherStore.syncVoucherStatus(voucher.id)

      // Assert
      expect(voucherStore.vouchers[0].status).toBe('used')
    })

    it('should handle MikroTik device offline during activation', async () => {
      // Arrange
      const voucher = { id: 1, code: 'TEST123', status: 'active' }
      const device = { id: 1, status: 'offline' }

      voucherStore.vouchers = [voucher]
      mikrotikStore.devices = [device]

      // Act & Assert
      await expect(
        voucherStore.activateVoucherOnMikroTik(voucher.id, device.id)
      ).rejects.toThrow('Device is offline')
    })
  })

  describe('Voucher Transfer Functionality', () => {
    it('should transfer voucher between customers', async () => {
      // Arrange
      const sourceCustomer = { id: 1, name: 'John Doe' }
      const targetCustomer = { id: 2, name: 'Jane Smith' }
      const voucher = {
        id: 1,
        code: 'TRANSFER123',
        customer_id: sourceCustomer.id,
        status: 'active'
      }

      voucherStore.vouchers = [voucher]
      customerStore.customers = [sourceCustomer, targetCustomer]

      const wrapper = mount(VoucherTransferModal, {
        global: { plugins: [pinia] },
        props: {
          voucher,
          isOpen: true
        }
      })

      // Act
      await wrapper.find('select[name="target_customer"]').setValue(targetCustomer.id)
      await wrapper.find('form').trigger('submit')

      // Assert
      expect(voucherStore.vouchers[0].customer_id).toBe(targetCustomer.id)
      expect(voucherStore.vouchers[0].status).toBe('transferred')
      expect(voucherStore.transferHistory).toContainEqual(
        expect.objectContaining({
          voucher_id: voucher.id,
          from_customer_id: sourceCustomer.id,
          to_customer_id: targetCustomer.id
        })
      )
    })

    it('should validate transfer constraints', async () => {
      // Arrange
      const voucher = {
        id: 1,
        code: 'USED123',
        status: 'used'
      }

      const wrapper = mount(VoucherTransferModal, {
        global: { plugins: [pinia] },
        props: {
          voucher,
          isOpen: true
        }
      })

      // Act & Assert
      expect(wrapper.find('.error-message').text()).toContain(
        'Cannot transfer used voucher'
      )
      expect(wrapper.find('form button[type="submit"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('Voucher Refund System', () => {
    it('should process voucher refund with payment reversal', async () => {
      // Arrange
      const voucher = {
        id: 1,
        code: 'REFUND123',
        status: 'active',
        amount: 5000,
        payment_id: 1
      }

      voucherStore.vouchers = [voucher]

      const wrapper = mount(VoucherRefundModal, {
        global: { plugins: [pinia] },
        props: {
          voucher,
          isOpen: true
        }
      })

      // Act
      await wrapper.find('textarea[name="reason"]').setValue('Customer request')
      await wrapper.find('form').trigger('submit')

      // Assert
      expect(voucherStore.vouchers[0].status).toBe('refunded')
      expect(voucherStore.refunds).toContainEqual(
        expect.objectContaining({
          voucher_id: voucher.id,
          amount: voucher.amount,
          reason: 'Customer request'
        })
      )
    })

    it('should calculate partial refund amounts', async () => {
      // Arrange
      const voucher = {
        id: 1,
        code: 'PARTIAL123',
        status: 'active',
        amount: 10000,
        usage_percentage: 30
      }

      const wrapper = mount(VoucherRefundModal, {
        global: { plugins: [pinia] },
        props: {
          voucher,
          isOpen: true
        }
      })

      // Act
      await wrapper.find('input[name="partial_refund"]').setChecked(true)

      // Assert
      const refundAmount = wrapper.find('.refund-amount').text()
      expect(refundAmount).toContain('7,000') // 70% of 10,000
    })
  })

  describe('Voucher Expiration Management', () => {
    it('should automatically cleanup expired vouchers', async () => {
      // Arrange
      const expiredVoucher = {
        id: 1,
        code: 'EXPIRED123',
        status: 'active',
        expires_at: new Date(Date.now() - 86400000) // 1 day ago
      }
      
      const activeVoucher = {
        id: 2,
        code: 'ACTIVE123',
        status: 'active',
        expires_at: new Date(Date.now() + 86400000) // 1 day from now
      }

      voucherStore.vouchers = [expiredVoucher, activeVoucher]

      // Act
      const cleanupResult = await voucherStore.cleanupExpiredVouchers()

      // Assert
      expect(cleanupResult.processed).toBe(1)
      expect(voucherStore.vouchers[0].status).toBe('expired')
      expect(voucherStore.vouchers[1].status).toBe('active')
    })

    it('should send expiration notifications', async () => {
      // Arrange
      const expiringVoucher = {
        id: 1,
        code: 'EXPIRING123',
        status: 'active',
        expires_at: new Date(Date.now() + 3600000), // 1 hour from now
        customer: { phone: '+256700000000' }
      }

      voucherStore.vouchers = [expiringVoucher]

      // Act
      await voucherStore.sendExpirationNotifications()

      // Assert
      expect(voucherStore.notifications).toContainEqual(
        expect.objectContaining({
          voucher_id: expiringVoucher.id,
          type: 'expiration_warning',
          sent: true
        })
      )
    })
  })

  describe('Voucher Analytics Integration', () => {
    it('should generate comprehensive usage analytics', async () => {
      // Arrange
      const vouchers = [
        {
          id: 1,
          status: 'used',
          amount: 2000,
          duration_hours: 24,
          bytes_used: 1000000000
        },
        {
          id: 2,
          status: 'used',
          amount: 5000,
          duration_hours: 72,
          bytes_used: 2500000000
        }
      ]

      voucherStore.vouchers = vouchers

      // Act
      const analytics = await voucherStore.getAnalytics()

      // Assert
      expect(analytics).toEqual(
        expect.objectContaining({
          total_vouchers: 2,
          total_revenue: 7000,
          average_usage: 1750000000,
          usage_by_duration: expect.any(Object),
          revenue_trends: expect.any(Array)
        })
      )
    })

    it('should track customer voucher patterns', async () => {
      // Arrange
      const customerId = 1
      const customerVouchers = [
        { id: 1, customer_id: customerId, status: 'used', created_at: '2024-01-01' },
        { id: 2, customer_id: customerId, status: 'used', created_at: '2024-01-15' },
        { id: 3, customer_id: customerId, status: 'active', created_at: '2024-01-30' }
      ]

      voucherStore.vouchers = customerVouchers

      // Act
      const patterns = await voucherStore.getCustomerUsagePatterns(customerId)

      // Assert
      expect(patterns).toEqual(
        expect.objectContaining({
          purchase_frequency: expect.any(Number),
          preferred_duration: expect.any(Number),
          usage_efficiency: expect.any(Number),
          loyalty_score: expect.any(Number)
        })
      )
    })
  })

  describe('Bulk Operations with Progress Tracking', () => {
    it('should generate bulk vouchers with progress updates', async () => {
      // Arrange
      const bulkData = {
        count: 100,
        duration_hours: 24,
        bandwidth_limit: '5M/5M',
        amount: 2000,
        customer_id: 1
      }

      const wrapper = mount(VoucherManagement, {
        global: { plugins: [pinia] }
      })

      // Act
      const progressPromise = voucherStore.generateBulkVouchers(bulkData)
      
      // Assert progress updates
      await new Promise(resolve => setTimeout(resolve, 100))
      expect(voucherStore.bulkProgress.current).toBeGreaterThan(0)
      expect(voucherStore.bulkProgress.total).toBe(100)
      
      await progressPromise
      expect(voucherStore.bulkProgress.completed).toBe(true)
      expect(voucherStore.vouchers).toHaveLength(100)
    })

    it('should handle bulk operation errors gracefully', async () => {
      // Arrange
      const bulkData = {
        count: 50,
        duration_hours: 24,
        amount: 2000,
        customer_id: 999 // Non-existent customer
      }

      // Act & Assert
      await expect(
        voucherStore.generateBulkVouchers(bulkData)
      ).rejects.toThrow('Customer not found')
      
      expect(voucherStore.bulkProgress.errors).toHaveLength(1)
      expect(voucherStore.bulkProgress.errors[0]).toContain('Customer not found')
    })
  })
})