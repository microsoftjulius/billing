import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/common/DataTable.vue'
import fc from 'fast-check'
import type { Customer } from '@/types'

// Feature: vue-frontend-enhancement, Property 7: DataTable State Persistence

describe('DataTable State Persistence', () => {
  const mockCustomerColumns = [
    { key: 'name', title: 'Name', sortable: true },
    { key: 'email', title: 'Email', sortable: true },
    { key: 'phone', title: 'Phone', sortable: true },
    { key: 'status', title: 'Status', sortable: true }
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should maintain search state when data changes', async () => {
    // Test with a simple, controlled case first
    const initialData = [
      {
        id: '1',
        name: 'John Doe',
        email: 'john@example.com',
        phone: '1234567890',
        status: 'active',
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-01T00:00:00.000Z'
      }
    ];
    
    const wrapper = mount(DataTable, {
      props: {
        data: initialData,
        columns: mockCustomerColumns,
        showSearch: true
      }
    });

    // Set search query
    const searchQuery = 'test';
    wrapper.vm.searchQuery = searchQuery;

    // Verify search state is set
    expect(wrapper.vm.searchQuery).toBe(searchQuery);

    // Update data prop with new data that contains the search query
    const newData = [...initialData, {
      id: 'new-id',
      name: 'TestItem',
      email: 'test@example.com',
      phone: '9999999999',
      status: 'active',
      created_at: '2024-01-01T00:00:00.000Z',
      updated_at: '2024-01-01T00:00:00.000Z'
    }];

    wrapper.setProps({ data: newData });
    
    // Force the component to recalculate filtered data
    wrapper.vm.$forceUpdate();
    await wrapper.vm.$nextTick();

    // Search state should be maintained
    expect(wrapper.vm.searchQuery).toBe(searchQuery);

    // Filtered data should include the new item since it contains searchQuery
    const filteredData = wrapper.vm.filteredData;
    console.log('Search query:', wrapper.vm.searchQuery);
    console.log('New data:', newData);
    console.log('Filtered data:', filteredData);
    
    // Debug the filtering logic
    const query = wrapper.vm.searchQuery.toLowerCase();
    newData.forEach((item, index) => {
      console.log(`Item ${index}:`, item);
      const matches = Object.values(item).some(value =>
        String(value).toLowerCase().includes(query)
      );
      console.log(`  Matches search: ${matches}`);
    });
    
    const newItemInResults = filteredData.some(item => item.id === 'new-id');
    expect(newItemInResults).toBe(true);
    
    // Now test with property-based testing for more robust validation
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.uuid(),
            name: fc.string({ minLength: 3, maxLength: 20 }).filter(s => s.trim().length > 0),
            email: fc.emailAddress(),
            phone: fc.string({ minLength: 10, maxLength: 15 }),
            status: fc.constantFrom('active', 'suspended', 'inactive'),
            created_at: fc.constant('2024-01-01T00:00:00.000Z'),
            updated_at: fc.constant('2024-01-01T00:00:00.000Z')
          }),
          { minLength: 1, maxLength: 10 }
        ),
        fc.string({ minLength: 1, maxLength: 5 }).filter(s => /^[a-zA-Z0-9]+$/.test(s)), // Only alphanumeric
        async (initialData, searchQuery) => {
          const wrapper = mount(DataTable, {
            props: {
              data: initialData,
              columns: mockCustomerColumns,
              showSearch: true
            }
          })

          // Set search query
          wrapper.vm.searchQuery = searchQuery
          
          // Verify search state is set
          expect(wrapper.vm.searchQuery).toBe(searchQuery)

          // Create new data with an item that definitely contains the search query
          const newData = [...initialData, {
            id: 'test-new-id',
            name: `Contains${searchQuery}Here`,
            email: `${searchQuery}@example.com`,
            phone: '9999999999',
            status: 'active' as const,
            created_at: '2024-01-01T00:00:00.000Z',
            updated_at: '2024-01-01T00:00:00.000Z'
          }]

          wrapper.setProps({ data: newData })
          await wrapper.vm.$nextTick()

          // Search state should be maintained
          expect(wrapper.vm.searchQuery).toBe(searchQuery)

          // The new item should be found in filtered results
          const filteredData = wrapper.vm.filteredData
          const newItemInResults = filteredData.some(item => item.id === 'test-new-id')
          expect(newItemInResults).toBe(true)
        }
      ),
      { numRuns: 20 }
    )
  })

  it('should maintain sort state when data changes', () => {
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
        (initialData, sortColumn) => {
          const wrapper = mount(DataTable, {
            props: {
              data: initialData,
              columns: mockCustomerColumns
            }
          })

          // Set sort state
          wrapper.vm.sortByColumn(sortColumn)
          wrapper.vm.sortByColumn(sortColumn) // Second click for descending

          // Verify sort state
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('desc')

          // Update data prop with new data
          const newData = [...initialData, {
            id: 'new-id',
            name: 'ZZZ New Item',
            email: 'zzz@example.com',
            phone: '9999999999',
            status: 'active' as const,
            created_at: '2024-01-01T00:00:00.000Z',
            updated_at: '2024-01-01T00:00:00.000Z'
          }]

          wrapper.setProps({ data: newData })

          // Sort state should be maintained
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('desc')

          // Data should still be sorted according to the maintained state
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

  it('should maintain pagination state when data changes', () => {
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
          { minLength: 25, maxLength: 50 } // Ensure enough data for pagination
        ),
        fc.integer({ min: 2, max: 5 }),
        fc.integer({ min: 5, max: 15 }),
        (initialData, targetPage, pageSize) => {
          const wrapper = mount(DataTable, {
            props: {
              data: initialData,
              columns: mockCustomerColumns,
              pageSize: pageSize,
              paginated: true
            }
          })

          // Set pagination state
          wrapper.vm.internalPageSize = pageSize
          const maxPage = Math.ceil(initialData.length / pageSize)
          const validTargetPage = Math.min(targetPage, maxPage)
          
          wrapper.vm.goToPage(validTargetPage)

          // Verify pagination state
          expect(wrapper.vm.internalPage).toBe(validTargetPage)
          expect(wrapper.vm.internalPageSize).toBe(pageSize)

          // Update data prop with new data
          const newData = [...initialData, {
            id: 'new-id',
            name: 'New Item',
            email: 'new@example.com',
            phone: '9999999999',
            status: 'active' as const,
            created_at: '2024-01-01T00:00:00.000Z',
            updated_at: '2024-01-01T00:00:00.000Z'
          }]

          wrapper.setProps({ data: newData })

          // Page size should be maintained
          expect(wrapper.vm.internalPageSize).toBe(pageSize)
          
          // Current page should be maintained if still valid, otherwise adjusted
          const newMaxPage = Math.ceil(newData.length / pageSize)
          const expectedPage = Math.min(validTargetPage, newMaxPage)
          expect(wrapper.vm.internalPage).toBe(expectedPage)
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should maintain combined search and sort state', () => {
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
        (initialData, searchQuery, sortColumn) => {
          // Ensure at least one item matches search
          if (initialData.length > 0) {
            initialData[0].name = `test${searchQuery}name`
          }

          const wrapper = mount(DataTable, {
            props: {
              data: initialData,
              columns: mockCustomerColumns,
              showSearch: true
            }
          })

          // Set search state
          wrapper.vm.searchQuery = searchQuery
          wrapper.vm.handleSearch()

          // Set sort state
          wrapper.vm.sortByColumn(sortColumn)

          // Verify initial state
          expect(wrapper.vm.searchQuery).toBe(searchQuery)
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')

          // Update data prop
          const newData = [...initialData, {
            id: 'new-id',
            name: `New${searchQuery}Item`,
            email: 'new@example.com',
            phone: '9999999999',
            status: 'active' as const,
            created_at: '2024-01-01T00:00:00.000Z',
            updated_at: '2024-01-01T00:00:00.000Z'
          }]

          wrapper.setProps({ data: newData })

          // Both search and sort state should be maintained
          expect(wrapper.vm.searchQuery).toBe(searchQuery)
          expect(wrapper.vm.internalSortBy).toBe(sortColumn)
          expect(wrapper.vm.internalSortDirection).toBe('asc')

          // Filtered and sorted data should reflect both states
          const filteredData = wrapper.vm.filteredData
          
          // All items should match search query
          filteredData.forEach((item: Customer) => {
            const matchFound = Object.values(item).some(value =>
              String(value).toLowerCase().includes(searchQuery.toLowerCase())
            )
            expect(matchFound).toBe(true)
          })

          // Data should be sorted
          for (let i = 1; i < filteredData.length; i++) {
            const prevValue = String(filteredData[i - 1][sortColumn]).toLowerCase()
            const currentValue = String(filteredData[i][sortColumn]).toLowerCase()
            expect(prevValue.localeCompare(currentValue)).toBeLessThanOrEqual(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })

  it('should reset selection state when data changes', () => {
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
          { minLength: 2, maxLength: 15 }
        ),
        (initialData) => {
          const wrapper = mount(DataTable, {
            props: {
              data: initialData,
              columns: mockCustomerColumns,
              selectable: true
            }
          })

          // Select some rows (ensure we have data to select)
          if (initialData.length > 0) {
            wrapper.vm.toggleRowSelection(initialData[0])
            if (initialData.length > 1) {
              wrapper.vm.toggleRowSelection(initialData[1])
            }
            
            // Verify selection state exists
            expect(wrapper.vm.selectedRows.length).toBeGreaterThan(0)

            // Update data prop
            const newData = initialData.map(item => ({ ...item, name: `Updated ${item.name}` }))
            wrapper.setProps({ data: newData })

            // Selection should be reset when data changes
            expect(wrapper.vm.selectedRows.length).toBe(0)
            expect(wrapper.vm.allSelected).toBe(false)
          } else {
            // If no data, just verify no selection exists
            expect(wrapper.vm.selectedRows.length).toBe(0)
          }
        }
      ),
      { numRuns: 50 }
    )
  })
})