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
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ', 'Đơn vị']"
    title="Quản lý Đơn vị"
    description="Quản lý viện, khoa, bộ môn và các đơn vị theo cấu trúc tổ chức."
  >
    <AcademicSiblingNav active="units" />
    <AcademicResourceManager :allow-resource-switch="false" initial-resource="units" />
  </AdminWorkspaceShell>
</template>
