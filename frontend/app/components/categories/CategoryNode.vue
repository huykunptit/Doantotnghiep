<script setup lang="ts">
import { computed } from 'vue'
// Icons removed - using PrimeIcons

interface CategoryItem {
  id: number
  name: string
  icon?: string | null
  parent_id?: number | null
  courses_count?: number
  sort_order?: number
}

const props = defineProps<{
  category: CategoryItem
  childrenMap: Record<number, CategoryItem[]>
  depth: number
}>()

const emit = defineEmits<{
  (e: 'edit', cat: CategoryItem): void
  (e: 'delete', cat: CategoryItem): void
  (e: 'view', cat: CategoryItem): void
}>()

const children = computed(() => props.childrenMap[props.category.id] || [])
const hasChildren = computed(() => children.value.length > 0)
</script>

<template>
  <div class="category-node" :class="{ 'has-children': hasChildren }">
    <!-- If has children, use details/summary for collapse/expand -->
    <details v-if="hasChildren" class="cat-details" open>
      <summary class="cat-summary">
        <div class="node-header">
          <ChevronRight class="chevron-icon" :size="16" />
          <span v-if="category.icon" class="cat-icon">{{ category.icon }}</span>
          <span class="cat-name">{{ category.name }}</span>
          <span class="cat-badge">{{ category.courses_count || 0 }} khóa học</span>
          <span class="cat-order">Thứ tự: {{ category.sort_order || 0 }}</span>
          
          <div class="node-actions">
            <button class="action-btn view-btn" type="button" @click.stop="emit('view', category)" title="Chi tiết">
              <i class="pi pi-eye" style="font-size:0.875rem" />
            </button>
            <button class="action-btn edit-btn" type="button" @click.stop="emit('edit', category)" title="Sửa">
              <i class="pi pi-pencil" style="font-size:0.875rem" />
            </button>
            <button class="action-btn delete-btn" type="button" @click.stop="emit('delete', category)" title="Xóa">
              <i class="pi pi-trash" style="font-size:0.875rem" />
            </button>
          </div>
        </div>
      </summary>
      
      <div class="node-children">
        <CategoryNode
          v-for="child in children"
          :key="child.id"
          :category="child"
          :children-map="childrenMap"
          :depth="depth + 1"
          @edit="emit('edit', $event)"
          @delete="emit('delete', $event)"
          @view="emit('view', $event)"
        />
      </div>
    </details>

    <!-- If leaf category, render flat node -->
    <div v-else class="node-header leaf-node">
      <div class="leaf-dot" />
      <span v-if="category.icon" class="cat-icon">{{ category.icon }}</span>
      <span class="cat-name">{{ category.name }}</span>
      <span class="cat-badge">{{ category.courses_count || 0 }} khóa học</span>
      <span class="cat-order">Thứ tự: {{ category.sort_order || 0 }}</span>
      
      <div class="node-actions">
        <button class="action-btn view-btn" type="button" @click.stop="emit('view', category)" title="Chi tiết">
          <i class="pi pi-eye" style="font-size:0.875rem" />
        </button>
        <button class="action-btn edit-btn" type="button" @click="emit('edit', category)" title="Sửa">
          <i class="pi pi-pencil" style="font-size:0.875rem" />
        </button>
        <button class="action-btn delete-btn" type="button" @click="emit('delete', category)" title="Xóa">
          <i class="pi pi-trash" style="font-size:0.875rem" />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.category-node {
  margin: 6px 0;
  font-family: inherit;
}

.cat-details {
  border: 1px solid var(--line);
  background: var(--surface-strong);
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.2s;
  box-shadow: 0 2px 8px rgba(0,0,0,0.015);
}
.cat-details:hover {
  border-color: rgba(var(--primary-rgb), 0.25);
}

.cat-summary {
  display: block; /* Hide default triangle in Chrome/Safari */
  list-style: none; /* Hide default triangle in Firefox */
  cursor: pointer;
  outline: none;
  background: var(--surface-strong);
}
.cat-summary::-webkit-details-marker {
  display: none; /* Hide Chrome/Safari marker */
}

.node-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--text);
  user-select: none;
}

.leaf-node {
  border: 1px solid var(--line);
  background: var(--surface-strong);
  border-radius: 10px;
  padding: 10px 16px;
  margin-left: 28px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.01);
}
.leaf-node:hover {
  border-color: rgba(var(--primary-rgb), 0.2);
}

.leaf-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--muted);
  opacity: 0.5;
  margin-left: 4px;
  margin-right: 4px;
}

.chevron-icon {
  color: var(--muted);
  transition: transform 0.2s ease;
}
.cat-details[open] > .cat-summary .chevron-icon {
  transform: rotate(90deg);
}

.cat-icon {
  font-size: 1.1rem;
}

.cat-name {
  flex: 1;
  font-weight: 700;
}

.cat-badge {
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--green);
  background: var(--green-soft);
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: -0.01em;
}

.cat-order {
  font-size: 0.76rem;
  color: var(--muted);
  font-weight: 500;
}

.node-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  opacity: 0;
  transition: opacity 0.25s ease;
}
.node-header:hover .node-actions {
  opacity: 1;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
}
.edit-btn:hover {
  background: var(--green-soft);
  color: var(--green-deep);
  border-color: rgba(var(--primary-rgb), 0.3);
}
.delete-btn:hover {
  background: var(--danger-soft);
  color: var(--danger);
  border-color: rgba(226, 75, 74, 0.3);
}
.view-btn:hover {
  background: rgba(2, 132, 199, 0.1);
  color: #0284c7;
  border-color: rgba(2, 132, 199, 0.3);
}

.node-children {
  padding: 4px 12px 12px 24px;
  background: rgba(var(--primary-rgb), 0.01);
  border-top: 1px dashed var(--line);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
</style>
