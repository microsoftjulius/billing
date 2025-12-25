<template>
    <div class="data-table-wrapper" :class="{ 'has-footer': showFooter }">
        <!-- Table Header (Above table) -->
        <div v-if="showHeader" class="table-header">
            <div class="header-left">
                <slot name="header-left">
                    <h3 v-if="title" class="table-title">{{ title }}</h3>
                    <span v-if="description" class="table-description">{{ description }}</span>
                </slot>
            </div>

            <div class="header-right">
                <slot name="header-right">
                    <div v-if="showSearch" class="table-search">
                        <div class="search-wrapper">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                v-model="searchQuery"
                                :placeholder="searchPlaceholder"
                                class="search-input"
                                @input="handleSearch"
                            />
                            <button
                                v-if="searchQuery"
                                class="clear-search"
                                @click="clearSearch"
                                title="Clear search"
                            >
                                <svg class="clear-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="showFilters" class="table-filters">
                        <button
                            class="filter-btn"
                            @click="toggleFilterPanel"
                            :class="{ active: filterPanelOpen }"
                            title="Filters"
                        >
                            <svg class="filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
                        </button>

                        <transition name="slide-fade">
                            <div v-if="filterPanelOpen" class="filter-panel" v-click-outside="closeFilterPanel">
                                <div class="filter-panel-header">
                                    <h4>Filters</h4>
                                    <button class="filter-clear-all" @click="clearAllFilters">Clear All</button>
                                </div>

                                <div class="filter-options">
                                    <slot name="filters">
                                        <div v-for="filter in filters" :key="filter.key" class="filter-option">
                                            <label class="filter-label">{{ filter.label }}</label>
                                            <select
                                                v-if="filter.type === 'select'"
                                                v-model="filterValues[filter.key]"
                                                class="filter-select"
                                                @change="applyFilters"
                                            >
                                                <option value="">All</option>
                                                <option v-for="option in filter.options" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>

                                            <input
                                                v-else-if="filter.type === 'date'"
                                                type="date"
                                                v-model="filterValues[filter.key]"
                                                class="filter-input"
                                                @change="applyFilters"
                                            />

                                            <input
                                                v-else
                                                type="text"
                                                v-model="filterValues[filter.key]"
                                                :placeholder="filter.placeholder"
                                                class="filter-input"
                                                @input="debouncedApplyFilters"
                                            />
                                        </div>
                                    </slot>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <div class="table-actions">
                        <slot name="actions">
                            <button
                                v-if="showExport"
                                class="action-btn export-btn"
                                @click="exportData"
                                title="Export data"
                            >
                                <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </button>

                            <button
                                v-if="showRefresh"
                                class="action-btn refresh-btn"
                                @click="refreshData"
                                :disabled="loading"
                                title="Refresh data"
                            >
                                <svg
                                    class="action-icon"
                                    :class="{ 'animate-spin': loading }"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </slot>
                    </div>
                </slot>
            </div>
        </div>

        <!-- Filter Panel (if separate) -->
        <div v-if="showFilterBar && activeFilterCount > 0" class="active-filters-bar">
            <div class="active-filters">
                <span class="filters-label">Active filters:</span>
                <div class="filter-chips">
                    <div v-for="(filter, key) in activeFilters" :key="key" class="filter-chip">
                        <span class="chip-label">{{ getFilterLabel(key) }}:</span>
                        <span class="chip-value">{{ filter.value }}</span>
                        <button class="chip-remove" @click="removeFilter(key)">
                            <svg class="remove-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button class="clear-all-btn" @click="clearAllFilters">Clear all</button>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container" :class="{ 'with-border': bordered, 'striped': striped }">
            <div class="table-responsive" ref="tableContainer">
                <table class="data-table">
                    <!-- Table Head -->
                    <thead>
                    <tr>
                        <!-- Checkbox column -->
                        <th v-if="selectable" class="checkbox-column">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleSelectAll"
                                class="select-all-checkbox"
                            />
                        </th>

                        <!-- Data columns -->
                        <th
                            v-for="column in visibleColumns"
                            :key="column.key"
                            :class="[
                  column.class,
                  column.sortable ? 'sortable' : '',
                  sortBy === column.key ? `sorted ${sortDirection}` : ''
                ]"
                            :style="{ width: column.width, minWidth: column.minWidth }"
                            @click="column.sortable ? sortByColumn(column.key) : null"
                        >
                            <div class="column-header">
                                <span class="column-title">{{ column.title }}</span>
                                <span v-if="column.required" class="required-mark">*</span>

                                <div v-if="column.sortable" class="sort-indicator">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Column filter -->
                            <div v-if="column.filterable" class="column-filter">
                                <input
                                    v-if="column.filterType === 'text'"
                                    type="text"
                                    v-model="columnFilters[column.key]"
                                    :placeholder="`Filter ${column.title.toLowerCase()}...`"
                                    class="filter-input"
                                    @input="debouncedFilterColumn(column.key)"
                                />

                                <select
                                    v-else-if="column.filterType === 'select'"
                                    v-model="columnFilters[column.key]"
                                    class="filter-select"
                                    @change="filterColumn(column.key)"
                                >
                                    <option value="">All</option>
                                    <option
                                        v-for="option in column.filterOptions || getUniqueValues(column.key)"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label || option }}
                                    </option>
                                </select>
                            </div>
                        </th>

                        <!-- Actions column -->
                        <th v-if="showActions" class="actions-column">Actions</th>
                    </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody>
                    <!-- Loading state -->
                    <tr v-if="loading">
                        <td :colspan="totalColumns" class="loading-row">
                            <div class="loading-cell">
                                <div class="loading-spinner"></div>
                                <span class="loading-text">{{ loadingText || 'Loading data...' }}</span>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-else-if="paginatedData.length === 0">
                        <td :colspan="totalColumns" class="empty-row">
                            <div class="empty-cell">
                                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <p class="empty-text">{{ emptyText || 'No data available' }}</p>
                                <button v-if="showReset && (searchQuery || hasActiveFilters)" class="reset-btn" @click="resetTable">
                                    Reset filters
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Data rows -->
                    <template v-else>
                        <tr
                            v-for="(row, index) in paginatedData"
                            :key="rowKey ? row[rowKey] : index"
                            :class="[
                  rowClass ? rowClass(row) : '',
                  selectedRows.includes(getRowId(row)) ? 'selected' : '',
                  { 'clickable': rowClickable }
                ]"
                            @click="rowClickable ? handleRowClick(row) : null"
                        >
                            <!-- Checkbox cell -->
                            <td v-if="selectable" class="checkbox-cell">
                                <input
                                    type="checkbox"
                                    :checked="isRowSelected(row)"
                                    @change="toggleRowSelection(row)"
                                    @click.stop
                                    class="row-checkbox"
                                />
                            </td>

                            <!-- Data cells -->
                            <td
                                v-for="column in visibleColumns"
                                :key="column.key"
                                :class="column.cellClass ? column.cellClass(row) : ''"
                            >
                                <slot :name="`cell(${column.key})`" :row="row" :value="row[column.key]">
                                    <template v-if="column.format">
                                        {{ formatCell(row[column.key], column.format, row) }}
                                    </template>
                                    <template v-else-if="column.render">
                                        <component
                                            :is="column.render"
                                            :row="row"
                                            :value="row[column.key]"
                                        />
                                    </template>
                                    <template v-else>
                                        {{ row[column.key] }}
                                    </template>
                                </slot>
                            </td>

                            <!-- Actions cell -->
                            <td v-if="showActions" class="actions-cell">
                                <div class="action-buttons">
                                    <slot name="actions-cell" :row="row" :index="index">
                                        <button
                                            v-if="showViewAction"
                                            class="action-btn view-btn"
                                            @click.stop="$emit('view', row)"
                                            title="View"
                                        >
                                            <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <button
                                            v-if="showEditAction"
                                            class="action-btn edit-btn"
                                            @click.stop="$emit('edit', row)"
                                            title="Edit"
                                        >
                                            <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <button
                                            v-if="showDeleteAction"
                                            class="action-btn delete-btn"
                                            @click.stop="$emit('delete', row)"
                                            title="Delete"
                                        >
                                            <svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </slot>
                                </div>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>

            <!-- Row Count & Selection Info -->
            <div v-if="showSelectionInfo && selectedRows.length > 0" class="selection-info">
                <span class="selection-count">{{ selectedRows.length }} item(s) selected</span>
                <div class="selection-actions">
                    <button class="selection-action" @click="clearSelection">Clear</button>
                    <slot name="bulk-actions" :selected-rows="selectedRowsData"></slot>
                </div>
            </div>
        </div>

        <!-- Table Footer (Pagination & Info) -->
        <div v-if="showFooter" class="table-footer">
            <div class="footer-left">
                <div v-if="showPageSize" class="page-size-selector">
                    <span class="page-size-label">Show</span>
                    <select v-model="pageSize" class="page-size-select" @change="handlePageSizeChange">
                        <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }}</option>
                    </select>
                    <span class="page-size-label">entries</span>
                </div>

                <div class="row-count">
                    Showing {{ showingFrom }} to {{ showingTo }} of {{ totalRows }} entries
                    <span v-if="filteredRows !== totalRows" class="filtered-count">
            (filtered from {{ totalRows }} total entries)
          </span>
                </div>
            </div>

            <div v-if="showPagination" class="pagination">
                <button
                    class="pagination-btn first"
                    @click="goToPage(1)"
                    :disabled="currentPage === 1"
                    title="First page"
                >
                    <svg class="pagination-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>

                <button
                    class="pagination-btn prev"
                    @click="goToPage(currentPage - 1)"
                    :disabled="currentPage === 1"
                    title="Previous page"
                >
                    <svg class="pagination-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="page-numbers">
                    <template v-for="page in visiblePages">
                        <button
                            v-if="page === '...'"
                            :key="`ellipsis-${page}`"
                            class="pagination-ellipsis"
                            disabled
                        >
                            ...
                        </button>

                        <button
                            v-else
                            :key="page"
                            class="page-number"
                            :class="{ active: page === currentPage }"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                    </template>
                </div>

                <button
                    class="pagination-btn next"
                    @click="goToPage(currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    title="Next page"
                >
                    <svg class="pagination-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <button
                    class="pagination-btn last"
                    @click="goToPage(totalPages)"
                    :disabled="currentPage === totalPages"
                    title="Last page"
                >
                    <svg class="pagination-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { debounce } from 'lodash'

