<template>
  <div class="lazy-data-table-wrapper">
    <!-- Table Header -->
    <div v-if="showHeader" class="table-header">
      <div class="header-left">
        <h3 v-if="title" class="table-title">{{ title }}</h3>
        <span v-if="description" class="table-description">{{ description }}</span>
      </div>
      <div class="header-right">
        <div v-if="showSearch" class="table-search">
          <input
            type="text"
            v-model="searchQuery"
            :placeholder="searchPlaceholder"
            class="search-input"
            @input="handleSearch"
          />
        </div>
        <div class="table-actions">
          <button
            v-if="showRefresh"
            class="action-btn refresh-btn"
            @click="refreshData"
            :disabled="loading"
          >
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Table Container -->
    <div class="table-container">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th v-if="selectable" class="checkbox-column">
                <input type="checkbox" @change="toggleSelectAll" />
              </th>
              <th v-for="column in columns" :key="column.key" :style="{ width: column.width }">
                {{ column.title }}
              </th>
              <th v-if="showActions" class="actions-column">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="totalColumns" class="loading-row">
                <div class="loading-cell">
                  <div class="loading-spinner"></div>
                  <span>{{ loadingText || 'Loading...' }}</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="data.length === 0">
              <td :colspan="totalColumns" class="empty-row">
                <div class="empty-cell">
                  <p>No data available</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="(row, index) in data" :key="getRowKey(row, index)">
              <td v-if="selectable" class="checkbox-cell">
                <input type="checkbox" @change="toggleRowSelection(row)" />
              </td>
              <td v-for="column in columns" :key="column.key">
                <slot :name="`cell(${column.key})`" :row="row" :value="row[column.key]">
                  {{ row[column.key] }}
                </slot>
              </td>
              <td v-if="showActions" class="actions-cell">
                <div class="action-buttons">
                  <slot name="actions-cell" :row="row" :index="index">
                    <button v-if="showViewAction" @click="$emit('view', row)">View</button>
                    <button v-if="showEditAction" @click="$emit('edit', row)">Edit</button>
                    <button v-if="showDeleteAction" @click="$emit('delete', row)">Delete</button>
                  </slot>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Table Footer -->
    <div v-if="showFooter" class="table-footer">
      <div class="footer-left">
        <div class="row-count">
          Showing {{ data.length }} items
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

interface Column {
  key: string;
  title: string;
  width?: string;
  minWidth?: string;
  sortable?: boolean;
}

interface Props {
  data?: any[];
  columns: Column[];
  title?: string;
  description?: string;
  selectable?: boolean;
  showActions?: boolean;
  showViewAction?: boolean;
  showEditAction?: boolean;
  showDeleteAction?: boolean;
  showHeader?: boolean;
  showFooter?: boolean;
  showSearch?: boolean;
  showRefresh?: boolean;
  rowKey?: string;
  searchPlaceholder?: string;
  loadingText?: string;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  data: () => [],
  selectable: false,
  showActions: false,
  showViewAction: false,
  showEditAction: false,
  showDeleteAction: false,
  showHeader: true,
  showFooter: true,
  showSearch: false,
  showRefresh: false,
  rowKey: 'id',
  searchPlaceholder: 'Search...',
  loadingText: 'Loading...',
  loading: false,
});

const emit = defineEmits<{
  view: [row: any];
  edit: [row: any];
  delete: [row: any];
  'row-click': [row: any];
  'selection-change': [selectedRows: any[]];
}>();

// State
const searchQuery = ref('');
const selectedRows = ref<any[]>([]);

// Computed
const totalColumns = computed(() => {
  let count = props.columns.length;
  if (props.selectable) count++;
  if (props.showActions) count++;
  return count;
});

// Methods
const getRowKey = (row: any, index: number) => {
  return props.rowKey ? row[props.rowKey] : index;
};

const toggleRowSelection = (row: any) => {
  const key = getRowKey(row, 0);
  const index = selectedRows.value.findIndex(selected => getRowKey(selected, 0) === key);
  
  if (index === -1) {
    selectedRows.value.push(row);
  } else {
    selectedRows.value.splice(index, 1);
  }
  
  emit('selection-change', selectedRows.value);
};

const toggleSelectAll = () => {
  if (selectedRows.value.length === props.data.length) {
    selectedRows.value = [];
  } else {
    selectedRows.value = [...props.data];
  }
  emit('selection-change', selectedRows.value);
};

const handleSearch = () => {
  // Search logic would go here
};

const refreshData = () => {
  // Refresh logic would go here
};
</script>

<style scoped>
.lazy-data-table-wrapper {
  background: var(--card-bg);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.table-header {
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.header-left {
  flex: 1;
  min-width: 0;
}

.table-title {
  margin: 0 0 4px;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
}

.table-description {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.search-input {
  width: 240px;
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.875rem;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.table-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.action-btn {
  padding: 8px 16px;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--text-primary);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.table-container {
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

.data-table thead {
  background: var(--bg-secondary);
}

.data-table th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.875rem;
  border-bottom: 2px solid var(--border-color);
}

.data-table tbody tr {
  border-bottom: 1px solid var(--border-light);
  transition: background-color 0.2s ease;
}

.data-table tbody tr:hover {
  background: var(--hover-bg);
}

.data-table td {
  padding: 12px 16px;
  font-size: 0.95rem;
  color: var(--text-primary);
  vertical-align: middle;
}

.checkbox-column,
.checkbox-cell {
  width: 48px;
}

.actions-column {
  width: 120px;
}

.actions-cell {
  white-space: nowrap;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.action-buttons button {
  padding: 4px 8px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 0.75rem;
  border-radius: 4px;
  color: var(--text-secondary);
}

.action-buttons button:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

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
  color: var(--text-secondary);
}

.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 50%;
  border-top-color: var(--primary-color);
  animation: spin 1s linear infinite;
  margin-bottom: 8px;
}

.table-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border-color);
  background: var(--bg-secondary);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.row-count {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>