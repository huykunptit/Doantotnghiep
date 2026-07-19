<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({
  layout: 'admin'
})
</script>

<template>
  <AdminWorkspaceShell 
    title="Cài đặt hệ thống" 
    subtitle="Cấu hình hệ thống, tham số học tập và các tùy chọn khác."
    :breadcrumbs="[{ label: 'AI & Hệ thống' }, { label: 'Cài đặt hệ thống' }]"
  >
    <div class="bg-white border border-[var(--line)] rounded-2xl p-6 shadow-sm">
      <p class="text-sm text-[var(--muted)]">Nội dung đang được thiết kế...</p>
    </div>
  </AdminWorkspaceShell>
</template>