export default {
    name: 'DataTable',
    directives: {
        'click-outside': {
            bind(el, binding, vnode) {
                el.clickOutsideEvent = function(event) {
                    if (!(el === event.target || el.contains(event.target))) {
                        vnode.context[binding.expression](event)
                    }
                }
                document.body.addEventListener('click', el.clickOutsideEvent)
            },
            unbind(el) {
                document.body.removeEventListener('click', el.clickOutsideEvent)
            }
        }
    },
    props: {
        // Data
        data: {
            type: Array,
            default: () => []
        },
        columns: {
            type: Array,
            required: true
        },
        rowKey: {
            type: String,
            default: 'id'
        },

        // Display
        title: String,
        description: String,
        bordered: {
            type: Boolean,
            default: true
        },
        striped: {
            type: Boolean,
            default: true
        },
        hover: {
            type: Boolean,
            default: true
        },

        // Features
        selectable: Boolean,
        rowClickable: Boolean,
        showActions: Boolean,
        showViewAction: Boolean,
        showEditAction: Boolean,
        showDeleteAction: Boolean,
        showHeader: {
            type: Boolean,
            default: true
        },
        showFooter: {
            type: Boolean,
            default: true
        },
        showSearch: Boolean,
        showFilters: Boolean,
        showFilterBar: Boolean,
        showExport: Boolean,
        showRefresh: Boolean,
        showReset: Boolean,
        showPagination: {
            type: Boolean,
            default: true
        },
        showPageSize: Boolean,
        showSelectionInfo: Boolean,

        // States
        loading: Boolean,
        loadingText: String,
        emptyText: String,

        // Pagination
        paginated: {
            type: Boolean,
            default: true
        },
        pageSize: {
            type: Number,
            default: 10
        },
        pageSizeOptions: {
            type: Array,
            default: () => [10, 25, 50, 100]
        },
        currentPage: {
            type: Number,
            default: 1
        },

        // Filtering
        filters: {
            type: Array,
            default: () => []
        },
        searchPlaceholder: {
            type: String,
            default: 'Search...'
        },

        // Sorting
        sortBy: String,
        sortDirection: {
            type: String,
            default: 'asc',
            validator: value => ['asc', 'desc'].includes(value)
        },

        // Customization
        rowClass: Function,
        totalRows: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            // Internal states
            internalPage: this.currentPage,
            internalPageSize: this.pageSize,
            internalSortBy: this.sortBy,
            internalSortDirection: this.sortDirection,

            // Filtering
            searchQuery: '',
            filterValues: {},
            columnFilters: {},
            filterPanelOpen: false,

            // Selection
            selectedRows: [],
            allSelected: false,

            // Column visibility
            hiddenColumns: []
        }
    },
    computed: {
        visibleColumns() {
            return this.columns.filter(col => !this.hiddenColumns.includes(col.key))
        },

        totalColumns() {
            let count = this.visibleColumns.length
            if (this.selectable) count++
            if (this.showActions) count++
            return count
        },

        // Filtering & Sorting
        filteredData() {
            let data = [...this.data]

            // Apply search
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase()
                data = data.filter(row => {
                    return Object.values(row).some(value =>
                        String(value).toLowerCase().includes(query)
                    )
                })
            }

            // Apply column filters
            Object.keys(this.columnFilters).forEach(key => {
                const filterValue = this.columnFilters[key]
                if (filterValue) {
                    data = data.filter(row => {
                        const cellValue = String(row[key]).toLowerCase()
                        return cellValue.includes(filterValue.toLowerCase())
                    })
                }
            })

            // Apply custom filters
            Object.keys(this.filterValues).forEach(key => {
                const filterValue = this.filterValues[key]
                if (filterValue) {
                    data = data.filter(row => {
                        const rowValue = row[key]
                        return String(rowValue) === String(filterValue)
                    })
                }
            })

            // Apply sorting
            if (this.internalSortBy) {
                data.sort((a, b) => {
                    let aValue = a[this.internalSortBy]
                    let bValue = b[this.internalSortBy]

                    // Handle null/undefined values
                    if (aValue == null) aValue = ''
                    if (bValue == null) bValue = ''

                    // Handle different types
                    if (typeof aValue === 'string' && typeof bValue === 'string') {
                        return this.internalSortDirection === 'asc'
                            ? aValue.localeCompare(bValue)
                            : bValue.localeCompare(aValue)
                    }

                    // Numeric comparison
                    return this.internalSortDirection === 'asc'
                        ? aValue - bValue
                        : bValue - aValue
                })
            }

            return data
        },

        filteredRows() {
            return this.filteredData.length
        },

        totalRows() {
            return this.totalRows !== null ? this.totalRows : this.data.length
        },

        // Pagination
        totalPages() {
            if (!this.paginated) return 1
            return Math.ceil(this.filteredRows / this.internalPageSize)
        },

        paginatedData() {
            if (!this.paginated || this.filteredRows <= this.internalPageSize) {
                return this.filteredData
            }

            const start = (this.internalPage - 1) * this.internalPageSize
            const end = start + this.internalPageSize
            return this.filteredData.slice(start, end)
        },

        showingFrom() {
            if (!this.paginated) return 1
            return (this.internalPage - 1) * this.internalPageSize + 1
        },

        showingTo() {
            if (!this.paginated) return this.filteredRows
            const to = this.internalPage * this.internalPageSize
            return Math.min(to, this.filteredRows)
        },

        visiblePages() {
            const pages = []
            const maxVisible = 5
            let start = Math.max(1, this.internalPage - Math.floor(maxVisible / 2))
            let end = Math.min(this.totalPages, start + maxVisible - 1)

            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1)
            }

            if (start > 1) {
                pages.push(1)
                if (start > 2) pages.push('...')
            }

            for (let i = start; i <= end; i++) {
                pages.push(i)
            }

            if (end < this.totalPages) {
                if (end < this.totalPages - 1) pages.push('...')
                pages.push(this.totalPages)
            }

            return pages
        },

        // Selection
        selectedRowsData() {
            return this.data.filter(row =>
                this.selectedRows.includes(this.getRowId(row))
            )
        },

        // Filters
        activeFilters() {
            const filters = {}

            // Column filters
            Object.keys(this.columnFilters).forEach(key => {
                if (this.columnFilters[key]) {
                    const column = this.columns.find(col => col.key === key)
                    filters[key] = {
                        key,
                        label: column?.title || key,
                        value: this.columnFilters[key]
                    }
                }
            })

            // Custom filters
            Object.keys(this.filterValues).forEach(key => {
                if (this.filterValues[key]) {
                    const filter = this.filters.find(f => f.key === key)
                    filters[key] = {
                        key,
                        label: filter?.label || key,
                        value: this.filterValues[key]
                    }
                }
            })

            return filters
        },

        activeFilterCount() {
            return Object.keys(this.activeFilters).length
        }
    },
    watch: {
        data: {
            handler() {
                this.resetSelection()
            },
            deep: true
        },

        currentPage(newVal) {
            this.internalPage = newVal
        },

        pageSize(newVal) {
            this.internalPageSize = newVal
        },

        sortBy(newVal) {
            this.internalSortBy = newVal
        },

        sortDirection(newVal) {
            this.internalSortDirection = newVal
        }
    },
    created() {
        // Initialize filter values
        this.filters.forEach(filter => {
            this.$set(this.filterValues, filter.key, '')
        })

        // Initialize column filters
        this.columns.forEach(column => {
            if (column.filterable) {
                this.$set(this.columnFilters, column.key, '')
            }
        })

        // Setup debounced functions
        this.debouncedApplyFilters = debounce(this.applyFilters, 300)
        this.debouncedFilterColumn = debounce(this.filterColumn, 300)
    },
    methods: {
        // Row handling
        getRowId(row) {
            return this.rowKey ? row[this.rowKey] : JSON.stringify(row)
        },

        handleRowClick(row) {
            this.$emit('row-click', row)
        },

        // Selection
        isRowSelected(row) {
            return this.selectedRows.includes(this.getRowId(row))
        },

        toggleRowSelection(row) {
            const rowId = this.getRowId(row)
            const index = this.selectedRows.indexOf(rowId)

            if (index === -1) {
                this.selectedRows.push(rowId)
            } else {
                this.selectedRows.splice(index, 1)
            }

            this.updateAllSelected()
            this.$emit('selection-change', this.selectedRowsData)
        },

        toggleSelectAll() {
            if (this.allSelected) {
                this.clearSelection()
            } else {
                this.selectAll()
            }
        },

        selectAll() {
            this.selectedRows = this.filteredData.map(row => this.getRowId(row))
            this.allSelected = true
            this.$emit('selection-change', this.selectedRowsData)
        },

        clearSelection() {
            this.selectedRows = []
            this.allSelected = false
            this.$emit('selection-change', [])
        },

        resetSelection() {
            this.selectedRows = []
            this.allSelected = false
        },

        updateAllSelected() {
            this.allSelected = this.filteredData.length > 0 &&
                this.filteredData.every(row => this.isRowSelected(row))
        },

        // Sorting
        sortByColumn(columnKey) {
            if (this.internalSortBy === columnKey) {
                this.internalSortDirection = this.internalSortDirection === 'asc' ? 'desc' : 'asc'
            } else {
                this.internalSortBy = columnKey
                this.internalSortDirection = 'asc'
            }

            this.$emit('sort-change', {
                column: columnKey,
                direction: this.internalSortDirection
            })
        },

        // Filtering
        handleSearch() {
            this.$emit('search', this.searchQuery)
        },

        clearSearch() {
            this.searchQuery = ''
            this.$emit('search', '')
        },

        applyFilters() {
            this.internalPage = 1
            this.$emit('filter-change', this.filterValues)
        },

        filterColumn(columnKey) {
            this.internalPage = 1
            this.$emit('column-filter-change', {
                column: columnKey,
                value: this.columnFilters[columnKey]
            })
        },

        removeFilter(filterKey) {
            if (this.columnFilters[filterKey] !== undefined) {
                this.columnFilters[filterKey] = ''
            } else {
                this.filterValues[filterKey] = ''
            }
            this.applyFilters()
        },

        clearAllFilters() {
            // Clear column filters
            Object.keys(this.columnFilters).forEach(key => {
                this.columnFilters[key] = ''
            })

            // Clear custom filters
            Object.keys(this.filterValues).forEach(key => {
                this.filterValues[key] = ''
            })

            // Clear search
            this.searchQuery = ''

            this.applyFilters()
            this.$emit('filters-cleared')
        },

        getFilterLabel(filterKey) {
            const column = this.columns.find(col => col.key === filterKey)
            if (column) return column.title

            const filter = this.filters.find(f => f.key === filterKey)
            return filter?.label || filterKey
        },

        getUniqueValues(columnKey) {
            const values = [...new Set(this.data.map(row => row[columnKey]))]
            return values.filter(value => value != null).sort()
        },

        // Filter panel
        toggleFilterPanel() {
            this.filterPanelOpen = !this.filterPanelOpen
        },

        closeFilterPanel() {
            this.filterPanelOpen = false
        },

        // Pagination
        goToPage(page) {
            if (page < 1 || page > this.totalPages || page === this.internalPage) return

            this.internalPage = page
            this.$emit('page-change', page)
            this.scrollToTop()
        },

        handlePageSizeChange() {
            this.internalPage = 1
            this.$emit('page-size-change', this.internalPageSize)
        },

        // Utility
        formatCell(value, format, row) {
            if (typeof format === 'function') {
                return format(value, row)
            }

            switch (format) {
                case 'date':
                    return new Date(value).toLocaleDateString()
                case 'datetime':
                    return new Date(value).toLocaleString()
                case 'currency':
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD'
                    }).format(value)
                case 'number':
                    return new Intl.NumberFormat().format(value)
                case 'percentage':
                    return `${(value * 100).toFixed(2)}%`
                case 'boolean':
                    return value ? 'Yes' : 'No'
                default:
                    return value
            }
        },

        scrollToTop() {
            if (this.$refs.tableContainer) {
                this.$refs.tableContainer.scrollTop = 0
            }
        },

        resetTable() {
            this.clearAllFilters()
            this.clearSearch()
            this.resetSelection()
            this.internalPage = 1
            this.$emit('reset')
        },

        refreshData() {
            this.$emit('refresh')
        },

        exportData() {
            this.$emit('export', {
                data: this.filteredData,
                selected: this.selectedRowsData
            })
        }
    }
}
</script>

