<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'
import AcademicSiblingNav from '~/components/admin/AcademicSiblingNav.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Học kỳ']"
    title="Quản lý Học kỳ"
    description="Quản lý năm học, học kỳ và các mốc thời gian học vụ."
  >
    <AcademicSiblingNav active="terms" />
    <AcademicResourceManager :allow-resource-switch="false" initial-resource="terms" />
  </AdminWorkspaceShell>
</template>
