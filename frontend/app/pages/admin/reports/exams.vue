<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })
</script>

<template>
  <AdminWorkspaceShell
    title="Báo cáo kỳ thi"
    description="Tổng hợp kết quả thi, tỷ lệ đạt/trượt và thống kê điểm số của các học viên."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Báo cáo kỳ thi']"
  >
    <div class="dashboard-card crud-panel">
      <div class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2;">assignment</span>
        <p>Tính năng báo cáo kỳ thi đang được phát triển.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>
