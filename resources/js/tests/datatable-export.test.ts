import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/common/DataTable.vue'
import fc from 'fast-check'
import type { Customer } from '@/types'

// Feature: vue-frontend-enhancement, Property 8: Export Data Consistency

describe('DataTable Export Data Consistency', () => {
  const mockCustomerColumns = [
    { key: 'name', title: 'Name', sortable: true },
    { key: 'email', title: 'Email', sortable: true },
    { key: 'phone', title: 'Phone', sortable: true },
    { key: 'status', title: 'Status', sortable: true }
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should export exactly the same data as displayed in filtered view', () => {
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
          { minLength: 5, maxLength: 20 }
        ),
        fc.string({ minLength: 2, maxLength: 8 }),
        (customerData, searchQuery) => {
          // Ensure at least one item matches search
          if (customerData.length > 0) {
            customerData[0].name = `test${searchQuery}name`
          }

          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns,
              showSearch: true,
              showExport: true
            }
          })

          // Apply search filter
          wrapper.vm.searchQuery = searchQuery
          wrapper.vm.handleSearch()

          // Get filtered data that should be displayed
          const filteredData = wrapper.vm.filteredData

          // Trigger export
          wrapper.vm.exportData()

          // Check emitted export event
          const exportEvents = wrapper.emitted('export')
          expect(exportEvents).toBeDefined()
          expect(exportEvents!.length).toBe(1)

          const exportData = exportEvents![0][0] as { data: Customer[]; selected: Customer[] }
          
          // Exported data should match exactly the filtered data
          expect(exportData.data.length).toBe(filteredData.length)
          
          // Each item in export should match corresponding item in filtered view
          exportData.data.forEach((exportItem, index) => {
            const filteredItem = filteredData[index]
            expect(exportItem.id).toBe(filteredItem.id)
            expect(exportItem.name).toBe(filteredItem.name)
            expect(exportItem.email).toBe(filteredItem.email)
            expect(exportItem.phone).toBe(filteredItem.phone)
            expect(exportItem.status).toBe(filteredItem.status)
          })
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should export data in the same order as displayed after sorting', () => {
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
          { minLength: 5, maxLength: 15 }
        ),
        fc.constantFrom('name', 'email', 'phone', 'status'),
        (customerData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns,
              showExport: true
            }
          })

          // Apply sorting
          wrapper.vm.sortByColumn(sortColumn)
          wrapper.vm.sortByColumn(sortColumn) // Second click for descending

          // Get sorted data
          const sortedData = wrapper.vm.filteredData

          // Trigger export
          wrapper.vm.exportData()

          // Check emitted export event
          const exportEvents = wrapper.emitted('export')
          expect(exportEvents).toBeDefined()

          const exportData = exportEvents![0][0] as { data: Customer[]; selected: Customer[] }
          
          // Exported data should be in the same order as sorted display
          expect(exportData.data.length).toBe(sortedData.length)
          
          exportData.data.forEach((exportItem, index) => {
            const sortedItem = sortedData[index]
            expect(exportItem.id).toBe(sortedItem.id)
            expect(exportItem[sortColumn]).toBe(sortedItem[sortColumn])
          })

          // Verify the exported data maintains sort order
          for (let i = 1; i < exportData.data.length; i++) {
            const prevValue = String(exportData.data[i - 1][sortColumn]).toLowerCase()
            const currentValue = String(exportData.data[i][sortColumn]).toLowerCase()
            // Should be in descending order (second click)
            expect(prevValue.localeCompare(currentValue)).toBeGreaterThanOrEqual(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should export only selected data when rows are selected', () => {
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
          { minLength: 5, maxLength: 15 }
        ),
        fc.integer({ min: 1, max: 3 }),
        (customerData, numToSelect) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns,
              showExport: true,
              selectable: true
            }
          })

          // Select some rows
          const itemsToSelect = customerData.slice(0, Math.min(numToSelect, customerData.length))
          itemsToSelect.forEach(item => {
            wrapper.vm.toggleRowSelection(item)
          })

          // Verify selection
          expect(wrapper.vm.selectedRows.length).toBe(itemsToSelect.length)

          // Trigger export
          wrapper.vm.exportData()

          // Check emitted export event
          const exportEvents = wrapper.emitted('export')
          expect(exportEvents).toBeDefined()

          const exportData = exportEvents![0][0] as { data: Customer[]; selected: Customer[] }
          
          // Export should include both all data and selected data
          expect(exportData.data.length).toBe(customerData.length)
          expect(exportData.selected.length).toBe(itemsToSelect.length)
          
          // Selected data should match the items we selected
          exportData.selected.forEach(selectedItem => {
            const originalItem = itemsToSelect.find(item => item.id === selectedItem.id)
            expect(originalItem).toBeDefined()
            expect(selectedItem.name).toBe(originalItem!.name)
            expect(selectedItem.email).toBe(originalItem!.email)
          })
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should export filtered and sorted data consistently', () => {
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
          { minLength: 10, maxLength: 20 }
        ),
        fc.string({ minLength: 2, maxLength: 8 }),
        fc.constantFrom('name', 'email', 'phone', 'status'),
        (customerData, searchQuery, sortColumn) => {
          // Ensure at least one item matches search
          if (customerData.length > 0) {
            customerData[0].name = `test${searchQuery}name`
          }

          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns,
              showSearch: true,
              showExport: true
            }
          })

          // Apply search filter
          wrapper.vm.searchQuery = searchQuery
          wrapper.vm.handleSearch()

          // Apply sorting
          wrapper.vm.sortByColumn(sortColumn)

          // Get the final filtered and sorted data
          const finalData = wrapper.vm.filteredData

          // Trigger export
          wrapper.vm.exportData()

          // Check emitted export event
          const exportEvents = wrapper.emitted('export')
          expect(exportEvents).toBeDefined()

          const exportData = exportEvents![0][0] as { data: Customer[]; selected: Customer[] }
          
          // Exported data should match the filtered and sorted display exactly
          expect(exportData.data.length).toBe(finalData.length)
          
          // Verify each item matches
          exportData.data.forEach((exportItem, index) => {
            const displayItem = finalData[index]
            expect(exportItem.id).toBe(displayItem.id)
            expect(exportItem.name).toBe(displayItem.name)
            expect(exportItem.email).toBe(displayItem.email)
          })

          // Verify all exported items match search criteria
          exportData.data.forEach((item: Customer) => {
            const matchFound = Object.values(item).some(value =>
              String(value).toLowerCase().includes(searchQuery.toLowerCase())
            )
            expect(matchFound).toBe(true)
          })

          // Verify exported data maintains sort order
          for (let i = 1; i < exportData.data.length; i++) {
            const prevValue = String(exportData.data[i - 1][sortColumn]).toLowerCase()
            const currentValue = String(exportData.data[i][sortColumn]).toLowerCase()
            expect(prevValue.localeCompare(currentValue)).toBeLessThanOrEqual(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should export paginated data correctly across all pages', () => {
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
          { minLength: 15, maxLength: 30 } // Ensure enough data for pagination
        ),
        fc.integer({ min: 5, max: 10 }),
        (customerData, pageSize) => {
          const wrapper = mount(DataTable, {
            props: {
              data: customerData,
              columns: mockCustomerColumns,
              showExport: true,
              paginated: true,
              pageSize: pageSize
            }
          })

          // Navigate to a specific page (not the first one)
          const totalPages = Math.ceil(customerData.length / pageSize)
          if (totalPages > 1) {
            wrapper.vm.goToPage(2)
          }

          // Get all filtered data (not just current page)
          const allFilteredData = wrapper.vm.filteredData

          // Trigger export
          wrapper.vm.exportData()

          // Check emitted export event
          const exportEvents = wrapper.emitted('export')
          expect(exportEvents).toBeDefined()

          const exportData = exportEvents![0][0] as { data: Customer[]; selected: Customer[] }
          
          // Export should include ALL data, not just current page
          expect(exportData.data.length).toBe(allFilteredData.length)
          expect(exportData.data.length).toBe(customerData.length)
          
          // Verify all original data is included in export
          customerData.forEach(originalItem => {
            const exportedItem = exportData.data.find(item => item.id === originalItem.id)
            expect(exportedItem).toBeDefined()
            expect(exportedItem!.name).toBe(originalItem.name)
          })
        }
      ),
      { numRuns: 50 }
    )
  })
})