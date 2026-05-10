<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })
</script>

<template>
  <AdminWorkspaceShell
    title="Nhật ký hoạt động"
    description="Lưu trữ lịch sử các thao tác quan trọng của admin, giảng viên và người dùng trên hệ thống."
    :breadcrumb="['Trang chủ', 'Hệ thống', 'Nhật ký hoạt động']"
  >
    <div class="dashboard-card crud-panel">
      <div class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2;">history_edu</span>
        <p>Tính năng nhật ký hoạt động đang được phát triển.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>
