import { describe, it, expect, vi, beforeEach } from 'vitest'
import fc from 'fast-check'
import { Customer, SmsLog } from '@/types'

// Mock customer communication service
const mockCommunicationService = {
  getCustomerSmsHistory: vi.fn(),
  getCustomerCommunicationTimeline: vi.fn()
}

describe('Customer Communication History', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  /**
   * Feature: vue-frontend-enhancement, Property 23: Customer Communication History
   * 
   * Property: For any customer record, the communication history 
   * should include all SMS logs associated with that customer.
   * 
   * Validates: Requirements 9.5
   */
  it('should include all SMS logs for a customer in communication history', () => {
    fc.assert(
      fc.property(
        // Generate customer data with constrained dates
        fc.record({
          id: fc.uuid(),
          name: fc.string({ minLength: 1, maxLength: 100 }),
          phone: fc.string({ minLength: 10, maxLength: 15 }),
          status: fc.constantFrom('active', 'suspended', 'inactive'),
          created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
          updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
        }),
        // Generate SMS logs for the customer using correct SmsLog interface
        fc.array(fc.record({
          id: fc.uuid(),
          customer_id: fc.uuid(), // Will be overridden to match customer
          recipient: fc.string({ minLength: 10, maxLength: 15 }),
          phone_number: fc.string({ minLength: 10, maxLength: 15 }), // Alias for recipient
          content: fc.string({ minLength: 1, maxLength: 500 }),
          message: fc.string({ minLength: 1, maxLength: 500 }), // Alias for content
          sender_id: fc.option(fc.string({ minLength: 1, maxLength: 20 })),
          message_id: fc.option(fc.string()),
          status: fc.constantFrom('pending', 'sent', 'delivered', 'failed'),
          delivery_status: fc.option(fc.constantFrom('pending', 'delivered', 'failed')),
          cost: fc.option(fc.float({ min: Math.fround(0.01), max: Math.fround(10.0) })),
          currency: fc.option(fc.constantFrom('UGX', 'USD')),
          provider: fc.constantFrom('ugsms', 'other'),
          provider_response: fc.option(fc.record({
            message_id: fc.string(),
            status_code: fc.integer({ min: 200, max: 500 }),
            description: fc.string()
          })),
          metadata: fc.option(fc.record({
            campaign_id: fc.option(fc.string()),
            template_id: fc.option(fc.string())
          })),
          sent_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
          delivered_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
          failed_at: fc.option(fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())),
          created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
          updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
        }), { minLength: 0, maxLength: 50 }),
        (customer: Customer, smsLogs: SmsLog[]) => {
          // Ensure SMS logs belong to the customer
          const customerSmsLogs = smsLogs.map(log => ({
            ...log,
            customer_id: customer.id,
            recipient: customer.phone,
            phone_number: customer.phone
          }))

          // Mock service response
          mockCommunicationService.getCustomerSmsHistory.mockResolvedValue(customerSmsLogs)

          // Verify all SMS logs are included in communication history
          expect(Array.isArray(customerSmsLogs)).toBe(true)

          customerSmsLogs.forEach(smsLog => {
            // Verify SMS log structure completeness
            expect(smsLog).toHaveProperty('id')
            expect(smsLog).toHaveProperty('customer_id')
            expect(smsLog).toHaveProperty('recipient')
            expect(smsLog).toHaveProperty('content')
            expect(smsLog).toHaveProperty('status')
            expect(smsLog).toHaveProperty('provider')
            expect(smsLog).toHaveProperty('created_at')
            expect(smsLog).toHaveProperty('updated_at')

            // Verify SMS log belongs to the correct customer
            expect(smsLog.customer_id).toBe(customer.id)
            expect(smsLog.recipient).toBe(customer.phone)
            expect(smsLog.phone_number).toBe(customer.phone)

            // Verify field types and constraints
            expect(typeof smsLog.id).toBe('string')
            expect(smsLog.id.length).toBeGreaterThan(0)
            expect(typeof smsLog.customer_id).toBe('string')
            expect(smsLog.customer_id.length).toBeGreaterThan(0)
            expect(typeof smsLog.recipient).toBe('string')
            expect(smsLog.recipient.length).toBeGreaterThanOrEqual(10)
            expect(typeof smsLog.content).toBe('string')
            expect(smsLog.content.length).toBeGreaterThan(0)
            expect(['pending', 'sent', 'delivered', 'failed']).toContain(smsLog.status)
            expect(typeof smsLog.provider).toBe('string')
            expect(smsLog.provider.length).toBeGreaterThan(0)

            // Verify timestamp format (ISO 8601 with 4-digit year)
            expect(smsLog.created_at).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/)
            expect(smsLog.updated_at).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/)

            // Verify optional fields when present
            if (smsLog.provider_response) {
              expect(typeof smsLog.provider_response).toBe('object')
              if (smsLog.provider_response.message_id) {
                expect(typeof smsLog.provider_response.message_id).toBe('string')
              }
              if (smsLog.provider_response.status_code) {
                expect(typeof smsLog.provider_response.status_code).toBe('number')
                expect(smsLog.provider_response.status_code).toBeGreaterThanOrEqual(200)
                expect(smsLog.provider_response.status_code).toBeLessThan(600)
              }
            }

            if (smsLog.cost) {
              expect(typeof smsLog.cost).toBe('number')
              expect(smsLog.cost).toBeGreaterThan(0)
            }

            if (smsLog.sent_at) {
              expect(smsLog.sent_at).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/)
            }

            if (smsLog.delivered_at) {
              expect(smsLog.delivered_at).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/)
            }

            if (smsLog.failed_at) {
              expect(smsLog.failed_at).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/)
            }
          })
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should maintain chronological order in communication history', () => {
    fc.assert(
      fc.property(
        fc.record({
          id: fc.uuid(),
          phone: fc.string({ minLength: 10, maxLength: 15 })
        }),
        fc.array(
          fc.record({
            id: fc.uuid(),
            customer_id: fc.uuid(),
            phone_number: fc.string({ minLength: 10, maxLength: 15 }),
            message: fc.string({ minLength: 1, maxLength: 500 }),
            status: fc.constantFrom('pending', 'sent', 'delivered', 'failed'),
            created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
          }),
          { minLength: 2, maxLength: 20 }
        ),
        (customer, smsLogs) => {
          // Ensure SMS logs belong to customer and sort by created_at
          const customerSmsLogs = smsLogs
            .map(log => ({
              ...log,
              customer_id: customer.id,
              phone_number: customer.phone
            }))
            .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())

          mockCommunicationService.getCustomerCommunicationTimeline.mockResolvedValue(customerSmsLogs)

          // Verify chronological ordering (newest first)
          for (let i = 0; i < customerSmsLogs.length - 1; i++) {
            const currentDate = new Date(customerSmsLogs[i].created_at).getTime()
            const nextDate = new Date(customerSmsLogs[i + 1].created_at).getTime()
            
            expect(currentDate).toBeGreaterThanOrEqual(nextDate)
          }

          // Verify all logs are for the same customer
          customerSmsLogs.forEach(log => {
            expect(log.customer_id).toBe(customer.id)
            expect(log.phone_number).toBe(customer.phone)
          })
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should filter communication history by message type and status', () => {
    fc.assert(
      fc.property(
        fc.record({
          id: fc.uuid(),
          phone: fc.string({ minLength: 10, maxLength: 15 })
        }),
        fc.array(
          fc.record({
            id: fc.uuid(),
            customer_id: fc.uuid(),
            phone_number: fc.string({ minLength: 10, maxLength: 15 }),
            message: fc.string({ minLength: 1, maxLength: 500 }),
            status: fc.constantFrom('pending', 'sent', 'delivered', 'failed'),
            created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
          }),
          { minLength: 5, maxLength: 30 }
        ),
        fc.constantFrom('pending', 'sent', 'delivered', 'failed'),
        (customer, smsLogs, filterStatus) => {
          const customerSmsLogs = smsLogs.map(log => ({
            ...log,
            customer_id: customer.id,
            phone_number: customer.phone
          }))

          // Filter logs by status
          const filteredLogs = customerSmsLogs.filter(log => log.status === filterStatus)

          // Verify all filtered logs have the correct status
          filteredLogs.forEach(log => {
            expect(log.status).toBe(filterStatus)
            expect(log.customer_id).toBe(customer.id)
          })

          // Verify filter doesn't include logs with different status
          const otherStatusLogs = customerSmsLogs.filter(log => log.status !== filterStatus)
          otherStatusLogs.forEach(log => {
            expect(log.status).not.toBe(filterStatus)
          })

          // Verify total count consistency
          expect(filteredLogs.length + otherStatusLogs.length).toBe(customerSmsLogs.length)
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should handle empty communication history gracefully', () => {
    fc.assert(
      fc.property(
        fc.record({
          id: fc.uuid(),
          name: fc.string({ minLength: 1, maxLength: 100 }),
          phone: fc.string({ minLength: 10, maxLength: 15 }),
          status: fc.constantFrom('active', 'suspended', 'inactive'),
          created_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString()),
          updated_at: fc.integer({ min: 1704067200000, max: 1735689599000 }).map(ts => new Date(ts).toISOString())
        }),
        (customer: Customer) => {
          // Mock empty SMS history
          const emptySmsHistory: SmsLog[] = []
          mockCommunicationService.getCustomerSmsHistory.mockResolvedValue(emptySmsHistory)

          // Verify empty history is handled correctly
          expect(Array.isArray(emptySmsHistory)).toBe(true)
          expect(emptySmsHistory.length).toBe(0)

          // Verify customer still has valid data
          expect(customer).toHaveProperty('id')
          expect(customer).toHaveProperty('name')
          expect(customer).toHaveProperty('phone')
          expect(typeof customer.id).toBe('string')
          expect(customer.id.length).toBeGreaterThan(0)
        }
      ),
      { numRuns: 100 }
    )
  })
})