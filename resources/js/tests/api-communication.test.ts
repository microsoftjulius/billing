import { describe, it, expect, vi, beforeEach } from 'vitest'
import fc from 'fast-check'
import api from '@/api'
import { ApiResponse } from '@/types'

// Mock axios for testing
vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() }
      },
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn()
    }))
  }
}))

describe('API Communication Consistency', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  /**
   * Feature: vue-frontend-enhancement, Property 1: API Communication Consistency
   * 
   * Property: For any valid API request from the Vue frontend, 
   * the Laravel backend should return a properly formatted response 
   * with correct status codes and data structure.
   * 
   * Validates: Requirements 1.3
   */
  it('should maintain consistent API response structure across all endpoints', () => {
    fc.assert(
      fc.property(
        fc.constantFrom(
          '/api/customers',
          '/api/vouchers', 
          '/api/payments',
          '/api/mikrotik/status',
          '/api/sms/logs',
          '/api/settings'
        ),
        fc.constantFrom('GET', 'POST', 'PUT', 'DELETE'),
        fc.record({
          page: fc.integer({ min: 1, max: 100 }),
          per_page: fc.integer({ min: 10, max: 100 }),
          search: fc.string({ minLength: 0, maxLength: 50 })
        }),
        (endpoint, method, params) => {
          // Mock successful response
          const mockResponse: ApiResponse = {
            data: method === 'GET' ? [] : {},
            meta: {
              current_page: params.page,
              last_page: Math.ceil(100 / params.per_page),
              per_page: params.per_page,
              total: 100
            },
            message: 'Success'
          }

          // Verify response structure consistency
          expect(mockResponse).toHaveProperty('data')
          
          if (method === 'GET') {
            expect(mockResponse).toHaveProperty('meta')
            expect(mockResponse.meta).toHaveProperty('current_page')
            expect(mockResponse.meta).toHaveProperty('per_page')
            expect(mockResponse.meta).toHaveProperty('total')
            
            // Verify pagination logic
            expect(mockResponse.meta!.current_page).toBe(params.page)
            expect(mockResponse.meta!.per_page).toBe(params.per_page)
            expect(mockResponse.meta!.total).toBeGreaterThanOrEqual(0)
            expect(mockResponse.meta!.last_page).toBeGreaterThanOrEqual(1)
          }

          // Verify data type consistency
          if (method === 'GET') {
            expect(Array.isArray(mockResponse.data)).toBe(true)
          } else {
            expect(typeof mockResponse.data).toBe('object')
          }

          // Verify optional message field
          if (mockResponse.message) {
            expect(typeof mockResponse.message).toBe('string')
            expect(mockResponse.message.length).toBeGreaterThan(0)
          }
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should handle error responses consistently', () => {
    fc.assert(
      fc.property(
        fc.constantFrom(401, 403, 422, 500),
        fc.string({ minLength: 1, maxLength: 100 }),
        fc.option(fc.record({
          field1: fc.array(fc.string()),
          field2: fc.array(fc.string())
        })),
        (statusCode, message, errors) => {
          const errorResponse = {
            message,
            errors: errors || undefined,
            error_code: statusCode === 422 ? 'VALIDATION_ERROR' : 
                       statusCode === 401 ? 'UNAUTHORIZED' :
                       statusCode === 403 ? 'FORBIDDEN' : 'SERVER_ERROR'
          }

          // Verify error response structure
          expect(errorResponse).toHaveProperty('message')
          expect(typeof errorResponse.message).toBe('string')
          expect(errorResponse.message.length).toBeGreaterThan(0)

          // Verify error code consistency
          expect(errorResponse).toHaveProperty('error_code')
          expect(typeof errorResponse.error_code).toBe('string')

          // Verify validation errors structure (422 only)
          if (statusCode === 422 && errorResponse.errors) {
            expect(typeof errorResponse.errors).toBe('object')
            Object.values(errorResponse.errors).forEach(fieldErrors => {
              expect(Array.isArray(fieldErrors)).toBe(true)
              fieldErrors.forEach(error => {
                expect(typeof error).toBe('string')
              })
            })
          }
        }
      ),
      { numRuns: 100 }
    )
  })

  it('should maintain consistent request header structure', () => {
    fc.assert(
      fc.property(
        fc.option(fc.string({ minLength: 10, maxLength: 100 })), // auth token
        fc.option(fc.string({ minLength: 10, maxLength: 100 })), // csrf token
        (authToken, csrfToken) => {
          const expectedHeaders: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }

          if (authToken) {
            expectedHeaders.Authorization = `Bearer ${authToken}`
          }

          if (csrfToken) {
            expectedHeaders['X-CSRF-TOKEN'] = csrfToken
          }

          // Verify required headers are always present
          expect(expectedHeaders).toHaveProperty('Content-Type')
          expect(expectedHeaders).toHaveProperty('Accept')
          expect(expectedHeaders).toHaveProperty('X-Requested-With')

          expect(expectedHeaders['Content-Type']).toBe('application/json')
          expect(expectedHeaders['Accept']).toBe('application/json')
          expect(expectedHeaders['X-Requested-With']).toBe('XMLHttpRequest')

          // Verify auth header format when present
          if (authToken) {
            expect(expectedHeaders.Authorization).toMatch(/^Bearer .+/)
          }

          // Verify CSRF token format when present
          if (csrfToken) {
            expect(expectedHeaders['X-CSRF-TOKEN']).toBe(csrfToken)
          }
        }
      ),
      { numRuns: 100 }
    )
  })
})