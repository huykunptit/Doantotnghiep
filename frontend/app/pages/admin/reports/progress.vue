<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })
</script>

<template>
  <AdminWorkspaceShell
    title="Tiến độ học tập"
    description="Theo dõi lộ trình hoàn thành bài học và mức độ chuyên cần của học viên theo từng khóa học."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Tiến độ học tập']"
  >
    <div class="dashboard-card crud-panel">
      <div class="crud-empty">
        <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2;">trending_up</span>
        <p>Tính năng báo cáo tiến độ học tập đang được phát triển.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>
