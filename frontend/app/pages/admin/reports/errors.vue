<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })
</script>

<template>
  <AdminWorkspaceShell
    title="Lịch sử báo lỗi"
    description="Xem danh sách các lỗi kỹ thuật và phản hồi từ người dùng được hệ thống ghi nhận."
    :breadcrumb="['Trang chủ', 'Hỗ trợ', 'Lịch sử báo lỗi']"
  >
    <div class="dashboard-card crud-panel">
      <div class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2;">bug_report</span>
        <p>Tính năng báo cáo lịch sử lỗi đang được phát triển.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>
