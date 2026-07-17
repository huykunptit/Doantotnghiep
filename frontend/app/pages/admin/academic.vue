<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

type Tab = 'majors' | 'programs' | 'cohorts'
const activeTab = ref<Tab>('majors')

const tabs: { id: Tab; label: string; desc: string }[] = [
  { id: 'majors',   label: 'Ngành học',              desc: 'Danh sách ngành và chuyên ngành đào tạo (UC01)' },
  { id: 'programs', label: 'Chương trình đào tạo',   desc: 'Các chương trình đào tạo theo ngành (UC02)' },
  { id: 'cohorts',  label: 'Khóa tuyển sinh',        desc: 'Các khóa sinh viên theo chương trình đào tạo' },
]

const activeDesc = computed(() => tabs.find(t => t.id === activeTab.value)?.desc ?? '')
</script>

<template>
  <div class="flex flex-col gap-5">

    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Đào tạo & Học vụ</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Danh mục Đào tạo</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">{{ activeDesc }}</p>
      </div>
    </div>

    <!-- Tabs + content panel -->
    <div class="bg-white border rounded-2xl shadow-sm overflow-hidden" style="border-color:var(--line)">
      <div class="catalog-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="catalog-tab"
          :class="{ 'is-active': activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="p-6">
        <AcademicResourceManager
          :key="activeTab"
          :initial-resource="activeTab"
          :allow-resource-switch="false"
        />
      </div>
    </div>

  </div>
</template>

<style scoped>
.catalog-tabs {
  display: flex; gap: 4px; flex-wrap: wrap;
  border-bottom: 2px solid var(--line); padding-bottom: 2px;
}
.catalog-tab {
  display: inline-flex; align-items: center; padding: 9px 18px;
  border: none; background: none; border-radius: 10px 10px 0 0;
  font-size: 0.88rem; font-weight: 600; color: var(--muted);
  cursor: pointer; position: relative; transition: color 0.15s, background 0.15s;
}
.catalog-tab:hover { color: var(--green-deep); background: rgba(var(--green-rgb), 0.04); }
.catalog-tab.is-active { color: var(--green-deep); }
.catalog-tab.is-active::after {
  content: ''; position: absolute; bottom: -4px; left: 0; right: 0;
  height: 3px; background: var(--green-deep); border-radius: 99px;
}
</style>