<style scoped>
/* Base styles */
.data-table-wrapper {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Table Header */
.table-header {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.header-left {
    flex: 1;
    min-width: 0;
}

.table-title {
    margin: 0 0 4px;
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.table-description {
    font-size: 0.875rem;
    color: #6b7280;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}

/* Search */
.table-search .search-wrapper {
    position: relative;
    width: 240px;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: #9ca3af;
}

.search-input {
    width: 100%;
    padding: 10px 36px 10px 40px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.clear-search {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: none;
    background: none;
    color: #9ca3af;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.clear-search:hover {
    background: #f3f4f6;
    color: #374151;
}

.clear-icon {
    width: 14px;
    height: 14px;
}

/* Filters */
.table-filters {
    position: relative;
}

.filter-btn {
    position: relative;
    width: 36px;
    height: 36px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.filter-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.filter-icon {
    width: 18px;
    height: 18px;
}

.filter-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid white;
}

.filter-panel {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 300px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    z-index: 100;
    animation: slideDown 0.2s ease;
}

.filter-panel-header {
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.filter-panel-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.filter-clear-all {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.filter-clear-all:hover {
    background: #fee2e2;
}

.filter-options {
    padding: 16px;
    max-height: 300px;
    overflow-y: auto;
}

.filter-option {
    margin-bottom: 16px;
}

.filter-option:last-child {
    margin-bottom: 0;
}

.filter-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.filter-select,
.filter-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Active Filters Bar */
.active-filters-bar {
    padding: 12px 24px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
}

.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.filters-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.875rem;
    border: 1px solid #7dd3fc;
}

.chip-label {
    font-weight: 500;
}

.chip-remove {
    width: 16px;
    height: 16px;
    border: none;
    background: none;
    color: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.7;
    transition: opacity 0.2s ease;
    padding: 0;
    margin-left: 2px;
}

.chip-remove:hover {
    opacity: 1;
}

.remove-icon {
    width: 12px;
    height: 12px;
}

.clear-all-btn {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    padding: 4px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.clear-all-btn:hover {
    background: #f3f4f6;
    color: #374151;
}

/* Table Actions */
.table-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-icon {
    width: 18px;
    height: 18px;
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Table Container */
.table-container {
    overflow: hidden;
}

.table-container.with-border {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.table-responsive {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

/* Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.data-table thead {
    background: #f9fafb;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
    position: relative;
}

.data-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
}

.data-table.striped tbody tr:nth-child(even) {
    background: #f9fafb;
}

.data-table tbody tr:hover {
    background: #f3f4f6;
}

.data-table tbody tr.selected {
    background: #dbeafe;
}

.data-table tbody tr.clickable {
    cursor: pointer;
}

.data-table td {
    padding: 12px 16px;
    font-size: 0.95rem;
    color: #374151;
    vertical-align: middle;
}

/* Checkbox columns */
.checkbox-column,
.checkbox-cell {
    width: 48px;
    padding-right: 0 !important;
}

.select-all-checkbox,
.row-checkbox {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
}

/* Sortable columns */
.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background: #f3f4f6;
}

.column-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.column-title {
    flex: 1;
}

.required-mark {
    color: #ef4444;
}

.sort-indicator {
    display: flex;
    align-items: center;
}

.sort-icon {
    width: 16px;
    height: 16px;
    opacity: 0.5;
    transition: all 0.2s ease;
}

.sortable:hover .sort-icon,
.sorted .sort-icon {
    opacity: 1;
}

.sorted.asc .sort-icon {
    transform: rotate(180deg);
}

/* Column filters */
.column-filter {
    margin-top: 8px;
}

.column-filter .filter-input,
.column-filter .filter-select {
    width: 100%;
    padding: 6px 10px;
    font-size: 0.8rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

/* Actions column */
.actions-column {
    width: 120px;
}

.actions-cell {
    white-space: nowrap;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.actions-cell .action-btn {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
}

.actions-cell .view-btn:hover {
    color: #3b82f6;
    background: #dbeafe;
}

.actions-cell .edit-btn:hover {
    color: #f59e0b;
    background: #fef3c7;
}

.actions-cell .delete-btn:hover {
    color: #ef4444;
    background: #fee2e2;
}

/* Loading & Empty states */
.loading-row,
.empty-row {
    text-align: center;
}

.loading-cell,
.empty-cell {
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6b7280;
}

.loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e5e7eb;
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin 1s linear infinite;
    margin-bottom: 12px;
}

.loading-text {
    font-size: 0.95rem;
    font-weight: 500;
}

.empty-icon {
    width: 48px;
    height: 48px;
    color: #d1d5db;
    margin-bottom: 12px;
}

.empty-text {
    margin: 0 0 16px;
    font-size: 0.95rem;
}

.reset-btn {
    padding: 6px 16px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.reset-btn:hover {
    background: #2563eb;
}

/* Selection Info */
.selection-info {
    padding: 12px 16px;
    background: #dbeafe;
    border-top: 1px solid #93c5fd;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.selection-count {
    font-weight: 500;
    color: #1e40af;
}

.selection-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.selection-action {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    padding: 4px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.selection-action:hover {
    background: rgba(59, 130, 246, 0.1);
}

/* Table Footer */
.table-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.footer-left {
    display: flex;
    align-items: center;
    gap: 24px;
}

.page-size-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-size-label {
    font-size: 0.875rem;
    color: #6b7280;
}

.page-size-select {
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: white;
    font-size: 0.875rem;
    color: #374151;
    cursor: pointer;
}

.page-size-select:focus {
    outline: none;
    border-color: #3b82f6;
}

.row-count {
    font-size: 0.875rem;
    color: #6b7280;
}

.filtered-count {
    color: #9ca3af;
    font-style: italic;
}

/* Pagination */
.pagination {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-icon {
    width: 16px;
    height: 16px;
}

.page-numbers {
    display: flex;
    gap: 4px;
}

.page-number {
    min-width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    color: #374151;
    transition: all 0.2s ease;
}

.page-number:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.page-number.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.pagination-ellipsis {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 0.875rem;
}

/* Animations */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.2s ease;
}

.slide-fade-enter,
.slide-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Responsive */
@media (max-width: 768px) {
    .table-header {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .header-right {
        flex-wrap: wrap;
    }

    .table-search .search-wrapper {
        width: 100%;
    }

    .table-footer {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }

    .footer-left {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .pagination {
        justify-content: center;
    }

    .filter-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90vw;
        max-width: 400px;
        margin: 0;
    }
}
</style>
