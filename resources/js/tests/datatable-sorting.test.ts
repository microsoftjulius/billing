import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/common/DataTable.vue'
import fc from 'fast-check'
import type { Customer, Voucher } from '@/types'

// Feature: vue-frontend-enhancement, Property 6: DataTable Sorting Behavior

describe('DataTable Sorting Behavior', () => {
  const mockCustomerColumns = [
    { key: 'name', title: 'Name', sortable: true },
    { key: 'email', title: 'Email', sortable: true },
    { key: 'phone', title: 'Phone', sortable: true },
    { key: 'status', title: 'Status', sortable: true },
    { key: 'created_at', title: 'Created', sortable: true }
  ]

  const mockVoucherColumns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'amount', title: 'Amount', sortable: true },
    { key: 'status', title: 'Status', sortable: true },
    { key: 'duration_hours', title: 'Duration', sortable: true }
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should sort data in ascending order on first click', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            name: fc.string({ minLength: 3, maxLength: 20 }),
            email: fc.emailAddress(),
            phone: fc.string({ minLength: 10, maxLength: 15 }),
            status: fc.constantFrom('active', 'suspended', 'inactive'),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 3, maxLength: 10 }
        ),
        fc.constantFrom('name', 'email', 'phone', 'status'),
        (customerData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns
            }
          })

          // Click column header to sort
          wrapper.vm.sortByColumn(sortColumn)

          // Should sort in ascending order first
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')

          // Verify data is actually sorted in ascending order
          const sortedData = wrapper.vm.filteredData
          for (let i = 1; i < sortedData.length; i++) {
            const prevValue = String(sortedData[i - 1][sortColumn]).toLowerCase()
            const currentValue = String(sortedData[i][sortColumn]).toLowerCase()
            expect(prevValue.localeCompare(currentValue)).toBeLessThanOrEqual(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should sort data in descending order on second click', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            name: fc.string({ minLength: 3, maxLength: 20 }),
            email: fc.emailAddress(),
            phone: fc.string({ minLength: 10, maxLength: 15 }),
            status: fc.constantFrom('active', 'suspended', 'inactive'),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 3, maxLength: 10 }
        ),
        fc.constantFrom('name', 'email', 'phone', 'status'),
        (customerData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns
            }
          })

          // First click - ascending
          wrapper.vm.sortByColumn(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')

          // Second click - descending
          wrapper.vm.sortByColumn(sortColumn)
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('desc')

          // Verify data is actually sorted in descending order
          const sortedData = wrapper.vm.filteredData
          for (let i = 1; i < sortedData.length; i++) {
            const prevValue = String(sortedData[i - 1][sortColumn]).toLowerCase()
            const currentValue = String(sortedData[i][sortColumn]).toLowerCase()
            expect(prevValue.localeCompare(currentValue)).toBeGreaterThanOrEqual(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should handle numeric sorting correctly', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            code: fc.string({ minLength: 5, maxLength: 15 }),
            amount: fc.float({ min: 100, max: 50000 }),
            status: fc.constantFrom('unused', 'active', 'expired', 'suspended'),
            duration_hours: fc.integer({ min: 1, max: 168 }),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 5, maxLength: 15 }
        ),
        fc.constantFrom('amount', 'duration_hours'),
        (voucherData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: voucherData,
              columns: mockVoucherColumns
            }
          })

          // Sort by numeric column in ascending order
          wrapper.vm.sortByColumn(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')

          const sortedData = wrapper.vm.filteredData
          
          // Verify numeric ascending sort
          for (let i = 1; i < sortedData.length; i++) {
            const prevValue = Number(sortedData[i - 1][sortColumn])
            const currentValue = Number(sortedData[i][sortColumn])
            expect(prevValue).toBeLessThanOrEqual(currentValue)
          }

          // Sort in descending order
          wrapper.vm.sortByColumn(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('desc')

          const descendingSortedData = wrapper.vm.filteredData
          
          // Verify numeric descending sort
          for (let i = 1; i < descendingSortedData.length; i++) {
            const prevValue = Number(descendingSortedData[i - 1][sortColumn])
            const currentValue = Number(descendingSortedData[i][sortColumn])
            expect(prevValue).toBeGreaterThanOrEqual(currentValue)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should reset to ascending when switching to different column', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            name: fc.string({ minLength: 3, maxLength: 20 }),
            email: fc.emailAddress(),
            phone: fc.string({ minLength: 10, maxLength: 15 }),
            status: fc.constantFrom('active', 'suspended', 'inactive'),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 3, maxLength: 10 }
        ),
        fc.constantFrom('name', 'email'),
        fc.constantFrom('phone', 'status'),
        (customerData, firstColumn, secondColumn) => {
          // Ensure we have different columns
          if (firstColumn === secondColumn) return

          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns
            }
          })

          // Sort first column to descending
          wrapper.vm.sortByColumn(firstColumn)
          wrapper.vm.sortByColumn(firstColumn)
          expect(wrapper.vm.internalSortDirection).toBe('desc')

          // Switch to second column - should reset to ascending
          wrapper.vm.sortByColumn(secondColumn)
          expect(wrapper.vm.internalSortBy).toBe(secondColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should emit sort-change event with correct parameters', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            name: fc.string({ minLength: 3, maxLength: 20 }),
            email: fc.emailAddress(),
            phone: fc.string({ minLength: 10, maxLength: 15 }),
            status: fc.constantFrom('active', 'suspended', 'inactive'),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 3, maxLength: 10 }
        ),
        fc.constantFrom('name', 'email', 'phone', 'status'),
        (customerData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns
            }
          })

          // Sort column
          wrapper.vm.sortByColumn(sortColumn)

          // Check emitted events
          const sortChangeEvents = wrapper.emitted('sort-change')
          expect(sortChangeEvents).toBeDefined()
          expect(sortChangeEvents!.length).toBe(1)

          const eventData = sortChangeEvents![0][0] as { column: string; direction: string }
          expect(eventData.column).toBe(sortColumn)
          expect(eventData.direction).toBe('asc')

          // Sort again to test descending
          wrapper.vm.sortByColumn(sortColumn)
          
          const sortChangeEvents2 = wrapper.emitted('sort-change')
          expect(sortChangeEvents2!.length).toBe(2)

          const eventData2 = sortChangeEvents2![1][0] as { column: string; direction: string }
          expect(eventData2.column).toBe(sortColumn)
          expect(eventData2.direction).toBe('desc')
        }
      ),
      { numRuns: 50 }
    )
  })
})