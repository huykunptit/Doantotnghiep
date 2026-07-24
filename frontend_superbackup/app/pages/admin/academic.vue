<script setup lang="ts">
import { computed, ref } from 'vue'
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

    <Card>
      <template #content>
        <div class="flex flex-wrap gap-2 border-b border-[var(--p-content-border-color)] pb-3 mb-5">
          <Button
            v-for="tab in tabs"
            :key="tab.id"
            :label="tab.label"
            :severity="activeTab === tab.id ? 'primary' : 'secondary'"
            :outlined="activeTab !== tab.id"
            size="small"
            @click="activeTab = tab.id"
          />
        </div>
        <AcademicResourceManager
          :key="activeTab"
          :initial-resource="activeTab"
          :allow-resource-switch="false"
        />
      </template>
    </Card>
  </div>
</template>
