<script setup lang="ts">
import { ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'
import AcademicSiblingNav from '~/components/admin/AcademicSiblingNav.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

type Tab = 'plos' | 'clos' | 'skills'
const activeTab = ref<Tab>('plos')

const tabs: Array<{ id: Tab; label: string; icon: string }> = [
  { id: 'plos', label: 'PLO — CTĐT', icon: 'flag' },
  { id: 'clos', label: 'CLO — Học phần', icon: 'task_alt' },
  { id: 'skills', label: 'Kỹ năng', icon: 'bolt' },
]
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Chuẩn đầu ra']"
    title="Chuẩn đầu ra & Kỹ năng"
    description="Quản lý PLO/CLO và taxonomy kỹ năng dùng cho gợi ý lộ trình."
  >
    <AcademicSiblingNav active="outcomes" />

    <div class="outcomes-tabs dashboard-card">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="['outcomes-tab', { 'is-active': activeTab === tab.id }]"
        @click="activeTab = tab.id"
      >
        <span class="material-symbols-outlined">{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <AcademicResourceManager
      :key="activeTab"
      :allow-resource-switch="false"
      :initial-resource="activeTab"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.outcomes-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px;
  margin-bottom: 4px;
}
.outcomes-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 14px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 180ms ease, color 180ms ease;
}
.outcomes-tab:hover {
  background: rgba(var(--green-rgb), 0.06);
  color: var(--green-deep);
}
.outcomes-tab.is-active {
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
}
.outcomes-tab .material-symbols-outlined { font-size: 18px; }
</style>
