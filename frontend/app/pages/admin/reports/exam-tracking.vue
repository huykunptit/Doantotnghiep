<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })
</script>

<template>
  <AdminWorkspaceShell
    title="Theo dõi kỳ thi"
    description="Giám sát trực tiếp trạng thái tham gia thi và các hoạt động của học viên trong thời gian thực."
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Theo dõi kỳ thi']"
  >
    <div class="dashboard-card crud-panel">
      <div class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2;">visibility</span>
        <p>Tính năng theo dõi kỳ thi trực tiếp đang được phát triển.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>
