import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PaymentAnalytics from '../components/PaymentAnalytics.vue'

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

describe('Payment Enhancements Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('generates comprehensive payment analytics', async () => {
    const mockAnalytics = {
      total_revenue: 1500000,
      success_rate: 85.5,
      failed_payments: 15,
      gateway_performance: [
        { name: 'CollectUG', success_rate: 90, total_transactions: 100 },
        { name: 'TestGateway', success_rate: 80, total_transactions: 50 }
      ],
      revenue_trends: [
        { date: '2024-01-01', revenue: 50000 },
        { date: '2024-01-02', revenue: 75000 }
      ],
      transaction_volume: {
        daily: 25,
        weekly: 150,
        monthly: 600
      }
    }

    mockApi.get.mockResolvedValue({ data: mockAnalytics })

    const wrapper = mount(PaymentAnalytics)
    await wrapper.vm.$nextTick()

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/payments/statistics')
    expect(wrapper.vm.analyticsData).toEqual(mockAnalytics)

    // Verify analytics components are displayed
    expect(wrapper.find('[data-testid="total-revenue"]').text()).toContain('1,500,000')
    expect(wrapper.find('[data-testid="success-rate"]').text()).toContain('85.5%')
  })

  it('provides complete analytics data structure', async () => {
    const completeAnalytics = {
      success_rates: {
        overall: 87.5,
        by_gateway: {
          'CollectUG': 90.2,
          'TestGateway': 84.8
        }
      },
      revenue_trends: [
        { period: '2024-01', revenue: 500000, transactions: 200 },
        { period: '2024-02', revenue: 650000, transactions: 260 }
      ],
      gateway_performance: [
        {
          gateway_id: '1',
          name: 'CollectUG',
          total_transactions: 200,
          successful_transactions: 180,
          failed_transactions: 20,
          average_response_time: 1.2,
          uptime_percentage: 99.5
        }
      ],
      transaction_trends: {
        hourly_distribution: Array.from({ length: 24 }, (_, i) => ({ hour: i, count: Math.floor(Math.random() * 50) })),
        daily_averages: Array.from({ length: 7 }, (_, i) => ({ day: i, average: Math.floor(Math.random() * 100) }))
      }
    }

    mockApi.get.mockResolvedValue({ data: completeAnalytics })

    const wrapper = mount(PaymentAnalytics)
    await wrapper.vm.$nextTick()

    // Verify all analytics components are present
    expect(wrapper.vm.analyticsData.success_rates).toBeDefined()
    expect(wrapper.vm.analyticsData.revenue_trends).toBeDefined()
    expect(wrapper.vm.analyticsData.gateway_performance).toBeDefined()
    expect(wrapper.vm.analyticsData.transaction_trends).toBeDefined()

    // Verify gateway-specific data
    expect(wrapper.vm.analyticsData.gateway_performance[0]).toHaveProperty('uptime_percentage')
    expect(wrapper.vm.analyticsData.gateway_performance[0]).toHaveProperty('average_response_time')
  })

  it('tests payment gateway connectivity', async () => {
    const connectivityResults = {
      success: true,
      message: 'All gateways connected successfully',
      connection_time: 250,
      gateway_info: {
        'CollectUG': {
          status: 'connected',
          response_time: 120,
          version: '2.1.0'
        },
        'TestGateway': {
          status: 'connected',
          response_time: 180,
          version: '1.5.2'
        }
      }
    }

    mockApi.post.mockResolvedValue({ data: connectivityResults })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.testGatewayConnectivity()

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/payment-gateways/test')
    expect(wrapper.vm.connectivityResults).toEqual(connectivityResults)
  })

  it('performs gateway test transactions', async () => {
    const testResults = {
      test_results: [
        {
          gateway_id: '1',
          gateway_name: 'CollectUG',
          success: true,
          response_time: 1.2,
          transaction_id: 'TEST_TXN_001',
          amount: 1000,
          status: 'completed'
        },
        {
          gateway_id: '2',
          gateway_name: 'TestGateway',
          success: false,
          response_time: 5.0,
          error: 'Connection timeout',
          amount: 1000,
          status: 'failed'
        }
      ]
    }

    mockApi.post.mockResolvedValue({ data: testResults })

    const wrapper = mount(PaymentAnalytics)
    
    const testData = {
      amount: 1000,
      currency: 'UGX',
      test_mode: true
    }

    await wrapper.vm.performTestTransactions(testData)

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/payment-gateways/test', testData)
    expect(wrapper.vm.testTransactionResults).toEqual(testResults.test_results)
  })

  it('handles payment record editing with validation', async () => {
    const payment = {
      id: '1',
      amount: 50000,
      status: 'completed',
      gateway_id: '1',
      created_at: '2024-01-12T10:00:00Z'
    }

    const updateData = {
      amount: 55000,
      notes: 'Corrected amount after customer inquiry',
      reason: 'amount_correction'
    }

    mockApi.put.mockResolvedValue({ 
      data: { 
        success: true, 
        payment: { ...payment, ...updateData }
      }
    })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.editPayment(payment.id, updateData)

    expect(mockApi.put).toHaveBeenCalledWith(`/api/v1/payments/${payment.id}`, updateData)
    expect(wrapper.vm.editSuccess).toBe(true)
  })

  it('maintains audit trails for payment modifications', async () => {
    const auditTrail = {
      payment_id: '1',
      changes: [
        {
          field: 'amount',
          old_value: 50000,
          new_value: 55000,
          changed_by: 'admin@example.com',
          changed_at: '2024-01-12T10:00:00Z',
          reason: 'amount_correction'
        }
      ]
    }

    mockApi.get.mockResolvedValue({ data: auditTrail })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.loadAuditTrail('1')

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/payments/1/audit-trail')
    expect(wrapper.vm.auditTrail).toEqual(auditTrail)
  })

  it('provides payment reconciliation tools', async () => {
    const reconciliationData = {
      reconciliation_summary: {
        total_transactions: 150,
        reconciled_count: 140,
        unreconciled_count: 10,
        discrepancies: 3
      },
      unreconciled_payments: [
        {
          id: '1',
          amount: 50000,
          gateway_transaction_id: 'TXN123',
          status: 'completed',
          reconciled: false
        }
      ],
      discrepancies: [
        {
          id: '1',
          payment_id: '2',
          type: 'amount_mismatch',
          expected_amount: 75000,
          actual_amount: 73000,
          status: 'open'
        }
      ]
    }

    mockApi.post.mockResolvedValue({ data: reconciliationData })

    const wrapper = mount(PaymentAnalytics)
    
    const reconciliationParams = {
      date_from: '2024-01-01',
      date_to: '2024-01-31'
    }

    await wrapper.vm.performReconciliation(reconciliationParams)

    expect(mockApi.post).toHaveBeenCalledWith('/api/v1/payments/reconciliation', reconciliationParams)
    expect(wrapper.vm.reconciliationData).toEqual(reconciliationData)
  })

  it('handles discrepancy resolution', async () => {
    const discrepancyId = '1'
    const resolutionData = {
      resolution: 'gateway_error',
      notes: 'Confirmed with gateway support - system error',
      corrected_amount: 50000
    }

    mockApi.post.mockResolvedValue({ 
      data: { 
        success: true, 
        message: 'Discrepancy resolved successfully' 
      }
    })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.resolveDiscrepancy(discrepancyId, resolutionData)

    expect(mockApi.post).toHaveBeenCalledWith(
      `/api/v1/payments/discrepancies/${discrepancyId}/resolve`,
      resolutionData
    )
    expect(wrapper.vm.resolutionSuccess).toBe(true)
  })

  it('flags payment disputes', async () => {
    const paymentId = '1'
    const disputeData = {
      reason: 'unauthorized_transaction',
      description: 'Customer claims they did not make this payment',
      evidence: 'Customer provided bank statement showing no authorization'
    }

    mockApi.post.mockResolvedValue({ 
      data: { 
        success: true, 
        dispute_id: 'DISPUTE_001' 
      }
    })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.flagDispute(paymentId, disputeData)

    expect(mockApi.post).toHaveBeenCalledWith(
      `/api/v1/payments/discrepancies/${paymentId}/dispute`,
      disputeData
    )
    expect(wrapper.vm.disputeFlagged).toBe(true)
  })

  it('exports reconciliation data', async () => {
    const exportParams = {
      format: 'csv',
      date_from: '2024-01-01',
      date_to: '2024-01-31'
    }

    // Mock blob response for file download
    const mockBlob = new Blob(['csv,data,here'], { type: 'text/csv' })
    mockApi.get.mockResolvedValue({ 
      data: mockBlob,
      headers: {
        'content-type': 'text/csv',
        'content-disposition': 'attachment; filename="reconciliation-2024-01.csv"'
      }
    })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.exportReconciliation(exportParams)

    expect(mockApi.get).toHaveBeenCalledWith('/api/v1/payments/reconciliation/export', {
      params: exportParams
    })
    expect(wrapper.vm.exportCompleted).toBe(true)
  })

  it('verifies payment transactions', async () => {
    const transactionId = 'TXN123456'
    const verificationResult = {
      verified: true,
      gateway_status: 'completed',
      amount_match: true,
      verification_details: {
        gateway_amount: 50000,
        system_amount: 50000,
        gateway_timestamp: '2024-01-12T10:00:00Z',
        system_timestamp: '2024-01-12T10:00:05Z'
      }
    }

    mockApi.post.mockResolvedValue({ data: verificationResult })

    const wrapper = mount(PaymentAnalytics)
    
    await wrapper.vm.verifyTransaction(transactionId)

    expect(mockApi.post).toHaveBeenCalledWith(`/api/v1/payments/${transactionId}/verify`)
    expect(wrapper.vm.verificationResult).toEqual(verificationResult)
  })

  it('integrates analytics with gateway testing', async () => {
    // Mock analytics data
    const analyticsData = {
      gateway_performance: [
        { name: 'CollectUG', success_rate: 90, total_transactions: 100 },
        { name: 'TestGateway', success_rate: 80, total_transactions: 50 }
      ]
    }

    // Mock test results
    const testResults = {
      test_results: [
        { gateway_id: '1', success: true, response_time: 1.2 },
        { gateway_id: '2', success: false, response_time: 5.0 }
      ]
    }

    mockApi.get.mockResolvedValueOnce({ data: analyticsData })
    mockApi.post.mockResolvedValueOnce({ data: testResults })

    const wrapper = mount(PaymentAnalytics)
    
    // Load analytics
    await wrapper.vm.loadAnalytics()
    
    // Perform gateway tests
    await wrapper.vm.testGateways()

    // Verify integration
    expect(wrapper.vm.analyticsData.gateway_performance).toBeDefined()
    expect(wrapper.vm.testResults.test_results).toBeDefined()
    
    // Verify correlation between analytics and test results
    const analyticsGateways = wrapper.vm.analyticsData.gateway_performance.length
    const testGateways = wrapper.vm.testResults.test_results.length
    expect(analyticsGateways).toBeGreaterThan(0)
    expect(testGateways).toBeGreaterThan(0)
  })

  it('handles payment editing with business rules validation', async () => {
    const oldPayment = {
      id: '1',
      amount: 50000,
      status: 'completed',
      created_at: '2023-12-01T10:00:00Z' // Old payment
    }

    const updateData = {
      amount: 75000,
      reason: 'amount_correction'
    }

    // Mock validation error for old payment
    mockApi.put.mockRejectedValue({
      response: {
        status: 422,
        data: {
          errors: {
            payment_age: ['Payments older than 30 days require manager approval']
          }
        }
      }
    })

    const wrapper = mount(PaymentAnalytics)
    
    try {
      await wrapper.vm.editPayment(oldPayment.id, updateData)
    } catch (error) {
      expect(error.response.status).toBe(422)
      expect(wrapper.vm.validationErrors).toContain('Payments older than 30 days require manager approval')
    }
  })
})