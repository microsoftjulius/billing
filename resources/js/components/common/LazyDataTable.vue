<template>
  <div class="lazy-data-table-wrapper">
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th v-for="column in columns" :key="column.key">{{ column.title }}</th>
            <th v-if="showActions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in data" :key="index">
            <td v-for="column in columns" :key="column.key">
              <slot :name="`cell(${column.key})`" :row="row" :value="row[column.key]">
                {{ row[column.key] }}
              </slot>
            </td>
            <td v-if="showActions">
              <slot name="actions-cell" :row="row" :index="index"></slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  data?: any[];
  columns: any[];
  showActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  data: () => [],
  showActions: false,
});
</script>

<style scoped>
.lazy-data-table-wrapper {
  background: var(--card-bg);
  border-radius: 8px;
  overflow: hidden;
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.data-table th {
  background: var(--bg-secondary);
  font-weight: 600;
  color: var(--text-primary);
}

.data-table td {
  color: var(--text-primary);
}
</style>
