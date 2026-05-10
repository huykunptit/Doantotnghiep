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
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Khóa/Lớp']"
    title="Quản lý Khóa/Lớp"
    description="Quản lý lớp hành chính theo ngành, niên khóa và trạng thái hoạt động."
  >
    <AcademicSiblingNav active="cohorts" />
    <AcademicResourceManager :allow-resource-switch="false" initial-resource="cohorts" />
  </AdminWorkspaceShell>
</template>
